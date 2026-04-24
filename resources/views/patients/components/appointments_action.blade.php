@php
    $patientUrl = getLogInUser()->hasRole('doctor') ? route('doctors.patient.detail', $row->patient->id)
                                                    : 'javascript:void(0)';
    $appointmentUrl  = getLogInUser()->hasRole('doctor') ? route('doctors.appointment.detail', $row->id)
                                                         : route('patients.appointment.detail', $row->id);
    $isPatient = getLogInUser()->hasRole('patient');
    $isCancelled = (int) $row->status === (int) \App\Models\Appointment::CANCELLED;
@endphp
<div class="d-flex justify-content-center">
    @if(getLogInUser()->hasRole('clinic_admin'))
        <a href="{{ route('appointments.show', $row->id) }}" title="{{ __('messages.common.show') }}"
           data-bs-toggle="tooltip"
           data-bs-original-title="{{ __('messages.common.show') }}"
           class="btn px-2 text-primary fs-2 user-edit-btn" data-id="{{$row->id}}">
            <i class="fas fa-eye fs-4"></i>
        </a>
        <a href="javascript:void(0)" data-id="{{ $row->id }}" title="{{ __('messages.common.delete') }}"
           class="btn px-2 text-danger fs-2 patient-show-apptment-delete-btn" data-bs-toggle="tooltip"
           data-bs-original-title="{{ __('messages.common.delete') }}">
            <i class="fa-solid fa-trash"></i>
        </a>
    @else
        {{-- CP-09: Rebook icon for patient on cancelled appointments --}}
        @if($isPatient && $isCancelled)
            <a href="{{ route('patients.appointments.book-by-token', $row->appointment_unique_id) }}"
               class="btn px-2 text-success fs-2"
               data-bs-toggle="tooltip"
               data-bs-original-title="{{ __('messages.appointment.rebook_appointment') }}"
               title="{{ __('messages.appointment.rebook_appointment') }}">
                <i class="fas fa-calendar-plus fs-4"></i>
            </a>
        @endif
        <a href="{{ $appointmentUrl }}" title="{{ __('messages.common.show') }}" data-bs-toggle="tooltip"
           data-bs-original-title="{{ __('messages.common.show') }}"
           class="btn px-2 text-primary fs-2 user-edit-btn" data-id="{{$row->id}}">
            <i class="fas fa-eye fs-4"></i>
        </a>
    @endif
</div>
