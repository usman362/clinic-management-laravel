<div class="d-flex justify-content-center">
    @if ($row->appointment_type === 'feedback')
        @php
            $allBooked = !\App\Models\Appointment::where('relation_id', $row->relation_id)
                ->where('appointment_type', 'feedback')
                ->where('status', \App\Models\Appointment::BOOKING_PENDING)
                ->exists();
        @endphp
        @if ($allBooked)
            <a href="{{ route('patients.feedback-booking.detail', $row->relation_id) }}" title="{{ __('messages.common.show') }}"
                data-bs-toggle="tooltip" data-bs-original-title="{{ __('messages.common.show') }}"
                class="btn px-1 text-primary fs-3 user-edit-btn" data-id="{{ $row->id }}">
                <i class="fa-solid fa-eye"></i>
            </a>
        @else
            <a href="{{ route('patients.appointments.book-by-token', $row->appointment_unique_id) }}" title="{{ __('messages.common.edit') }}"
                data-bs-toggle="tooltip" data-bs-original-title="{{ __('messages.common.edit') }}"
                class="btn px-1 text-primary fs-3 user-edit-btn" data-id="{{ $row->id }}">
                <i class="fas fa-edit"></i>
            </a>
        @endif
    @else
        <a href="{{ route('patients.booking.detail', $row->relation_id) }}" title="{{ __('messages.common.show') }}"
            data-bs-toggle="tooltip" data-bs-original-title="{{ __('messages.common.show') }}"
            class="btn px-1 text-primary fs-3 user-edit-btn" data-id="{{ $row->id }}">
            <i class="fa-solid fa-eye"></i>
        </a>
    @endif
</div>
