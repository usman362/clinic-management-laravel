<div class="d-flex justify-content-center">
    @if ($row->appointment_type === 'feedback')
        <a href="{{ route('patients.appointments.book-by-token', $row->appointment_unique_id) }}" title="{{ __('messages.common.view') }}"
            data-bs-toggle="tooltip" data-bs-original-title="{{ __('messages.common.view') }}"
            class="btn px-1 text-primary fs-3 user-edit-btn" data-id="{{ $row->id }}">
            <i class="fas fa-edit"></i>
        </a>
    @else
        <a href="{{ route('patients.booking.detail', $row->relation_id) }}" title="{{ __('messages.common.show') }}"
            data-bs-toggle="tooltip" data-bs-original-title="{{ __('messages.common.show') }}"
            class="btn px-1 text-primary fs-3 user-edit-btn" data-id="{{ $row->id }}">
            <i class="fa-solid fa-eye"></i>
        </a>
    @endif
</div>
