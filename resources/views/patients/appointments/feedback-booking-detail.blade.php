@extends('layouts.app')
@section('title')
    {{__('Feedback Appointments')}}
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-column">
            @include('flash::message')
            {{Form::hidden('patient_appointment',getLogInUser()->hasRole('patient'),['id' => 'userRole'])}}
            <livewire:patient-feedback-booking-appointment-table :relationId="$id" />
        </div>
    </div>
@endsection
