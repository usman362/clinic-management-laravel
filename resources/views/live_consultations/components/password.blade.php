@php
    $user = auth()->user();
    $canView = $user && (
        $user->hasRole('clinic_admin')
        || $user->hasRole('staff')
        || ($user->hasRole('doctor') && $user->doctor && $user->doctor->id === $row->doctor_id)
        || ($user->hasRole('patient') && $user->patient && $user->patient->id === $row->patient_id)
    );
@endphp
@if ($canView && ! empty($row->password))
    <span class="lc-password" data-password="{{ $row->password }}">
        ••••••
        <a href="#" onclick="event.preventDefault(); var el=this.parentNode; el.firstChild.textContent=el.dataset.password; this.remove();" title="Show password">
            <i class="fa fa-eye"></i>
        </a>
    </span>
@else
    <span class="text-muted">—</span>
@endif
