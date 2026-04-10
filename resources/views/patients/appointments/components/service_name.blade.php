<span class="fw-semibold">{{ $row->services->name ?? 'N/A' }}</span>
@if($row->services && $row->services->short_description)
    <br><small class="text-muted">{{ Str::limit($row->services->short_description, 50) }}</small>
@endif
