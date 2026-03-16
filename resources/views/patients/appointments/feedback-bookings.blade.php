@extends('layouts.app')
@section('title')
    {{__('Feedback Bookings')}}
@endsection
@section('header_toolbar')
    <div class="container-fluid">
        <div class="d-md-flex align-items-center justify-content-between mb-5">
            <h1 class="mb-0">@yield('title')</h1>
            <div class="text-end mt-4 mt-md-0">
                <a href="{{ route('patients.feedback_appointments.calendar') }}" class="btn btn-icon btn-primary me-2" title="Calendar View">
                    <i class="fas fa-calendar-alt fs-2"></i>
                </a>
            </div>
        </div>
    </div>
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-column">
            @include('flash::message')
            {{Form::hidden('patient_appointment',getLogInUser()->hasRole('patient'),['id' => 'userRole'])}}
            <livewire:patient-feedback-bookings-table/>
        </div>
    </div>
@endsection
