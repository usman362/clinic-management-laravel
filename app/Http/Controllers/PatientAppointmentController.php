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
        $query = Appointment::with([
            'doctor.user',
            'services',
            'transaction',
            'doctor.reviews',
        ])->where('patient_id', getLoginUser()->patient->id)->where('status',5)->select('appointments.*');


        $query->whereIn('appointments.id', function ($q) {
            $q->selectRaw('MAX(appointments.id)')
            ->from('appointments')
            ->groupBy('appointments.relation_id');
        });
        $appointments = $query->select('appointments.*')->get();
        return view('patients.appointments.pending-bookings',compact('appointments'));
    }
}
