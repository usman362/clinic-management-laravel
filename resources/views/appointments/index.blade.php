@extends('layouts.app')
@section('title')
    {{__('Packages')}}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')
        <div class="d-flex justify-content-between align-items-end mb-5">
            <h1>@yield('title')</h1>
            <a class="btn btn-outline-primary float-end"
               href="{{ url()->previous() }}">{{ __('messages.common.back') }}</a>
        </div>
        <div class="d-flex flex-column">
            {{Form::hidden('patientRole',getLogInUser()->hasRole('patient'),['id' => 'patientRole'])}}
            <livewire:appointment-table/>
            @include('appointments.models.patient-payment-model')
            @include('appointments.models.change-payment-status-model')
        </div>
    </div>
@endsection
