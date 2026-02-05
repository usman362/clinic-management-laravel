@extends('layouts.app')
@section('title')
    {{__('Feedback Bookings')}}
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
