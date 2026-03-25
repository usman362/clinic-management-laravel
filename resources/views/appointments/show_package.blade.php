@extends('layouts.app')
@section('title')
    {{__('Package Details > Appointments')}}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')
        <div class="d-flex justify-content-between align-items-end mb-5">
            <h1>@yield('title')</h1>
            <div class="d-flex gap-2">
                @php
                    $relationId = $appointment['data']['relation_id'] ?? null;
                    $package = $relationId ? \App\Models\Package::where('relation_id', $relationId)->where('appointment_type', 'assessment')->first() : null;
                    $hasFeedback = $package && $package->feedbackPackages()->exists();
                @endphp
                @if($package && !getLogInUser()->hasRole('patient'))
                    @if($hasFeedback)
                        <span class="btn btn-success btn-sm disabled" title="Feedback package already created">
                            <i class="fas fa-check me-1"></i> Feedback Sent
                        </span>
                    @else
                        <a class="btn btn-warning"
                           href="{{ route('feedback.send-from-package', $package->id) }}"
                           onclick="return confirm('This will create a feedback package for this patient with the same doctor(s).\n\nNote: Any appointments that have not been completed will trigger a warning message.\n\nContinue?')">
                            <i class="fas fa-paper-plane me-1"></i> Send Feedback Package
                        </a>
                    @endif
                @endif
                <a class="btn btn-outline-primary"
                   href="{{ url()->previous() }}">{{ __('messages.common.back') }}</a>
            </div>
        </div>
        <livewire:package-appointments-table :relationId="$appointment['data']['relation_id']"/>
    </div>
@endsection
