@if ($row->appointment_type === 'feedback')
    <span class="badge bg-info text-dark">Feedback</span>
@else
    <span class="badge bg-primary">Assessment</span>
@endif
