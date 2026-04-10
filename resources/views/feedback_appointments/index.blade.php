@extends('layouts.app')
@section('title')
    {{ __('Feedback Packages') }}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')

        {{-- Instructions panel --}}
        @if(!getLogInUser()->hasRole('patient'))
        {{-- AP-05: data-turbo-permanent prevents Turbo from caching the dismissed state --}}
        <div class="bg-info bg-opacity-10 border border-info rounded p-4 position-relative mb-4"
             role="alert" id="feedbackInstructions" data-turbo-permanent>
            <h5 class="alert-heading fw-bold mb-2"><i class="fas fa-info-circle me-2"></i>What is a Feedback Package?</h5>
            <p class="mb-2">
                A <strong>feedback package</strong> is a follow-up appointment set created after an assessment package has been completed.
                It allows the patient to book a feedback meeting with their doctor(s) to review results and next steps.
            </p>
            <hr>
            <p class="mb-2"><strong>How to create a feedback package:</strong></p>
            <ol class="mb-2">
                <li>Go to <a href="{{ route('appointments.index') }}"><strong>Packages</strong></a> and open the assessment package you want to follow up on.</li>
                <li>Click the <strong>"Send Feedback Package"</strong> button on the package details page.</li>
                <li>The system will create a feedback package linked to that assessment, pre-filled with the same patient and doctor(s).</li>
                <li>The patient receives a booking link to select their preferred feedback appointment slot.</li>
            </ol>
            <p class="mb-0"><small>Alternatively, click <strong>"Create Feedback Package"</strong> below to create one manually.</small></p>
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3" aria-label="Close"
                    onclick="this.closest('#feedbackInstructions').style.display='none'"></button>
        </div>
        @endif

        <div class="d-flex flex-column">
            {{Form::hidden('patientRole',getLogInUser()->hasRole('patient'),['id' => 'patientRole'])}}
            <livewire:feeback-appointment-table/>
            @include('appointments.models.patient-payment-model')
            @include('appointments.models.change-payment-status-model')
        </div>
    </div>
@endsection
