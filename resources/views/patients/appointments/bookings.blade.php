@extends('layouts.app')
@section('title')
    {{__('messages.appointment.appointments')}}
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-column">
            @include('flash::message')
            {{Form::hidden('patient_appointment',getLogInUser()->hasRole('patient'),['id' => 'userRole'])}}
            <livewire:patient-booking-appointment-table :relationId="$id" />
        </div>
    </div>
    @include('appointments.models.patient-payment-model')
    @include('generate_patient_smart_cards/components/show_card')
@endsection
