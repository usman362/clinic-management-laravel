@php
    $package = \App\Models\Package::where('relation_id', $row->relation_id)
        ->where('appointment_type', 'assessment')
        ->first();
    $feedbackStatus = $package ? $package->feedback_status_label : 'N/A';

    // Determine badge color based on status
    if (str_starts_with($feedbackStatus, 'Completed')) {
        $badgeClass = 'bg-success';
        $icon = 'fa-check-circle';
    } elseif (str_starts_with($feedbackStatus, 'Booked')) {
        $badgeClass = 'bg-primary';
        $icon = 'fa-calendar-check';
    } elseif (str_starts_with($feedbackStatus, 'Sent')) {
        $badgeClass = 'bg-warning text-dark';
        $icon = 'fa-paper-plane';
    } else {
        $badgeClass = 'bg-secondary';
        $icon = 'fa-clock';
    }
@endphp
<div class="d-flex justify-content-center">
    <span class="badge {{ $badgeClass }} px-3 py-2">
        <i class="fas {{ $icon }} me-1"></i> {{ $feedbackStatus }}
    </span>
</div>
