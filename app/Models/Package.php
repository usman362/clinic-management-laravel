<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * First-class Package entity.
 * Identified by relation_id (same value stored on each Appointment in the package).
 *
 * @property int    $id
 * @property string $relation_id
 * @property int    $patient_id
 * @property int    $created_by
 * @property string $appointment_type   assessment | feedback
 * @property string|null $description
 * @property string|null $payable_amount
 * @property int|null    $payment_type
 * @property string|null $payment_method
 * @property \Carbon\Carbon|null $feedback_sent_at
 * @property int|null    $parent_package_id
 */
class Package extends Model
{
    use HasFactory;

    /**
     * AP-03 V8: When a Package is deleted, HARD DELETE all related appointments
     * (including already-cancelled ones), their transactions, consent documents
     * and Google Calendar links. Client requirement: "The cancelled appointment
     * should no longer exist, as we deleted the package."
     *
     * Note: The primary entry point is AppointmentController::destroy() which already
     * does this cleanup. This event ensures consistency for any direct Package::delete()
     * call (e.g. via tinker, future admin tools).
     */
    protected static function booted(): void
    {
        static::deleting(function (Package $package) {
            $relationId = $package->relation_id;
            if (! $relationId) {
                return;
            }

            $appointments = Appointment::where('relation_id', $relationId)->get();
            $apptIds       = $appointments->pluck('id')->all();
            $apptUniqueIds = $appointments->pluck('appointment_unique_id')->filter()->all();

            if (! empty($apptUniqueIds)) {
                \App\Models\Transaction::whereIn('appointment_id', $apptUniqueIds)->delete();
            }
            if (! empty($apptIds)) {
                Document::whereIn('appointment_id', $apptIds)->where('type', 'consent')->delete();
                if (class_exists(UserGoogleAppointment::class)) {
                    UserGoogleAppointment::whereIn('appointment_id', $apptIds)->delete();
                }
            }
            Appointment::where('relation_id', $relationId)->delete();
        });
    }

    // AP-04: Package lifecycle statuses.
    const STATUS_PENDING             = 'pending';
    const STATUS_LINK_SENT           = 'link_sent';
    const STATUS_APPOINTMENTS_BOOKED = 'appointments_booked';
    const STATUS_FEEDBACK_SENT       = 'feedback_sent';
    const STATUS_FEEDBACK_BOOKED     = 'feedback_booked';
    const STATUS_COMPLETED           = 'completed';

    const STATUSES = [
        self::STATUS_PENDING             => 'Pending',
        self::STATUS_LINK_SENT           => 'Link Sent',
        self::STATUS_APPOINTMENTS_BOOKED => 'Appointments Booked',
        self::STATUS_FEEDBACK_SENT       => 'Feedback Sent',
        self::STATUS_FEEDBACK_BOOKED     => 'Feedback Booked',
        self::STATUS_COMPLETED           => 'Completed',
    ];

    protected $fillable = [
        'relation_id',
        'patient_id',
        'created_by',
        'appointment_type',
        'status',
        'description',
        'payable_amount',
        'payment_type',
        'payment_method',
        'payment_received',
        'payment_received_at',
        'payment_notes',
        'feedback_sent_at',
        'parent_package_id',
    ];

