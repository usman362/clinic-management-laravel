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
     * AP-03: When a package is deleted, cancel all its related appointments
     * rather than leaving orphans in the patient's view.
     */
    protected static function booted(): void
    {
        static::deleting(function (Package $package) {
            Appointment::where('relation_id', $package->relation_id)
                ->whereNotIn('status', [Appointment::CANCELLED])
                ->update(['status' => Appointment::CANCELLED]);
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
     */
    public function refreshStatus(): self
    {
        $appts  = $this->appointments()->get();
        $total  = $appts->count();
        $booked = $appts->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN, Appointment::CHECK_OUT])->count();
        $done   = $appts->where('status', Appointment::CHECK_OUT)->count();

        if ($total > 0 && $done === $total) {
            $status = self::STATUS_COMPLETED;
        } elseif ($this->feedback_sent_at || $this->feedbackPackages()->exists()) {
            $fbPkg = $this->feedbackPackages()->first();
            if ($fbPkg) {
                $fbDone = $fbPkg->appointments()->where('status', Appointment::CHECK_OUT)->count();
                $fbTotal = $fbPkg->appointments()->count();
                if ($fbTotal > 0 && $fbDone === $fbTotal) {
                    $status = self::STATUS_COMPLETED;
                } elseif ($fbPkg->appointments()->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN])->exists()) {
                    $status = self::STATUS_FEEDBACK_BOOKED;
                } else {
                    $status = self::STATUS_FEEDBACK_SENT;
                }
            } else {
                $status = self::STATUS_FEEDBACK_SENT;
            }
        } elseif ($booked > 0) {
            $status = self::STATUS_APPOINTMENTS_BOOKED;
        } elseif ($total > 0) {
            $status = self::STATUS_LINK_SENT;
        } else {
            $status = self::STATUS_PENDING;
        }

        $this->update(['status' => $status]);
        return $this;
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

    /** Feedback status for assessment packages: Not sent / Sent (date) / Booked / Completed. */
    public function getFeedbackStatusLabelAttribute(): string
    {
        if ($this->appointment_type !== 'assessment') {
            return '';
        }

        // Check if a feedback package exists for this assessment package
        $feedbackPkg = $this->feedbackPackages()->first();

        if (! $feedbackPkg) {
            // No feedback package linked — check if feedback was sent
            if ($this->feedback_sent_at) {
                return 'Sent ' . $this->feedback_sent_at->format('d M Y');
            }
            return 'Not sent';
        }

        // Feedback package exists — derive status from its appointments
        $fbAppts = $feedbackPkg->appointments;
        $total   = $fbAppts->count();
        $done    = $fbAppts->where('status', Appointment::CHECK_OUT)->count();
        $booked  = $fbAppts->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN])->count();

        if ($total > 0 && $done === $total) return 'Completed';
        if ($booked > 0 || $done > 0)       return 'Booked';
        return 'Sent ' . ($feedbackPkg->created_at ? $feedbackPkg->created_at->format('d M Y') : '');
    }

    /** Find a Package by its relation_id string. */
    public static function findByRelationId(string $relationId): ?self
    {
        return static::where('relation_id', $relationId)->first();
    }
}
