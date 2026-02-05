@extends('layouts.app')
@section('title')
    {{ __('Feedback Appointments') }}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')
        <div class="d-flex flex-column">
            <livewire:doctor-panel-feedback-appointment-table/>
        </div>
    </div>
    @include('doctor_appointment.models.doctor-payment-status-model')
@endsection
