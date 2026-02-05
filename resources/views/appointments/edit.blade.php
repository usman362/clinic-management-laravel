@extends('layouts.app')
@section('title')
    {{ __('Book Appointment') }}
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

        @if (getLogInUser()->hasRole('patient'))
            <div class="checkout-container">
                <!-- Form Panel -->
                <div class="form-panel">
                    <h1 class="h3 fw-bold mb-4">Complete the steps to book youre appointment</h1>

                    <!-- Progress Bar -->
                    <div class="step-progress-bar">
                        <div class="progress-indicator" style="width: 0%;"></div>
                    </div>

                    <!-- Step Header -->
                    <div class="step-header">
                        <div class="step-item active" id="step1-header">
                            <div class="step-number">1</div>
                            <div class="step-title d-none d-md-block">Your Details</div>
                        </div>
                        <div class="step-item" id="step2-header">
                            <div class="step-number">2</div>
                            <div class="step-title d-none d-md-block">Appointments</div>
                        </div>
                        <div class="step-item" id="step3-header">
                            <div class="step-number">3</div>
                            <div class="step-title d-none d-md-block">Consent</div>
                        </div>
                        <div class="step-item" id="step4-header">
                            <div class="step-number">4</div>
                            <div class="step-title d-none d-md-block">Payment</div>
                        </div>
                        <div class="step-item" id="step5-header">
                            <div class="step-number">5</div>
                            <div class="step-title d-none d-md-block">Confirmation</div>
                        </div>
                    </div>

                    <!-- Form Steps -->
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

                    <!-- STEP 1: CLIENT DETAILS -->
                    <div class="form-step active" id="step1">
                        <h2 class="h2 fw-bold mb-2">Child Details</h2>
                        <p style="color:#6c757d;font-size:13px" class="mb-4 date-time">Enter your personal information</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                    value="{{ @$appointment->patient->user->first_name }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                    value="{{ @$appointment->patient->user->last_name }}" required>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address"
                                    value="{{ @$appointment->patient->address->address1 }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob"
                                    value="{{ @$appointment->patient->user->dob }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax Code (Codice Fiscale)</label>
                                <input type="text" class="form-control" id="tax_code" name="tax_code"
                                    value="{{ @$appointment->patient->address->tax_code }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">School Name</label>
                                <input type="text" class="form-control" id="school_name" name="school_name"
                                    value="{{ @$appointment->patient->address->school_name }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">School Grade</label>
                                <input type="text" class="form-control" id="school_grade" name="school_grade"
                                    value="{{ @$appointment->patient->address->school_grade }}" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" class="next-btn btn btn-primary">Continue</button>
                        </div>
                    </div>

                    <!-- STEP 2: APPOINTMENT SELECTION -->
                    <div class="form-step" id="step2">
                        <h2 class="h2 fw-bold mb-2">Select Appointments</h2>
                        <p style="color:#6c757d;font-size:13px" class="mb-4 date-time">Choose date and time for your
                            appointments</p>

                        <div class="appointments-wrapper">
                            @foreach (\App\Models\Appointment::where('relation_id', $appointment->relation_id)->get() as $key => $relation)
                                <div class="appointments-section mb-4" data-index="{{ $key }}">
                                    <div class="card-body" style="border: 1px solid #eff3f7;border-radius: 12px;">
                                        <div
                                            style="font-size: 12px;border-radius: 12px;background-color: #eaecef;text-align: center;width: 106px;margin-bottom: 14px;">
                                            Appointment {{ $key + 1 }}
                                        </div>
                                        <h4>{{ $relation->services->name }}</h4>
                                        <h5 class="mb-4" style="color:#6c757d">
                                            {{ $relation->services->name . ' ' . ' with ' . ($relation->doctor->user->first_name . ' ' . $relation->doctor->user->last_name) . ' (' . $relation->services->duration . ' min)' }}
                                        </h5>
                                        <div class="row">

                                            {{ Form::hidden('appointments[' . $key . '][appointment_id]', $relation->id, ['id' => 'appointment_id']) }}
                                            @if (getLogInUser()->hasRole('patient'))
                                                <div class="col-lg-12 mb-5 col-sm-12">
                                                    {{ Form::label('Date', __('messages.appointment.date') . ':', ['class' => 'form-label required']) }}

                                                    {{ Form::text('appointments[' . $key . '][date]', $relation->date, ['class' => 'form-control date appointmentDate', 'placeholder' => __('messages.appointment.date'), 'required', 'autocomplete' => 'off']) }}
                                                </div>
                                            @endif

                                            <div class="col-lg-6 col-sm-12 mb-5 d-none">
                                                {{ Form::label('Service', __('messages.appointment.service') . ':', ['class' => 'form-label']) }}
                                                {{ Form::select('appointments[' . $key . '][service_id]', $data['services'], $relation->service_id, ['disabled', 'class' => 'io-select2 form-select appointmentServiceId', 'data-control' => '', 'id' => 'appointmentServiceId', 'placeholder' => __('messages.appointment.service')]) }}
                                            </div>

                                            @php
                                                $doctersService = \Illuminate\Support\Facades\DB::table(
                                                    'service_doctor',
                                                )
                                                    ->where('service_id', $relation->service_id)
                                                    ->pluck('doctor_id');
                                                $docs = \App\Models\Doctor::with('user')
                                                    ->whereIn('id', $doctersService)
                                                    ->get();
                                            @endphp
                                            <div class="col-sm-12 col-lg-6 mb-5 d-none">
                                                {{ Form::label('Doctor', __('messages.doctor.doctor') . ':', ['class' => 'form-label']) }}

                                                <select class="io-select2 form-select adminAppointmentDoctorId"
                                                    id="adminAppointmentDoctorId" data-control="" disabled
                                                    name="appointments[0][doctor_id]">
                                                    <option value="">Select Doctor</option>
                                                    @foreach ($docs as $doc)
                                                        <option value="{{ $doc->id }}" @selected($doc->id == $relation->doctor_id)>
                                                            {{ $doc->user->first_name . ' ' . $doc->user->last_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            @php $styleCss = 'style'; @endphp

                                            @if (getLogInUser()->hasRole('patient'))
                                                <div class="col-12 form-group">
                                                    {{ Form::label('Available Slots', __('messages.appointment.available_slot') . ':', ['class' => 'form-label required']) }}
                                                    <div class="mb-0 d-inline-flex align-items-center ms-2">
                                                        <span class="badge bg-danger badge-circle slot-color-dot"></span>
                                                        <span
                                                            class="ms-2">{{ __('messages.appointment.booked') }}</span>
                                                        <span
                                                            class="badge bg-success ms-2 badge-circle slot-color-dot"></span>
                                                        <span
                                                            class="ms-2">{{ __('messages.appointment.available') }}</span>
                                                    </div>
                                                    <div
                                                        class="fc-timegrid-slot ps-5 pe-5 form-control form-control-solid h-200px overflow-auto">
                                                        {{ Form::hidden('appointments[' . $key . '][from_time]', $relation->from_time . ' ' . $relation->from_time_type, ['class' => 'timeSlot']) }}
                                                        {{ Form::hidden('appointments[' . $key . '][to_time]', $relation->to_time . ' ' . $relation->to_time_type, ['class' => 'toTime']) }}
                                                        <div
                                                            class="text-center d-flex flex-wrap justify-content-center px-3 appointment-slot-data slotData">
                                                        </div>
                                                        <span
                                                            class="justify-content-center d-flex p-10 text-primary no-time-slot">{{ __('messages.appointment.no_slot_found') }}</span>
                                                        <span
                                                            class="justify-content-center d-flex p-10 text-primary d-none doctor-time-over">{{ __('messages.doctors_scheduled_time_ended_for_today__') }}</span>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="mt-4 container"
                                            style="background-color: #eaecef;border-radius: 13px;padding-top: 15px;padding-bottom: 15px;">
                                            <h4>{{ $relation->services->name }}</h4>
                                            <p style="color:#6c757d;font-size:13px" class="m-0">
                                                {{ $relation->services->name . ' ' . ' with ' . ($relation->doctor->user->first_name . ' ' . $relation->doctor->user->last_name) . ' (' . $relation->services->duration . ' min)' }}
                                            </p>
                                            <p style="color:#6c757d;font-size:13px" class="m-0 date-time">Date & Time not
                                                selected</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="prev-btn btn btn-light">Back</button>
                            <button type="button" class="next-btn btn btn-primary">Continue</button>
                        </div>
                    </div>

                    <!-- STEP 3: CONSENT -->
                    <div class="form-step" id="step3">
                        <h2 class="h2 fw-bold mb-4">Informed Consent Form</h2>

                        <p class="text-secondary mb-4">
                            To complete your booking, you must complete the consent form
                        </p>

                        <!-- Embedded JotForm -->
                        <div class="consent-form-wrapper">
                            @foreach ($fullDoctors as $doctor)
                                <h2 class="h2 fw-bold mb-4 mt-4">
                                    {{ $doctor->user->first_name . ' ' . $doctor->user->last_name }}</h2>
                                <iframe src="{{ $doctor->jotform_link }}" width="100%" height="500" frameborder="0"
                                    scrolling="auto">
                                </iframe>
                            @endforeach
                        </div>


                        <div style="background-color: #edf1f5;padding: 16px 12px 2px 24px;border-radius: 13px;">
                            <h5>Consent includes:</h5>
                            <p style="font-size: 13px;color:#6c757d">• Authorization for personal data processing</p>
                            <p style="font-size: 13px;color:#6c757d">• Agreement to terms of service</p>
                            <p style="font-size: 13px;color:#6c757d">• Privacy policy acknowledgment</p>
                            <p style="font-size: 13px;color:#6c757d">• Privacy policy acknowledgment</p>
                        </div>

                        <div class="form-check mt-4 mb-3">
                            <input class="form-check-input" type="checkbox" id="consentConfirmed">
                            <label class="form-check-label" for="consentConfirmed">
                                I contim that I have read and accepted the consent form
                            </label>
                            <p style="font-size: 13px;color:#6c757d">By checking this box, you declare that you have
                                understood
                                and accepted all conditions</p>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="prev-btn btn btn-light">Back</button>
                            <button type="button" class="next-btn btn btn-primary">Continue</button>
                        </div>
                    </div>

                    <!-- STEP 4: PAYMENT ACKNOWLEDGEMENT -->
                    <div class="form-step" id="step4">
                        <h2 class="h2 fw-bold mb-4">Payment Information</h2>

                        <p class="text-secondary mb-4">
                            Please review and acknowledge the payment instructions
                        </p>

                        <div class="container mb-4"
                            style="border: 1px solid #e1e1e1;border-radius: 15px;padding-top: 16px;padding-bottom:24px;">
                            <h2 class="h3 fw-bold mb-4">How Payments Work</h2>

                            {{-- <p class="text-secondary mb-4">
                                Important information about payment processing
                            </p> --}}

                            <div style="background-color: #edf1f5;padding: 16px 12px 2px 24px;border-radius: 13px;">
                                <h5>Each clinician will issue their own invoice via email after the appointment has been completed.</h5>
                                {{-- <p style="font-size: 13px;color:#6c757d">Each clinician will send their own payment
                                    instructions
                                    via email following the confirmation or
                                    completed appointment.</p> --}}

                                <h5>All payments must be completed before scheduling the feedback meeting, during which assessment results are discussed.</h5>
                                {{-- <p style="font-size: 13px;color:#6c757d">You will receive payment details directly from
                                    each
                                    clinician. Please ensure timely payment to
                                    avoid any service delays.</p> --}}

                                <h5>The final written report will be released only after full payment has been received.</h5>
                                {{-- <p style="font-size: 13px;color:#6c757d">Any documentation and reporting will only be
                                    provided
                                    once payment has been received in full.</p> --}}
                            </div>
                        </div>

                        <div class="container mb-4"
                            style="border: 1px solid #e1e1e1;border-radius: 15px;padding-top: 16px;padding-bottom:24px;">
                            <h2 class="h3 fw-bold mb-4">Insurance & Tax Information</h2>

                            {{-- <p class="text-secondary mb-4">
                                Important information about payment processing
                            </p> --}}

                            <div style="background-color: #edf1f5;padding: 16px 12px 2px 24px;border-radius: 13px;">
                                <h5>Medical visits are tax-deductible in accordance with Italian regulations.</h5>
                                {{-- <p style="font-size: 13px;color:#6c757d">Each clinician will send their own payment
                                    instructions
                                    via email following the confirmation or
                                    completed appointment.</p> --}}

                                <h5>Services are often reimbursable by major insurance providers.</h5>
                                {{-- <p style="font-size: 13px;color:#6c757d">You will receive payment details directly from
                                    each
                                    clinician. Please ensure timely payment to
                                    avoid any service delays.</p> --}}

                                <h5>We do not have direct agreements with insurance companies. <br>
                                Please contact your insurance provider directly to confirm coverage and reimbursement details.
                                </h5>
                                {{-- <p style="font-size: 13px;color:#6c757d">Any documentation and reporting will only be
                                    provided
                                    once payment has been received in full.</p> --}}
                            </div>
                        </div>
                        <div class="container-fluid">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="paymentAcknowledged">
                                <label class="form-check-label" for="paymentAcknowledged">
                                    I understand how payments will be processed*
                                </label>
                                <p style="font-size: 13px;color:#6c757d">I acknowledge that each clinician will send their
                                    own
                                    payment instructions</p>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="documentationPolicy">
                                <label class="form-check-label" for="documentationPolicy">
                                    I understand the documentation policy *
                                </label>
                                <p style="font-size: 13px;color:#6c757d">I acknowledge that documentation and reports will
                                    only
                                    be released upon full payment</p>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="prev-btn btn btn-light">Back</button>
                            <button type="button" class="next-btn btn btn-primary">Continue</button>
                        </div>
                    </div>

                    <!-- STEP 5: CONFIRMATION -->
                    <div class="form-step" id="step5">
                        <h2 class="h2 fw-bold mb-4">Booking Confirmation</h2>

                        <div class="container mb-4"
                            style="border: 1px solid #e1e1e1;border-radius: 15px;padding-top: 16px;padding-bottom:24px;">
                            <h2 class="h3 fw-bold mb-4">Child Details</h2>

                            <div style="background-color: #edf1f5;padding: 16px 12px 2px 24px;border-radius: 13px;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p style="font-size: 13px;color:#6c757d">Name:</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="client_name">
                                            {{ @$appointment->patient->user->first_name . ' ' . @$appointment->patient->user->last_name }}
                                        </h5>
                                    </div>
                                    <div class="col-md-6">
                                        <p style="font-size: 13px;color:#6c757d">Address:</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="client_address">{{ @$appointment->patient->address->address1 }}</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <p style="font-size: 13px;color:#6c757d">Date of Birth:</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="client_dob">{{ @$appointment->patient->user->dob }}</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <p style="font-size: 13px;color:#6c757d">Tax Code:</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="client_tax_code">{{ @$appointment->patient->address->tax_code }}</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <p style="font-size: 13px;color:#6c757d">School:</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="client_school">{{ @$appointment->patient->address->school_name }}</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <p style="font-size: 13px;color:#6c757d">Grade:</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="client_grade">{{ @$appointment->patient->address->school_grade }}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="container mb-4"
                            style="border: 1px solid #e1e1e1;border-radius: 15px;padding-top: 16px;padding-bottom:24px;">
                            <h2 class="h3 fw-bold mb-4">Booked Appointments</h2>

                            @foreach (\App\Models\Appointment::where('relation_id', $appointment->relation_id)->get() as $key => $relation)
                                <div class="mt-4 container"
                                    style="background-color: #eaecef;border-radius: 13px;padding-top: 15px;padding-bottom: 15px;">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <div class="mt-2"
                                                style="background-color: black;color: white;font-weight: bold;border-radius: 40px;
                                            width: 44px;height: 44px;text-align: center;padding-top: 11px;">
                                                {{ $key + 1 }}</div>
                                        </div>
                                        <div class="col-md-10">
                                            <h4>{{ $relation->services->name }}</h4>
                                            <p style="color:#6c757d;font-size:13px" class="m-0">
                                                {{ $relation->services->name . ' ' . ' with ' . ($relation->doctor->user->first_name . ' ' . $relation->doctor->user->last_name) . ' (' . $relation->services->duration . ' min)' }}
                                            </p>
                                            <p style="color:#6c757d;font-size:13px" class="m-0 date-time">Date & Time not
                                                selected</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="container mb-4"
                            style="border: 1px solid #e1e1e1;border-radius: 15px;padding-top: 16px;padding-bottom:24px;">
                            <h2 class="h3 fw-bold mb-4">Consent</h2>
                            <p style="color:#6c757d;font-size:13px" class="m-0 date-time"><span
                                    style="font-size: 15px">✓</span> Consent form confirmed</p>
                        </div>

                        <div class="container mb-4"
                            style="border: 1px solid #e1e1e1;border-radius: 15px;padding-top: 16px;padding-bottom:24px;">
                            <h2 class="h3 fw-bold mb-4">Payment</h2>
                            <p style="color:#6c757d;font-size:13px" class="m-0 date-time"><span
                                    style="font-size: 15px">✓</span> Payment instructions acknowledged</p>
                            <p style="color:#6c757d;font-size:13px" class="m-0 date-time"><span
                                    style="font-size: 15px">✓</span> Documentation policy ackngeageo</p>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="prev-btn btn btn-light">Back</button>
                            <button type="submit" class="btn btn-success submitAppointmentBtn">Finish</button>
                        </div>
                    </div>

                    {{ Form::close() }}
                </div>
            </div>
        @else
            <div class="checkout-container">
                <div class="form-panel">
                    <h1 class="h3 fw-bold mb-4">Set up a new booking package for a client</h1>

                    <!-- Progress Bar -->
                    <div class="step-progress-bar">
                        <div class="progress-indicator"></div>
                    </div>

                    <!-- Step Header -->
                    <div class="step-header">
                        <div class="step-item active">
                            <div class="step-number">1</div>
                            <div class="step-title d-none d-md-block">Client</div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">2</div>
                            <div class="step-title d-none d-md-block">Services</div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <div class="step-title d-none d-md-block">Review</div>
                        </div>
                    </div>
                    {{-- {{ Form::hidden(null, false, ['id' => 'appointmentIsEdit']) }} --}}

                    {{ Form::open(['route' => ['appointments.update', $appointment->id], 'id' => 'addAppointmentForm']) }}
                    @method('PUT')
                    {{-- @include('appointments.fields') --}}

                    <!-- STEP 1: CLIENT -->
                    <div class="form-step active">
                        <h3 class="fw-bold mb-3">Select Client & Package Setup</h3>
                        <h5 class="fw-bold mb-3">Choose a client and configure the booking package</h5>
                        <hr>
                        <div class="mb-3">
                            {{ Form::label('Patient', __('messages.appointment.patient') . ':', ['class' => 'form-label required']) }}
                            {{ Form::select('patient_id', $data['patients'], $appointment->patient_id, ['class' => 'io-select2 form-select', 'id' => 'client_id', 'data-control' => 'select2', 'placeholder' => __('messages.appointment.patient')]) }}
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Internal Notes (Admin Only)</label>
                            <textarea class="form-control" name="description" id="internal_notes"
                                placeholder="Add any internal notes about this package...">{{$appointment->description}}</textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="next-btn btn btn-primary">Next</button>
                        </div>
                    </div>

                    <!-- STEP 2: SERVICES -->
                    <div class="form-step">
                        <h3 class="fw-bold mb-3">Add Required Appointments</h3>
                        <h5 class="fw-bold mb-3">Define the appointments the client must complete</h5>
                        <hr>

                        <div class="appointments-wrapper">
                            @foreach (\App\Models\Appointment::where('relation_id',$appointment->relation_id)->get() as $key => $pkg)
                                @php
                                    $doctersService = \Illuminate\Support\Facades\DB::table('service_doctor')->where('service_id', $pkg->service_id)->pluck('doctor_id');
                                    $docs = \App\Models\Doctor::with('user')->whereIn('id',$doctersService)->get();
                                @endphp
                                {{ Form::hidden('appointments[' . $key . '][appointment_id]', $pkg->id, ['id' => 'appointment_id']) }}
                                <div class="appointments-section mb-4" data-index="{{$key}}">
                                    <div class="card-body" style="background-color: #eff3f7;border-radius: 12px;">
                                        <div class="row">
                                            <div class="col-12 text-end">
                                                <button type="button"
                                                    class="btn btn-sm btn-danger remove-appointment {{$key == 0 ? 'd-none' : '' }}">
                                                    <svg class="svg-inline--fa fa-trash" aria-hidden="true" focusable="false"
                                                        data-prefix="fas" data-icon="trash" role="img"
                                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"
                                                        data-fa-i2svg="">
                                                        <path fill="currentColor"
                                                            d="M135.2 17.69C140.6 6.848 151.7 0 163.8 0H284.2C296.3 0 307.4 6.848 312.8 17.69L320 32H416C433.7 32 448 46.33 448 64C448 81.67 433.7 96 416 96H32C14.33 96 0 81.67 0 64C0 46.33 14.33 32 32 32H128L135.2 17.69zM394.8 466.1C393.2 492.3 372.3 512 346.9 512H101.1C75.75 512 54.77 492.3 53.19 466.1L31.1 128H416L394.8 466.1z">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="col-lg-6 col-sm-12 mb-5">
                                                {{ Form::label('Service', __('messages.appointment.service') . ':', ['class' => 'form-label required']) }}
                                                {{ Form::select('appointments['.$key.'][service_id]', $data['services'], $pkg->service_id, ['class' => 'io-select2 form-select appointmentServiceId', 'data-control' => '', 'id' => 'appointmentServiceId', 'placeholder' => __('messages.appointment.service'), 'required']) }}
                                            </div>
                                            <div class="col-sm-12 col-lg-6 mb-5">
                                                {{ Form::label('Doctor', __('messages.doctor.doctor') . ':', ['class' => 'form-label required']) }}
                                                <select class="io-select2 form-select adminAppointmentDoctorId" id="adminAppointmentDoctorId" data-control="" required="" name="appointments[{{$key}}][doctor_id]" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                                                    <option value="">Doctor</option>
                                                    @foreach ($docs as $doc)
                                                        <option value="{{$doc->id}}" @selected($doc->id == $pkg->doctor_id)>{{$doc->user->first_name.' '.$doc->user->last_name}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-sm-12">
                                                <h5>Duration</h5>
                                                <h5 class="duration">Select a service to view duration</h5>
                                                <p>Duration is defined by the service selection.</p>
                                                <select class="form-select duration-details d-none" disabled>
                                                    <option>Service</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" class="btn btn-outline-primary w-100 mt-3" id="addAppointment">
                            + Add Appointment
                        </button>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="prev-btn btn btn-light">Back</button>
                            <button type="button" class="next-btn btn btn-primary">Next</button>
                        </div>
                    </div>

                    <!-- STEP 3: REVIEW -->
                    <div class="form-step">
                        <h3 class="fw-bold mb-3">Review & Activate</h3>
                        <h5 class="fw-bold mb-3">Review the package details before activation</h5>
                        <hr>

                        <div id="review-summary" class="mb-4"></div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="prev-btn btn btn-light">Back</button>
                            <button type="submit" class="btn btn-success submitAppointmentBtn">
                                Activate & Send Link
                            </button>
                        </div>
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        @endif

        <!-- Notification -->
        <div id="notification" class="notification-message"></div>

    </div>
@endsection
