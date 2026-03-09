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
    ];

    protected $casts = [
        'patient_id'   => 'integer',
        'created_by'   => 'integer',
        'payment_type' => 'integer',
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

    /** Find a Package by its relation_id string. */
    public static function findByRelationId(string $relationId): ?self
    {
        return static::where('relation_id', $relationId)->first();
    }
}
