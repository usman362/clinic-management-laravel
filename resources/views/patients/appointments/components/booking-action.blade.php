<div class="d-flex justify-content-center">
    <a href="{{ route('patients.booking.detail', $row->relation_id) }}" title="{{ __('messages.common.show') }}"
        data-bs-toggle="tooltip" data-bs-original-title="{{ __('messages.common.show') }}"
        class="btn px-1 text-primary fs-3 user-edit-btn" data-id="{{ $row->id }}">
        <i class="fa-solid fa-eye"></i>
    </a>
</div>
