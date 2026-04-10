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
                        @if(session('feedback_needs_confirm') == $package->id)
                            {{-- AP-05.3: Doctor clicked "Send" but some appointments incomplete — show confirm button --}}
                            <a class="btn btn-danger"
                               href="{{ route('feedback.send-from-package', ['packageId' => $package->id, 'confirm' => 1]) }}"
                               onclick="return confirm('Not all appointments are completed.\n\nAre you sure you want to create the feedback package anyway?')">
                                <i class="fas fa-exclamation-triangle me-1"></i> Confirm: Create Feedback Package
                            </a>
                        @endif
                        <a class="btn btn-warning"
                           href="{{ route('feedback.send-from-package', $package->id) }}"
                           onclick="return confirm('This will create a feedback package for this patient with the same doctor(s).\n\nContinue?')">
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
