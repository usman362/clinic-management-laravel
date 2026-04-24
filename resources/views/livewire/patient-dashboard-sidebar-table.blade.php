<div>
    <div class="">
        <div class="row">
            <div class="col-xxl-4 col-xl-4 col-md-6 widget">
                <a href="{{ route('patients.patient-appointments-index') }}" class="text-decoration-none" data-turbo="false">
                    <div
                        class="patient-wiget-appointment-card1 rounded-10 p-xxl-8 px-7 py-10 d-flex align-items-center justify-content-between mb-5">
                        <div class="text-start text-dark">
                            <h3 class="mb-0 fs-6 mb-3 fw-light">{{ __('messages.doctor_dashboard.total_appointments') }}</h3>
                            <h1 class="fs-2-xxl fw-bolder">{{ $totalAppointmentCount }}</h1>
                        </div>
                        <div
                            class="bg-cyan-500 widget-icon patient-widget-icon rounded-50 d-flex align-items-center justify-content-center">
                            <i class="fas fa-file-medical card-icon display-6 text-white"></i>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-xxl-4 col-xl-4 col-md-6 widget">
                <a href="{{ route('patients.patient-appointments-index') }}" class="text-decoration-none" data-turbo="false">
                    <div
                        class="patient-wiget-appointment-card1 rounded-10 p-xxl-8 px-7 py-10 d-flex align-items-center justify-content-between mb-5">
                        <div class="text-start text-dark">
                            <h3 class="mb-0 fs-6 mb-3 fw-light">{{ __('messages.patient_dashboard.pending_appointments') }}</h3>
                            <h1 class="fs-2-xxl fw-bolder">{{ $pendingAppointmentCount }}</h1>
                        </div>
                        <div
                            class="bg-cyan-500 widget-icon patient-widget-icon rounded-50 d-flex align-items-center justify-content-center">
                            <i class="fas fa-book-medical card-icon display-6 text-white hospital-user-dark-mode"></i>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-xxl-4 col-xl-4 col-md-6 widget">
                <a href="{{ route('patients.patient-appointments-index') }}" class="text-decoration-none" data-turbo="false">
                    <div
                        class="patient-wiget-appointment-card1 rounded-10 p-xxl-8 px-7 py-10 d-flex align-items-center justify-content-between mb-5">
                        <div class="text-start text-dark">
                            <h3 class="mb-0 fs-6 mb-3 fw-light">{{ __('messages.patient_dashboard.completed_appointments') }}</h3>
                            <h1 class="fs-2-xxl fw-bolder">{{ $completedAppointmentCount }}</h1>
                        </div>
                        <div
                            class="bg-cyan-500 widget-icon patient-widget-icon rounded-50 d-flex align-items-center justify-content-center">
                            <i class="fas fa-calendar-check card-icon display-6 text-white"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex flex-column justify-content-between">
        <div class="col-12 mb-7 mb-xxl-0 me-2">
            <div class="d-flex border-0 pt-5 mb-2">
                <h3 class="align-items-start flex-column">
                    <span class="fw-bolder fs-3 mb-1">{{ __('Pending Bookings') }}</span>
                </h3>
            </div>

            <div class="table-responsive livewire-table">
                <table class="table table-striped">
                    <thead>
                        <tr class="text-uppercase">
                            <th class="text-muted mt-1 fw-bold fs-7">{{ __('Booking') }}</th>
                            <th class="text-muted mt-1 fw-bold fs-7">{{ __('Status') }}</th>
                            <th class="text-muted mt-1 fw-bold fs-7 text-center">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody id="monthlyReport">
                        @forelse($pendingAppointments as $appointment)
                            @php
                                // CP-19: route to the right page depending on whether the
                                // package still needs action vs. is fully booked.
                                $needsAction = $appointment->has_pending_in_package ?? false;
                                if ($needsAction) {
                                    $rowUrl = route('patients.appointments.book-by-token', $appointment->appointment_unique_id);
                                } elseif ($appointment->appointment_type === 'feedback') {
                                    $rowUrl = route('patients.feedback-booking.detail', $appointment->relation_id);
                                } else {
                                    $rowUrl = route('patients.booking.detail', $appointment->relation_id);
                                }
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex flex-column">
                                            @if($appointment->appointment_type === 'feedback')
                                                <span class="badge bg-purple text-white mb-1" style="background-color: #7c3aed;">Feedback</span>
                                            @else
                                                <span class="badge bg-primary mb-1">Assessment</span>
                                            @endif
                                            <a href="{{ $rowUrl }}" class="text-primary-800 fs-6 text-decoration-none fw-semibold">
                                                @if($needsAction)
                                                    Booking – Action Required
                                                @else
                                                    Booking
                                                @endif
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{-- CP-19: Client requested that raw booking URL be removed.
                                         Replaced with a status badge: Booked vs Action Required. --}}
                                    @if($needsAction)
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Action Required
                                        </span>
                                    @else
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i> Booked
                                        </span>
                                    @endif
                                </td>
                                <td class="mb-1 fs-6 text-muted fw-bold text-center">
                                    <a href="{{ $rowUrl }}" class="badge bg-light-info" title="{{ $needsAction ? 'Continue booking' : 'View booked appointments' }}">
                                        <i class="fa {{ $needsAction ? 'fa-edit' : 'fa-eye' }}"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted fw-bold">
                                    {{ __('messages.common.no_data_available') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-12 mb-7 mb-xxl-0 me-2">
            <div class="d-flex border-0 pt-5 mb-2">
                <h3 class="align-items-start flex-column">
                    <span class="fw-bolder fs-3 mb-1">{{ __('messages.patient_dashboard.today_appointments') }}</span>
                </h3>
            </div>

            <div class="table-responsive livewire-table">
                <table class="table table-striped">
                    <thead>
                        <tr class="text-uppercase">
                            <th class="text-muted mt-1 fw-bold fs-7">{{ __('messages.doctor.doctor') }}</th>
                            <th class="text-muted mt-1 fw-bold fs-7 text-center">
                                {{ __('messages.appointment.time') }}</th>
                        </tr>
                    </thead>
                    <tbody id="monthlyReport">
                        @forelse($todayAppointment as $appointment)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="image image-circle image-mini me-3">
                                            <img src="{{ $appointment->doctor->user->profile_image }}" alt="user"
                                                class="">
                                        </div>
                                        <div class="d-flex flex-column">
                                            <a href="{{ route('patients.appointment.detail', $appointment->id) }}"
                                                class="text-primary-800 mb-1 fs-6 text-decoration-none">
                                                {{ $appointment->doctor->user->fullname }}</a>
                                            <span class="text-muted fw-bold d-block">{{ $appointment->doctor->user->email }}</span>
                                            @if($appointment->appointment_type === 'feedback')
                                                <span class="badge mt-1" style="background-color: #7c3aed; color: #fff; width: fit-content;">Feedback</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="mb-1 fs-6 text-muted fw-bold text-center">
                                    <span class="badge bg-light-info">
                                        {{ $appointment->from_time }} {{ $appointment->from_time_type }}
                                        - {{ $appointment->to_time }} {{ $appointment->to_time_type }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted fw-bold">
                                    {{ __('messages.common.no_data_available') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-12">
            <div class="d-flex border-0 pt-5 mb-2">
                <h3 class="align-items-start flex-column">
                    <span
                        class="fw-bolder fs-3 mb-1">{{ __('messages.patient_dashboard.upcoming_appointments') }}</span>
                </h3>
            </div>

            <div class="table-responsive livewire-table">
                <table class="table table-striped">
                    <thead>
                        <tr class="text-uppercase">
                            <th class="text-muted mt-1 fw-bold fs-7">{{ __('messages.doctor.doctor') }}</th>
                            <th class="text-muted mt-1 fw-bold fs-7 text-center">
                                {{ __('messages.appointment.date') }}</th>
                        </tr>
                    </thead>
                    <tbody id="monthlyReport">
                        @forelse($upcomingAppointment as $appointment)
                            <tr>
                                <td class="w-50px">
                                    <div class="d-flex align-items-center">
                                        <div class="image image-circle image-mini me-3">
                                            <img src="{{ $appointment->doctor->user->profile_image }}" alt="user"
                                                class="user-img">
                                        </div>
                                        <div class="d-flex flex-column">
                                            <a href="{{ route('patients.appointment.detail', $appointment->id) }}"
                                                class="text-primary-800 mb-1 fs-6 text-decoration-none">
                                                {{ $appointment->doctor->user->fullname }}</a>
                                            <span class="text-muted fw-bold d-block">{{ $appointment->doctor->user->email }}</span>
                                            @if($appointment->appointment_type === 'feedback')
                                                <span class="badge mt-1" style="background-color: #7c3aed; color: #fff; width: fit-content;">Feedback</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="mb-1 fs-6 text-muted fw-bold text-center">
                                    <span class="badge bg-light-info">
                                        {{ \Carbon\Carbon::parse($appointment->date)->isoFormat('DD MMM YYYY') }}
                                        {{ $appointment->from_time }} {{ $appointment->from_time_type }}
                                        - {{ $appointment->to_time }} {{ $appointment->to_time_type }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted fw-bold">
                                    {{ __('messages.common.no_data_available') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($pastPendingAppointments->count() > 0)
        <div class="col-12 mb-7 mb-xxl-0 me-2">
            <div class="d-flex border-0 pt-5 mb-2">
                <h3 class="align-items-start flex-column">
                    <span class="fw-bolder fs-3 mb-1">{{ __('Missed Appointments') }}</span>
                </h3>
            </div>

            <div class="table-responsive livewire-table">
                <table class="table table-striped">
                    <thead>
                        <tr class="text-uppercase">
                            <th class="text-muted mt-1 fw-bold fs-7">{{ __('messages.doctor.doctor') }}</th>
                            <th class="text-muted mt-1 fw-bold fs-7 text-center">
                                {{ __('messages.appointment.date') }}</th>
                            <th class="text-muted mt-1 fw-bold fs-7 text-center">
                                {{ __('messages.appointment.status') }}</th>
                        </tr>
                    </thead>
                    <tbody id="monthlyReport">
                        @forelse($pastPendingAppointments as $appointment)
                            <tr>
                                <td class="w-50px">
                                    <div class="d-flex align-items-center">
                                        <div class="image image-circle image-mini me-3">
                                            <img src="{{ $appointment->doctor->user->profile_image }}" alt="user"
                                                class="user-img">
                                        </div>
                                        <div class="d-flex flex-column">
                                            <a href="{{ route('patients.appointment.detail', $appointment->id) }}"
                                                class="text-primary-800 mb-1 fs-6 text-decoration-none">
                                                {{ $appointment->doctor->user->fullname }}</a>
                                            <span class="text-muted fw-bold d-block">{{ $appointment->doctor->user->email }}</span>
                                            @if($appointment->appointment_type === 'feedback')
                                                <span class="badge mt-1" style="background-color: #7c3aed; color: #fff; width: fit-content;">Feedback</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="mb-1 fs-6 text-muted fw-bold text-center">
                                    <span class="badge bg-light-info">
                                        {{ \Carbon\Carbon::parse($appointment->date)->isoFormat('DD MMM YYYY') }}
                                    </span>
                                </td>
                                <td class="mb-1 fs-6 text-muted fw-bold text-center">
                                    @if($appointment->status === 'BOOKED')
                                        <span class="badge bg-warning">{{ __('Booked') }}</span>
                                    @else
                                        <span class="badge bg-info">{{ __('Checked In') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted fw-bold">
                                    {{ __('messages.common.no_data_available') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
