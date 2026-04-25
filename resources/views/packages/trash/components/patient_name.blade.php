<div class="d-flex align-items-center">
    <div class="d-flex flex-column">
        <span class="mb-1 fw-semibold fs-6">
            {{ $row->patient->user->full_name ?? 'Unknown patient' }}
        </span>
        <span class="text-muted fs-7">{{ $row->patient->user->email ?? '' }}</span>
    </div>
</div>