    protected $casts = [
        'patient_id'          => 'integer',
        'created_by'          => 'integer',
        'payment_type'        => 'integer',
        'payment_received'    => 'boolean',
        'payment_received_at' => 'datetime',
        'parent_package_id'   => 'integer',
        'feedback_sent_at'    => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    /** All appointments belonging to this package. */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'relation_id', 'relation_id');
    }

    /** Appointments that are assessment-type (not feedback). */
    public function assessmentAppointments()
    {
        return $this->hasMany(Appointment::class, 'relation_id', 'relation_id')
            ->where('appointment_type', '!=', 'feedback');
    }

    /** Feedback appointments in this package. */
    public function feedbackAppointments()
    {
        return $this->hasMany(Appointment::class, 'relation_id', 'relation_id')
            ->where('appointment_type', 'feedback');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The assessment package this feedback package was created from. */
    public function parentPackage()
    {
        return $this->belongsTo(self::class, 'parent_package_id');
    }

    /** The feedback package(s) linked to this assessment package. */
    public function feedbackPackages()
    {
        return $this->hasMany(self::class, 'parent_package_id');
    }

    // ── Status workflow helpers (AP-04) ─────────────────────────────────────

    /** Mark booking link as sent to patient. */
    public function markLinkSent(): self
    {
        $this->update(['status' => self::STATUS_LINK_SENT]);
        return $this;
    }

    /** Record that manual payment has been received. */
    public function recordPaymentReceived(?string $notes = null): self
    {
        $this->update([
            'payment_received'    => true,
            'payment_received_at' => now(),
            'payment_notes'       => $notes,
        ]);
        return $this;
    }

    /**
     * Re-derive status from appointment states and persist it.
     * Call this after any appointment status change within the package.
     *
     * For assessment packages: aggregates status across ALL child feedback
     * packages (a package may now have multiple child feedback packages,
     * one per doctor or doctor-subset).
     */
    public function refreshStatus(): self
    {
        $appts  = $this->appointments()->get();
        $total  = $appts->count();
        $booked = $appts->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN, Appointment::CHECK_OUT])->count();
        $done   = $appts->where('status', Appointment::CHECK_OUT)->count();

        if ($this->appointment_type === 'assessment') {
            $feedbackPkgs = $this->feedbackPackages()->with('appointments')->get();

            if ($feedbackPkgs->isNotEmpty()) {
                // Aggregate across every child feedback package
                $allFbAppts = $feedbackPkgs->flatMap->appointments;
                $fbTotal    = $allFbAppts->count();
                $fbDone     = $allFbAppts->where('status', Appointment::CHECK_OUT)->count();
                $fbBookedCount = $allFbAppts->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN])->count();

                // Do all assessment doctors have a feedback appointment?
                $assessmentDoctorIds = $this->getAssessmentDoctorIds();
                $doctorsWithFb       = $allFbAppts->pluck('doctor_id')->unique()->values()->all();
                $allDoctorsCovered   = empty(array_diff($assessmentDoctorIds, $doctorsWithFb));

                if ($allDoctorsCovered && $fbTotal > 0 && $fbDone === $fbTotal) {
                    $status = self::STATUS_COMPLETED;
                } elseif ($fbBookedCount > 0 || $fbDone > 0) {
                    $status = self::STATUS_FEEDBACK_BOOKED;
                } else {
                    $status = self::STATUS_FEEDBACK_SENT;
                }
            } elseif ($this->feedback_sent_at) {
                $status = self::STATUS_FEEDBACK_SENT;
            } elseif ($total > 0 && $done === $total) {
                $status = self::STATUS_COMPLETED;
            } elseif ($booked > 0) {
                $status = self::STATUS_APPOINTMENTS_BOOKED;
            } elseif ($total > 0) {
                $status = self::STATUS_LINK_SENT;
            } else {
                $status = self::STATUS_PENDING;
            }
        } else {
            // Feedback package — status reflects only its own appointments
            if ($total > 0 && $done === $total) {
                $status = self::STATUS_COMPLETED;
            } elseif ($booked > 0) {
                $status = self::STATUS_FEEDBACK_BOOKED;
            } else {
                $status = self::STATUS_FEEDBACK_SENT;
            }
        }

        $this->update(['status' => $status]);
        return $this;
    }

    // ── Per-doctor feedback helpers (multi-send support) ────────────────────

    /**
     * Unique doctor IDs from this package's assessment appointments.
     */
    public function getAssessmentDoctorIds(): array
    {
        return $this->assessmentAppointments()
            ->pluck('doctor_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Doctor IDs that already have a feedback appointment in any child
     * feedback package of this assessment package.
     */
    public function getDoctorIdsWithFeedback(): array
    {
        $fbRelationIds = $this->feedbackPackages()->pluck('relation_id');
        if ($fbRelationIds->isEmpty()) {
            return [];
        }
        return Appointment::whereIn('relation_id', $fbRelationIds)
            ->pluck('doctor_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Assessment doctor IDs that have NOT yet been sent feedback.
     */
    public function getDoctorIdsWithoutFeedback(): array
    {
        return array_values(array_diff(
            $this->getAssessmentDoctorIds(),
            $this->getDoctorIdsWithFeedback()
        ));
    }

    /**
     * Per-doctor feedback status map — used by the "Send Feedback" modal to
     * show each doctor's current status in the UI.
     *
     * Returns: [doctor_id => 'completed'|'booked'|'sent'|null, ...]
     */
    public function getDoctorFeedbackStatusMap(): array
    {
        $map = [];
        foreach ($this->getAssessmentDoctorIds() as $docId) {
            $map[$docId] = null;
        }

        $feedbackPkgs = $this->feedbackPackages()->with('appointments')->get();
        foreach ($feedbackPkgs as $fbPkg) {
            foreach ($fbPkg->appointments as $appt) {
                $docId = $appt->doctor_id;
                if (! array_key_exists($docId, $map)) {
                    continue;
                }
                // Priority: completed > booked > sent (so later fb appts can't downgrade)
                $current = $map[$docId];
                $incoming = match ((int) $appt->status) {
                    Appointment::CHECK_OUT                         => 'completed',
                    Appointment::BOOKED, Appointment::CHECK_IN     => 'booked',
                    default                                        => 'sent',
                };
                $rank = ['sent' => 1, 'booked' => 2, 'completed' => 3];
                if ($current === null || $rank[$incoming] > $rank[$current]) {
                    $map[$docId] = $incoming;
                }
            }
        }

        return $map;
    }

    /** Human-readable status badge color for views. */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED           => 'bg-success',
            self::STATUS_APPOINTMENTS_BOOKED => 'bg-primary',
            self::STATUS_FEEDBACK_BOOKED     => 'bg-info',
            self::STATUS_FEEDBACK_SENT       => 'bg-warning text-dark',
            self::STATUS_LINK_SENT           => 'bg-secondary',
            default                          => 'bg-light text-dark',
        };
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    /** Overall status — prefer explicit status field, fall back to derived. */
    public function getStatusLabelAttribute(): string
    {
        // If explicit status is set and meaningful, use it.
        if ($this->status && isset(self::STATUSES[$this->status])) {
            return self::STATUSES[$this->status];
        }

        // Legacy fallback: derive from appointments.
        $appts = $this->appointments;
        $total = $appts->count();
        $done  = $appts->where('status', Appointment::CHECK_OUT)->count();
        $booked = $appts->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN, Appointment::CHECK_OUT])->count();

        if ($total > 0 && $done === $total) return 'Completed';
        if ($booked > 0) return 'In Progress';
        return 'Pending';
    }

    /**
     * Feedback status for assessment packages.
     * Shows aggregate across multiple child feedback packages:
     *   "Not sent" / "Sent 2/3 (15 Apr)" / "Booked 2/3" / "Completed"
     */
    public function getFeedbackStatusLabelAttribute(): string
    {
        if ($this->appointment_type !== 'assessment') {
            return '';
        }

        $feedbackPkgs = $this->feedbackPackages()->with('appointments')->get();

        if ($feedbackPkgs->isEmpty()) {
            if ($this->feedback_sent_at) {
                return 'Sent ' . $this->feedback_sent_at->format('d M Y');
            }
            return 'Not sent';
        }

        $allFbAppts = $feedbackPkgs->flatMap->appointments;
        $fbTotal    = $allFbAppts->count();
        $fbDone     = $allFbAppts->where('status', Appointment::CHECK_OUT)->count();
        $fbBooked   = $allFbAppts->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN])->count();

        $assessmentDoctorCount = count($this->getAssessmentDoctorIds());
        $doctorsWithFbCount    = $allFbAppts->pluck('doctor_id')->unique()->count();
        $allCovered            = ($doctorsWithFbCount >= $assessmentDoctorCount);

        $coverage = $doctorsWithFbCount . '/' . max($assessmentDoctorCount, $doctorsWithFbCount);

        if ($allCovered && $fbTotal > 0 && $fbDone === $fbTotal) {
            return 'Completed';
        }
        if ($fbBooked > 0 || $fbDone > 0) {
            return 'Booked ' . $coverage;
        }

        $latestCreatedAt = $feedbackPkgs->sortByDesc('created_at')->first()?->created_at;
        return 'Sent ' . $coverage . ($latestCreatedAt ? ' (' . $latestCreatedAt->format('d M Y') . ')' : '');
    }

    /** Find a Package by its relation_id string. */
    public static function findByRelationId(string $relationId): ?self
    {
        return static::where('relation_id', $relationId)->first();
    }
}
