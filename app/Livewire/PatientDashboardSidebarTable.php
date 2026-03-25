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

        $this->pendingAppointments =  Appointment::with(['patient.user', 'doctor.user', 'services'])
            ->wherePatientId($patientId)
            ->whereStatus(Appointment::BOOKING_PENDING)
            ->whereIn('appointments.id', function ($q) {
                $q->selectRaw('MAX(id)')
                    ->from('appointments')
                    ->whereStatus(Appointment::BOOKING_PENDING)
                    ->groupBy('relation_id');
            })
            ->whereIn('appointment_type', ['assessment', 'feedback'])
            ->orderByDesc('appointments.id')
            ->get();

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
