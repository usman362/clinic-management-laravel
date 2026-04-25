<div class="d-flex justify-content-center">
    <a href="javascript:void(0)" data-id="{{ $row->id }}" data-bs-toggle="tooltip"
        data-bs-original-title="{{ __('Restore') }}"
        class="btn px-1 text-success fs-3 package-restore-btn">
        <i class="fa-solid fa-rotate-left"></i>
    </a>
    <a href="javascript:void(0)" data-id="{{ $row->id }}" data-bs-toggle="tooltip"
        data-bs-original-title="{{ __('Permanent Delete') }}"
        class="btn px-1 text-danger fs-3 package-force-delete-btn">
        <i class="fa-solid fa-trash"></i>
    </a>
</div>
