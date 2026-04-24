<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Setting;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;

class PatientAppointmentController extends AppBaseController
{
    /**
     * @return Application|Factory|View
     */
    public function index(): \Illuminate\View\View
    {
        $allPaymentStatus = getAllPaymentStatus();
        $paymentStatus = Arr::except($allPaymentStatus, [Appointment::MANUALLY]);
        $paymentGateway = getPaymentGateway();
        $logo = Setting::where('key', 'logo')->pluck('value');

        return view('patients.appointments.index', compact('paymentStatus', 'paymentGateway','logo'));
    }

    public function bookingAppointments($id): \Illuminate\View\View
    {
        $allPaymentStatus = getAllPaymentStatus();
        $paymentStatus = Arr::except($allPaymentStatus, [Appointment::MANUALLY]);
        $paymentGateway = getPaymentGateway();
        $logo = Setting::where('key', 'logo')->pluck('value');

        return view('patients.appointments.bookings', compact('id','paymentStatus', 'paymentGateway','logo'));
    }

    public function confirmed_bookings(): \Illuminate\View\View
    {
        $allPaymentStatus = getAllPaymentStatus();
        $paymentStatus = Arr::except($allPaymentStatus, [Appointment::MANUALLY]);
        $paymentGateway = getPaymentGateway();
        $logo = Setting::where('key', 'logo')->pluck('value');

        return view('patients.appointments.confirmed-bookings', compact('paymentStatus', 'paymentGateway','logo'));
    }

    public function feedbackBookingAppointments($id): \Illuminate\View\View
    {
        return view('patients.appointments.feedback-booking-detail', compact('id'));
    }

    public function feedback_bookings(): \Illuminate\View\View
    {
        $allPaymentStatus = getAllPaymentStatus();
        $paymentStatus = Arr::except($allPaymentStatus, [Appointment::MANUALLY]);
        $paymentGateway = getPaymentGateway();
        $logo = Setting::where('key', 'logo')->pluck('value');

        return view('patients.appointments.feedback-bookings', compact('paymentStatus', 'paymentGateway','logo'));
    }

    public function pending_bookings()
    {
        // CP-19: Show one row per package for everything currently in flight
        // (BOOKED + CHECK_IN + BOOKING_PENDING — not CHECK_OUT, not CANCELLED).
        // The view decides how to render each row based on whether the
        // package still has at least one BOOKING_PENDING appointment:
        //   - has pending → label "Action Required", link to booking wizard
        //   - fully booked → label "Booked", link to package detail (shows
        //     the booked appointments)
        //
        // The subquery is scoped to THIS patient + the same status set so a
        // race condition can never make a booked package show up as pending
        // (and vice-versa).
        $patientId     = getLoginUser()->patient->id;
        $activeStatuses = [Appointment::BOOKED, Appointment::CHECK_IN, Appointment::BOOKING_PENDING];

        $appointments = Appointment::with([
                'doctor.user',
                'services',
                'transaction',
                'doctor.reviews',
            ])
            ->where('patient_id', $patientId)
            ->whereIn('status', $activeStatuses)
            ->whereIn('appointments.id', function ($q) use ($patientId, $activeStatuses) {
                $q->selectRaw('MIN(id)')
                    ->from('appointments')
                    ->where('patient_id', $patientId)
                    ->whereIn('status', $activeStatuses)
                    ->groupBy('relation_id');
            })
            ->orderByDesc('appointments.id')
            ->get();

        // Per-package pending flag used by the view to switch between
        // "Booked" and "Action Required" states. One SELECT total — O(1).
        $pendingRelationIds = Appointment::where('patient_id', $patientId)
            ->where('status', Appointment::BOOKING_PENDING)
            ->distinct()
            ->pluck('relation_id')
            ->flip(); // allow O(1) isset() lookup

        foreach ($appointments as $appt) {
            $appt->setAttribute('has_pending_in_package', isset($pendingRelationIds[$appt->relation_id]));
        }

        return view('patients.appointments.pending-bookings', compact('appointments'));
    }
}
