<?php

namespace App\Livewire;

use App\Models\Appointment;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Lazy;

#[Lazy]
class PatientDashboardSidebarTable extends Component
{

    public $todayAppointmentCount;

    public $upcomingAppointmentCount;

    public $totalAppointmentCount;

    public $pendingAppointmentCount;

    public $pastCompletedAppointmentCount;

    public $completedAppointmentCount;

    public $todayAppointment;

    public $upcomingAppointment;

    public $pendingAppointments;

    public $pastPendingAppointments;

    public function mount()
    {
        $todayDate = Carbon::now()->format('Y-m-d');
        $patientId = getLogInUser()->patient->id;

        $todayCompleted = Appointment::wherePatientId($patientId)
            ->where('date', '=', $todayDate)
            ->whereStatus(Appointment::CHECK_OUT)
            ->count();

        $this->todayAppointmentCount = Appointment::wherePatientId($patientId)
            ->where('date', '=', $todayDate)
            ->whereNotIn('status', [Appointment::CANCELLED, Appointment::BOOKING_PENDING])
            ->count();

        $this->upcomingAppointmentCount = Appointment::wherePatientId($patientId)
            ->where('date', '>=', $todayDate)
            ->whereNotIn('status', [Appointment::CANCELLED, Appointment::BOOKING_PENDING])
            ->count();

        // Total = real confirmed appointments (excludes cancelled and unfinished booking drafts)
        $this->totalAppointmentCount = Appointment::wherePatientId($patientId)
            ->whereNotIn('status', [Appointment::CANCELLED, Appointment::BOOKING_PENDING])
            ->count();

        // Pending = booked or checked-in appointments (not yet completed)
        $this->pendingAppointmentCount = Appointment::wherePatientId($patientId)
            ->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN])
            ->count();

        // Completed = all CHECK_OUT appointments regardless of date
        $this->completedAppointmentCount = Appointment::wherePatientId($patientId)
            ->whereStatus(Appointment::CHECK_OUT)
            ->count();

        $this->pastCompletedAppointmentCount = $this->completedAppointmentCount;

        // Today appointments list: both assessment and feedback, not cancelled/booking_pending
        $this->todayAppointment = Appointment::with(['patient.user', 'doctor.user', 'services'])
            ->wherePatientId($patientId)
            ->where('date', '=', $todayDate)
            ->whereNotIn('status', [Appointment::CANCELLED, Appointment::BOOKING_PENDING])
            ->orderBy('created_at', 'DESC')
            ->get();

        // Upcoming appointments list: both assessment and feedback, future, not cancelled/booking_pending
        $this->upcomingAppointment = Appointment::with(['patient.user', 'doctor.user', 'services'])
            ->wherePatientId($patientId)
            ->where('date', '>', $todayDate)
            ->whereNotIn('status', [Appointment::CANCELLED, Appointment::BOOKING_PENDING])
            ->get();

        // CP-19: Show one row per active package (BOOKED + CHECK_IN +
        // BOOKING_PENDING). The blade uses the per-row
        // `has_pending_in_package` flag set below to decide the label:
        //   - any pending appointment in the package → "Action Required"
        //     + link to the booking wizard (book-by-token)
        //   - all appointments booked                → "Booked"
        //     + link to the package detail page (shows booked appointments)
        // The raw URL column is removed from the view.
        $activeStatuses = [Appointment::BOOKED, Appointment::CHECK_IN, Appointment::BOOKING_PENDING];

        $this->pendingAppointments = Appointment::with(['patient.user', 'doctor.user', 'services'])
            ->wherePatientId($patientId)
            ->whereIn('status', $activeStatuses)
            ->whereIn('appointments.id', function ($q) use ($patientId, $activeStatuses) {
                $q->selectRaw('MIN(id)')
                    ->from('appointments')
                    ->where('patient_id', $patientId)
                    ->whereIn('status', $activeStatuses)
                    ->groupBy('relation_id');
            })
            ->whereIn('appointment_type', ['assessment', 'feedback'])
            ->orderByDesc('appointments.id')
            ->get();

        // Flag each row — O(1) lookup via a pluck-and-flip
        $pendingRelationIds = Appointment::wherePatientId($patientId)
            ->where('status', Appointment::BOOKING_PENDING)
            ->distinct()
            ->pluck('relation_id')
            ->flip();
        foreach ($this->pendingAppointments as $appt) {
            $appt->setAttribute('has_pending_in_package', isset($pendingRelationIds[$appt->relation_id]));
        }

        // Past pending appointments: BOOKED or CHECK_IN with date < today
        $this->pastPendingAppointments = Appointment::with(['patient.user', 'doctor.user', 'services'])
            ->wherePatientId($patientId)
            ->where('date', '<', $todayDate)
            ->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN])
            ->orderByDesc('date')
            ->get();
    }
    public function placeholder()
    {
        return view('livewire.patient_dashboard_sidebar_skeleton');
    }
    public function render()
    {
        return view('livewire.patient-dashboard-sidebar-table');
    }
}
