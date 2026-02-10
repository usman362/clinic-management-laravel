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
                        <div class="step-title d-none d-md-block">Booking Information</div>
                    </div>
                    <div class="step-item" id="step5-header">
                        <div class="step-number">5</div>
                        <div class="step-title d-none d-md-block">Payment</div>
                    </div>
                    <div class="step-item" id="step6-header">
                        <div class="step-number">6</div>
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
                                            $doctersService = \Illuminate\Support\Facades\DB::table('service_doctor')
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
                                                        {{ $doc->user->first_name . ' ' . $doc->user->last_name }}</option>
                                                @endforeach
                                            </select>
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
                        <iframe src="https://form.jotform.com/260177736956066" width="100%" height="600"
                            frameborder="0" scrolling="auto">
                        </iframe>
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
                        <p style="font-size: 13px;color:#6c757d">By checking this box, you declare that you have understood
                            and accepted all conditions</p>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="prev-btn btn btn-light">Back</button>
                        <button type="button" class="next-btn btn btn-primary">Continue</button>
                    </div>
                </div>

                <!-- STEP 4: BOOKING INFORMATION -->
                <div class="form-step" id="step4">

                    <h2 class="h2 fw-bold mb-3">Booking Information</h2>
                    <p class="text-secondary mb-4">
                        Please read the information below carefully before continuing
                    </p>

                    <!-- Scrollable Content -->
                    <div class="container mb-4"
                        style="border:1px solid #e1e1e1;border-radius:15px;
                                    max-height:320px;overflow-y:auto;
                                    padding:20px;background:#f8f9fb;">

                        <h5 class="fw-bold mb-3">How the Assessment Works</h5>

                        <p>
                            In accordance with Italian regulations (Law 170/2010), the assessment of learning
                            difficulties is multidisciplinary and involves the participation of three professionals:
                            a psychologist, a speech and language therapist, and a child neuropsychiatrist (NPI).
                        </p>

                        <p>
                            Appointments with the professionals do not need to follow a specific order; however,
                            all of them must be completed.
                        </p>

                        <ul>
                            <li>1 appointment with the Child Neuropsychiatrist (NPI) (≈ 45 minutes)</li>
                            <li>2 hours of test-based assessment (cognitive abilities)</li>
                            <li>2 hours of test-based assessment (academic/learning skills)</li>
                            <li>1 hour of test-based assessment (language skills)</li>
                            <li>1 hour feedback meeting</li>
                        </ul>

                        <p>
                            If required, additional assessments may be included (attention, neuropsychological,
                            emotional evaluations). Timing will be discussed with professionals.
                        </p>

                        <h6 class="fw-bold mt-4">Specific Guidelines for Appointments</h6>

                        <p>
                            The Child Neuropsychiatrist appointment focuses on medical, family, and school history.
                            Families may attend with or without the child.
                        </p>

                        <p>
                            Based on experience, we recommend being open and honest with children about the
                            reasons for assessment. It is not recommended for children to wait alone during
                            sensitive discussions.
                        </p>

                        <p>
                            Online appointments are available only in specific, pre-agreed cases and are usually
                            suitable for parent meetings rather than direct child assessment.
                        </p>

                        <p>
                            Cognitive assessments are conducted by a psychologist.
                            Academic and language assessments by a speech & language therapist or psychologist.
                        </p>

                        <h6 class="fw-bold mt-4">Feedback & Final Report</h6>

                        <p>
                            After completion, professionals agree on a final diagnosis and meet with parents
                            to explain results and recommendations.
                        </p>

                        <p>
                            A written report (English or other language if required) will be provided within one month
                            after the feedback meeting, once all invoices are paid.
                        </p>

                        <h6 class="fw-bold mt-4">Fees</h6>
                        <ul>
                            <li>Child Neuropsychiatrist visit: €150</li>
                            <li>Test administration: €145 per hour</li>
                            <li>Feedback meeting: €145</li>
                            <li>Final report: €145</li>
                        </ul>

                        <p>
                            Payment is made by bank transfer directly to each professional.
                            A regular invoice will be issued after each appointment.
                        </p>

                        <h6 class="fw-bold mt-4">Required Documentation</h6>
                        <p>
                            A consent form must be signed for each professional. In joint custody cases,
                            signatures from both parents are required.
                        </p>

                        <p class="mt-3">
                            Contact: <strong>bilingualtherapymilan@gmail.com</strong>
                        </p>
                    </div>

                    <!-- Required Checkbox -->
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="assessmentInfoAccepted">
                        <label class="form-check-label fw-semibold" for="assessmentInfoAccepted">
                            I have read and understood <strong>How the Assessment Works</strong>
                        </label>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="prev-btn btn btn-light">Back</button>
                        <button type="button" class="next-btn btn btn-primary">Continue</button>
                    </div>

                </div>

                <!-- STEP 5: PAYMENT ACKNOWLEDGEMENT -->
                <div class="form-step" id="step5">
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
                            <h5>Each clinician will issue their own invoice via email after the appointment has been
                                completed.</h5>
                            {{-- <p style="font-size: 13px;color:#6c757d">Each clinician will send their own payment
                                    instructions
                                    via email following the confirmation or
                                    completed appointment.</p> --}}

                            <h5>All payments must be completed before scheduling the feedback meeting, during which
                                assessment results are discussed.</h5>
                            {{-- <p style="font-size: 13px;color:#6c757d">You will receive payment details directly from
                                    each
                                    clinician. Please ensure timely payment to
                                    avoid any service delays.</p> --}}

                            <h5>The final written report will be released only after full payment has been received.
                            </h5>
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
                                Please contact your insurance provider directly to confirm coverage and reimbursement
                                details.
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

                <!-- STEP 6: CONFIRMATION -->
                <div class="form-step" id="step6">
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

        <!-- Notification -->
        <div id="notification" class="notification-message"></div>

    </div>
@endsection

@push('scripts')
    @role('patient')
        <script src="{{ asset('assets/js/booking.js') }}"></script>
    @else
        <script src="{{ asset('assets/js/admin-booking.js') }}"></script>
    @endrole
@endpush
