@extends('fronts.layouts.app')
@section('front-title')
    Consent Form Error
@endsection
@section('front-content')
    <div class="page-content bg-white">
        <section class="section-area section-sp2 error-consent p-t-100">
            <div class="container">
                <div class="inner-content">
                    <div class="alert alert-danger" role="alert">
                        <h4 class="alert-heading">Consent Form Submission Error</h4>
                        <p>{{ $message ?? 'An error occurred while processing your consent form submission.' }}</p>
                    </div>
                    <p class="mt-3">Please try the following:</p>
                    <ul class="mt-2">
                        <li>Go back to your booking page and try submitting the consent form again</li>
                        <li>Make sure you have completed all required fields in the consent form</li>
                        <li>If the problem persists, contact support for assistance</li>
                    </ul>
                    <div class="clearfix mt-4">
                        <a href="{{ url()->previous() }}" class="btn btn-primary m-r5">Back to Booking</a>
                        <a href="{{ route('medical') }}" class="btn btn-secondary">Home</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
