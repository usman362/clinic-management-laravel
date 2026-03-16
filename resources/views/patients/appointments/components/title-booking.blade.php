@php
    use App\Models\Appointment;

    $isFeedback = ($row->appointment_type === 'feedback');
    $packageAppts = Appointment::where('relation_id', $row->relation_id)
        ->when($isFeedback, fn($q) => $q->where('appointment_type', 'feedback'),
                            fn($q) => $q->where('appointment_type', '!=', 'feedback'))
        ->get();
    $total  = $packageAppts->count();
    $booked = $packageAppts->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN, Appointment::CHECK_OUT])->count();
    $done   = $packageAppts->where('status', Appointment::CHECK_OUT)->count();

    $doctorIds     = $packageAppts->pluck('doctor_id')->unique();
    $patientUserId = optional(optional($row->patient)->user)->id;
    $consentCount  = $patientUserId
        ? \App\Models\Document::where('user_id', $patientUserId)
            ->where('type', 'consent')
            ->whereIn('doctor_id', $doctorIds)
            ->count()
        : 0;
    $consentTotal = $doctorIds->count();

    if ($done === $total && $total > 0) {
        $statusClass = 'bg-success';
        $statusLabel = 'Completed';
    } elseif ($booked > 0) {
        $statusClass = 'bg-primary';
        $statusLabel = 'In Progress';
    } else {
        $statusClass = 'bg-secondary';
        $statusLabel = 'Pending';
    }
@endphp
<div class="d-flex flex-column gap-1">
    <a href="{{ $isFeedback ? route('patients.feedback-booking.detail', $row->relation_id) : route('patients.booking.detail', $row->relation_id) }}" class="mb-1 text-decoration-none fs-6 fw-semibold">
        {{ $isFeedback ? __('Feedback Package') : __('Package') }} #{{ $row->relation_id }}
    </a>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
        <span class="badge bg-light text-dark border" title="Appointments booked">
            <i class="fas fa-calendar-check me-1"></i>{{ $booked }}/{{ $total }} Booked
        </span>
        <span class="badge bg-light text-dark border" title="Completed">
            <i class="fas fa-check-circle me-1"></i>{{ $done }}/{{ $total }} Done
        </span>
        @if ($consentTotal > 0)
        <span class="badge bg-light text-dark border" title="Consent forms signed">
            <i class="fas fa-file-signature me-1 {{ $consentCount >= $consentTotal ? 'text-success' : 'text-warning' }}"></i>
            {{ $consentCount }}/{{ $consentTotal }} Consent
        </span>
        @endif
    </div>
</div>
