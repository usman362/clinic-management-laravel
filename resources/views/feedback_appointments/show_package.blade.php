@extends('layouts.app')
@section('title')
    {{__('Package Details > Feedback Appointments')}}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')
        <div class="d-flex justify-content-between align-items-end mb-5">
            <h1>@yield('title')</h1>
            <a class="btn btn-outline-primary float-end"
               href="{{ route('feedback-appointments.index') }}">{{ __('messages.common.back') }}</a>
        </div>
        <livewire:package-feedback-appointments-table :relationId="$appointment['data']['relation_id']"/>
    </div>
@endsection
