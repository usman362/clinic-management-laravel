@if($row->services && $row->services->address)
    <span><i class="fas fa-map-marker-alt text-primary me-1"></i>{{ $row->services->address }}</span>
@else
    <span class="text-muted">-</span>
@endif
