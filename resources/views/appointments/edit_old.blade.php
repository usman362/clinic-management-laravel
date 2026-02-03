@extends('layouts.app')
@section('title')
    {{ __('Update Appointment') }}
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-end mb-5">
            <h1>@yield('title')</h1>
            @role('patient')
                <a href="{{ route('patients.patient-appointments-index') }}"
                    class="btn btn-outline-primary float-end">{{ __('messages.common.back') }}</a>
            @else
                <a href="{{ route('appointments.index') }}"
                    class="btn btn-outline-primary float-end">{{ __('messages.common.back') }}</a>
            @endrole
        </div>

        <div class="col-12">
            @include('layouts.errors')
        </div>
        <div class="card">
            <div class="card-body">
                {{ Form::hidden(null, false, ['id' => 'appointmentIsEdit']) }}
                {{ Form::hidden(null, \App\Models\Appointment::PAYSTACK, ['id' => 'paystackMethod']) }}
                {{ Form::hidden(null, \App\Models\Appointment::PAYTM, ['id' => 'paytmMethod']) }}
                {{ Form::hidden(null, \App\Models\Appointment::AUTHORIZE, ['id' => 'authorizeMethod']) }}
                {{ Form::hidden(null, \App\Models\Appointment::PAYPAL, ['id' => 'paypalMethod']) }}
                {{ Form::hidden(null, \App\Models\Appointment::MANUALLY, ['id' => 'manuallyMethod']) }}
                {{ Form::hidden(null, \App\Models\Appointment::STRIPE, ['id' => 'stripeMethod']) }}
                {{ Form::hidden(null, \App\Models\Appointment::RAZORPAY, ['id' => 'razorpayMethodMethod']) }}
                @if (getLogInUser()->hasRole('patient') || getLogInUser()->hasRole('doctor'))
                    @if (getLogInUser()->hasRole('patient'))
                        {{ Form::open(['route' => ['patients.appointments.update', $appointment->id], 'id' => 'addAppointmentForm']) }}
                    @else((getLogInUser()->hasRole('doctor')))
                        {{ Form::open(['route' => ['doctors.appointments.update', $appointment->id], 'id' => 'addAppointmentForm']) }}
                    @endif
                @else(getLogInUser()->hasRole('admin'))
                    {{ Form::open(['route' => ['appointments.update', $appointment->id], 'id' => 'addAppointmentForm']) }}
                @endif
                @method('PUT')
                <div class="row">

                    @role('patient')
                        {{ Form::hidden('patient_id', getLogInUser()->patient->id, ['id' => 'patientRole']) }}
                    @else
                        <div class="col-sm-12 col-lg-12 mb-5">
                            {{ Form::label('Patient', __('messages.appointment.patient') . ':', ['class' => 'form-label required']) }}
                            {{ Form::select('patient_id', $data['patients'], $appointment->patient_id, ['class' => 'io-select2 form-select', 'data-control' => 'select2', 'placeholder' => __('messages.appointment.patient')]) }}
                        </div>
                    @endrole

                    <div class="container appointments-wrapper">
                        @foreach (\App\Models\Appointment::where('relation_id',$appointment->relation_id)->get() as $key => $relation)
                            <div class="appointments-section mb-4" data-index="{{$key}}">
                                <div class="card-body" style="background-color: #eff3f7;border-radius: 12px;">
                                    <div class="row">

                                        {{ Form::hidden('appointments['.$key.'][appointment_id]', $relation->id, ['id' => 'appointment_id']) }}
                                        @if (getLogInUser()->hasRole('patient'))
                                            <div class="col-lg-12 mb-5 col-sm-12">
                                                {{ Form::label('Date', __('messages.appointment.date') . ':', ['class' => 'form-label required']) }}

                                                {{ Form::text('appointments['.$key.'][date]', $relation->date, ['class' => 'form-control date appointmentDate', 'placeholder' => __('messages.appointment.date'), 'required', 'autocomplete' => 'off']) }}
                                            </div>
                                        @endif

                                        <div class="col-lg-6 col-sm-12 mb-5">
                                            {{ Form::label('Service', __('messages.appointment.service') . ':', ['class' => 'form-label']) }}
                                            {{ Form::select('appointments['.$key.'][service_id]', $data['services'], $relation->service_id, ['disabled', 'class' => 'io-select2 form-select appointmentServiceId', 'data-control' => '', 'id' => 'appointmentServiceId', 'placeholder' => __('messages.appointment.service')]) }}
                                        </div>

                                        @role('doctor')
                                            {{ Form::hidden('doctorRole', getLogInUser()->hasRole('doctor'), ['id' => 'doctorRole']) }}

                                            {{ Form::hidden('doctor_id', getLogInUser()->doctor->id, ['id' => 'adminAppointmentDoctorId']) }}
                                        @else
                                            @php
                                                $doctersService = \Illuminate\Support\Facades\DB::table('service_doctor')->where('service_id', $relation->service_id)->pluck('doctor_id');
                                                $docs = \App\Models\Doctor::with('user')->whereIn('id',$doctersService)->get();
                                            @endphp
                                            <div class="col-sm-12 col-lg-6 mb-5">
                                                {{ Form::label('Doctor', __('messages.doctor.doctor') . ':', ['class' => 'form-label']) }}

                                                <select class="io-select2 form-select adminAppointmentDoctorId" id="adminAppointmentDoctorId" data-control="" disabled name="appointments[0][doctor_id]">
                                                    <option value="">Select Doctor</option>
                                                    @foreach ($docs as $doc)
                                                        <option value="{{$doc->id}}" @selected($doc->id == $relation->doctor_id)>{{$doc->user->first_name.' '.$doc->user->last_name}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endrole


                                        <div class="col-lg-12 col-sm-12 mb-5">
                                            {{ Form::label('Charge', __('messages.appointment.charge') . ':', ['class' => 'form-label']) }}
                                            <div class="input-group">
                                                {{ Form::text('appointments['.$key.'][charge]', $relation->charge, ['class' => 'form-control appointment-charge', 'placeholder' => __('messages.doctor.select_date'), 'id' => 'chargeId', 'placeholder' => __('messages.appointment.charge'), 'readonly']) }}
                                                <div class="input-group-text"> <a
                                                        class="fw-bolder text-gray-500 text-decoration-none">{{ getCurrencyIcon() }}</a>
                                                </div>
                                            </div>
                                        </div>

                                        @php $styleCss = 'style'; @endphp

                                        @if (getLogInUser()->hasRole('patient'))
                                            <div class="col-12 form-group">
                                                {{ Form::label('Available Slots', __('messages.appointment.available_slot') . ':', ['class' => 'form-label required']) }}
                                                <div class="mb-0 d-inline-flex align-items-center ms-2">
                                                    <span class="badge bg-danger badge-circle slot-color-dot"></span>
                                                    <span class="ms-2">{{ __('messages.appointment.booked') }}</span>
                                                    <span class="badge bg-success ms-2 badge-circle slot-color-dot"></span>
                                                    <span class="ms-2">{{ __('messages.appointment.available') }}</span>
                                                </div>
                                                <div
                                                    class="fc-timegrid-slot ps-5 pe-5 form-control form-control-solid h-300px overflow-auto">
                                                    {{ Form::hidden('appointments['.$key.'][from_time]', $relation->from_time.' '.$relation->from_time_type, ['class' => 'timeSlot']) }}
                                                    {{ Form::hidden('appointments['.$key.'][to_time]', $relation->to_time.' '.$relation->to_time_type, ['class' => 'toTime']) }}
                                                    <div
                                                        class="text-center d-flex flex-wrap justify-content-center px-3 appointment-slot-data slotData">
                                                    </div>
                                                    <span
                                                        class="justify-content-center d-flex p-20 text-primary no-time-slot">{{ __('messages.appointment.no_slot_found') }}</span>
                                                    <span
                                                        class="justify-content-center d-flex p-20 text-primary d-none doctor-time-over">{{ __('messages.doctors_scheduled_time_ended_for_today__') }}</span>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- <div class="col-12 text-end">
                                            <button type="button" class="btn btn-sm btn-danger remove-appointment d-none">
                                                Remove
                                            </button>
                                        </div> --}}

                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- <div class="col-12 mb-5 mt-5">
                        <button class="btn btn-info w-100" id="addAppointment">Add More</button>
                    </div> --}}

                    {{ Form::hidden('status', \App\Models\Appointment::BOOKED) }}
                    <div class="col-12 mb-5 mt-5">
                        {{ Form::label('Description', __('messages.appointment.description') . ':', ['class' => 'form-label']) }}
                        {{ Form::textarea('description', $appointment->description, ['class' => 'form-control', 'rows' => 10, 'disabled', 'placeholder' => __('messages.appointment.description')]) }}
                    </div>

                    @if (getLogInUser()->hasRole('patient'))
                        <div class="col-lg-6 col-sm-12 mb-5">
                            {{ Form::label('Payment Type', __('messages.appointment.payment_method') . ':', ['class' => 'form-label required']) }}
                            {{ Form::select('payment_type', getAllPaymentStatus(), $appointment->payment_type, ['class' => 'io-select2 form-select', 'data-control' => 'select2', 'placeholder' => __('messages.appointment.payment_method')]) }}
                        </div>
                    @else
                        {{ Form::hidden('payment_type', '1') }}
                    @endif


                    <div class="col-lg-6 col-sm-12 mb-5">
                        {{ Form::label('Total Payable Amount', __('messages.appointment.total_payable_amount') . ':', ['class' => 'form-label required']) }}
                        <div class="input-group">
                            {{ Form::text('payable_amount', $appointment->payable_amount, ['class' => 'form-control', 'placeholder' => __('messages.appointment.total_payable_amount'), 'id' => 'payableAmount', 'required', 'placeholder' => __('messages.appointment.total_payable_amount'), 'readonly']) }}
                            <div class="input-group-text">
                                <a class="fw-bolder text-gray-500 text-decoration-none">{{ getCurrencyIcon() }}</a>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex">
                        {{ Form::button(__('messages.common.save'), ['type' => 'submit', 'class' => 'btn btn-primary me-2 submitAppointmentBtn']) }}
                        &nbsp;
                        <a href="{{ url()->previous() }}" type="reset"
                            class="btn btn-secondary">{{ __('messages.common.discard') }}</a>
                    </div>

                </div>


                {{ Form::close() }}
            </div>
        </div>
    </div>
@endsection
