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

    protected $fillable = [
        'relation_id',
        'patient_id',
        'created_by',
        'appointment_type',
        'description',
        'payable_amount',
        'payment_type',
        'payment_method',
        'feedback_sent_at',
        'parent_package_id',
    ];

    protected $casts = [
        'patient_id'        => 'integer',
        'created_by'        => 'integer',
        'payment_type'      => 'integer',
        'parent_package_id' => 'integer',
        'feedback_sent_at'  => 'datetime',
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

    // ── Computed helpers ──────────────────────────────────────────────────────

    /** Overall status derived from appointments. */
    public function getStatusLabelAttribute(): string
    {
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
