@extends('layouts.app')
@section('title')
    {{ __('Create Booking Package') }}
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
        @if (getLogInUser()->hasRole('patient') || getLogInUser()->hasRole('doctor'))
            @if (getLogInUser()->hasRole('patient'))
                <div class="checkout-container">

                    <!-- Form Panel -->
                    <div class="form-panel">
                        <h1 class="h3 fw-bold mb-4">Complete Your Booking</h1>

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
                        {{ Form::open(['route' => 'patients.appointments.store', 'id' => 'addAppointmentForm']) }}

                        <!-- STEP 1: CLIENT DETAILS -->
                        <div class="form-step active" id="step1">
                            <h2 class="h5 fw-bold mb-4">Your Details</h2>

                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone">
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="next-btn btn btn-primary">Continue</button>
                            </div>
                        </div>

                        <!-- STEP 2: APPOINTMENT SELECTION -->
                        <div class="form-step" id="step2">
                            <h2 class="h5 fw-bold mb-4">Select Appointment Times</h2>

                            <!-- Repeated block per required service -->
                            <div class="appointment-item mb-4">
                                <h6 class="fw-bold">Psychological Assessment</h6>
                                <input type="date" class="form-control mb-2">
                                <input type="time" class="form-control">
                            </div>

                            <div class="appointment-item mb-4">
                                <h6 class="fw-bold">Speech Therapy</h6>
                                <input type="date" class="form-control mb-2">
                                <input type="time" class="form-control">
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="prev-btn btn btn-light">Back</button>
                                <button type="button" class="next-btn btn btn-primary">Continue</button>
                            </div>
                        </div>

                        <!-- STEP 3: CONSENT -->
                        <div class="form-step" id="step3">
                            <h2 class="h5 fw-bold mb-4">Consent</h2>

                            <!-- Embedded JotForm -->
                            <div class="mb-4">
                                <iframe src="https://form.jotform.com/YOUR_FORM_ID" width="100%" height="500"
                                    frameborder="0">
                                </iframe>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="consentConfirmed">
                                <label class="form-check-label" for="consentConfirmed">
                                    I confirm that I have read and signed the consent form
                                </label>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="prev-btn btn btn-light">Back</button>
                                <button type="button" class="next-btn btn btn-primary">Continue</button>
                            </div>
                        </div>

                        <!-- STEP 4: PAYMENT ACKNOWLEDGEMENT -->
                        <div class="form-step" id="step4">
                            <h2 class="h5 fw-bold mb-4">Payment Information</h2>

                            <p class="text-secondary mb-4">
                                Payment will be processed according to the terms agreed with the clinic.
                            </p>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="paymentAcknowledged">
                                <label class="form-check-label" for="paymentAcknowledged">
                                    I understand and agree to the payment terms
                                </label>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="prev-btn btn btn-light">Back</button>
                                <button type="button" class="next-btn btn btn-primary">Continue</button>
                            </div>
                        </div>

                        <!-- STEP 5: CONFIRMATION -->
                        <div class="form-step" id="step5">
                            <h2 class="h5 fw-bold mb-4">Booking Confirmed</h2>

                            <div class="text-secondary mb-4">
                                <p>Thank you. Your appointments have been successfully booked.</p>
                                <p>A confirmation email has been sent to you.</p>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                Finish
                            </button>
                        </div>

                        {{ Form::close() }}
                    </div>
                </div>
            @else((getLogInUser()->hasRole('doctor')))
                {{ Form::open(['route' => 'doctors.appointments.store', 'id' => 'addAppointmentForm']) }}
            @endif
        @else(getLogInUser()->hasRole('admin'))
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

                    {{ Form::open(['route' => 'appointments.store', 'id' => 'addAppointmentForm']) }}

                    {{-- @include('appointments.fields') --}}

                    <!-- STEP 1: CLIENT -->
                    <div class="form-step active">
                        <h3 class="fw-bold mb-3">Select Client & Package Setup</h3>
                        <h5 class="fw-bold mb-3">Choose a client and configure the booking package</h5>
                        <hr>
                        <div class="mb-3">
                            {{ Form::label('Patient', __('messages.appointment.patient') . ':', ['class' => 'form-label required']) }}
                            {{ Form::select('patient_id', $data['patients'], null, ['class' => 'io-select2 form-select', 'id' => 'client_id', 'data-control' => 'select2', 'placeholder' => __('messages.appointment.patient')]) }}
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Internal Notes (Admin Only)</label>
                            <textarea class="form-control" name="description" id="internal_notes"
                                placeholder="Add any internal notes about this package..."></textarea>
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
                            <div class="appointments-section mb-4" data-index="0">
                                <div class="card-body" style="background-color: #eff3f7;border-radius: 12px;">
                                    <div class="row">
                                        <div class="col-12 text-end">
                                            <button type="button"
                                                class="btn btn-sm btn-danger remove-appointment d-none">
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
                                            {{ Form::select('appointments[0][service_id]', $data['services'], null, ['class' => 'io-select2 form-select appointmentServiceId', 'data-control' => '', 'id' => 'appointmentServiceId', 'placeholder' => __('messages.appointment.service'), 'required']) }}
                                        </div>
                                        <div class="col-sm-12 col-lg-6 mb-5">
                                            {{ Form::label('Doctor', __('messages.doctor.doctor') . ':', ['class' => 'form-label required']) }}
                                            {{ Form::select('appointments[0][doctor_id]', [], null, ['class' => 'io-select2 form-select adminAppointmentDoctorId', 'id' => 'adminAppointmentDoctorId', 'data-control' => '', 'required', 'placeholder' => __('messages.doctor.doctor')]) }}
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
