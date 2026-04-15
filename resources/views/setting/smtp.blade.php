@extends('layouts.app')
@section('title')
    {{ __('messages.settings') }}
@endsection
@section('content')
    <div class="container-fluid">
        {{ Form::open(['route' => 'setting.update', 'files' => true, 'id' => 'generalSettingForm']) }}
        <div class="d-flex flex-column">
            @include('setting.setting_menu')
            {{ Form::hidden('sectionName', $sectionName) }}
            {{ Form::hidden('setting_country_id', false, ['id' => 'settingCountryId']) }}
            {{ Form::hidden('setting_state_id', false, ['id' => 'settingStateId']) }}
            {{ Form::hidden('setting_city_id', false, ['id' => 'settingCityId']) }}
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-center">
                        <h3 class="m-0">{{ __('SMTP Settings') }}
                        </h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-6">
                        {{ Form::label('mail_mailer', 'Mail Mailer:', ['class' => 'col-lg-4 form-label required']) }}
                        <div class="col-lg-8">
                            {{ Form::text('mail_mailer', $setting['mail_mailer'] ?? 'smtp', ['class' => 'form-control', 'placeholder' => 'smtp']) }}
                        </div>
                    </div>

                    <div class="row mb-6">
                        {{ Form::label('mail_host', 'Mail Host:', ['class' => 'col-lg-4 form-label required']) }}
                        <div class="col-lg-8">
                            {{ Form::text('mail_host', $setting['mail_host'] ?? 'smtp.gmail.com', ['class' => 'form-control', 'placeholder' => 'smtp.gmail.com']) }}
                        </div>
                    </div>

                    <div class="row mb-6">
                        {{ Form::label('mail_port', 'Mail Port:', ['class' => 'col-lg-4 form-label required']) }}
                        <div class="col-lg-8">
                            {{ Form::number('mail_port', $setting['mail_port'] ?? 587, ['class' => 'form-control', 'placeholder' => '587']) }}
                        </div>
                    </div>

                    <div class="row mb-6">
                        {{ Form::label('mail_username', 'Mail Username:', ['class' => 'col-lg-4 form-label required']) }}
                        <div class="col-lg-8">
                            {{ Form::text('mail_username', $setting['mail_username'] ?? null, ['class' => 'form-control', 'placeholder' => 'Enter Gmail Address']) }}
                        </div>
                    </div>

                    <div class="row mb-6">
                        {{ Form::label('mail_password', 'Mail Password:', ['class' => 'col-lg-4 form-label required']) }}
                        <div class="col-lg-8">
                            {{ Form::password('mail_password', ['class' => 'form-control', 'placeholder' => 'Enter App Password']) }}
                        </div>
                    </div>

                    <div class="row mb-6">
                        {{ Form::label('mail_encryption', 'Mail Encryption:', ['class' => 'col-lg-4 form-label required']) }}
                        <div class="col-lg-8">
                            {{ Form::text('mail_encryption', $setting['mail_encryption'] ?? 'tls', ['class' => 'form-control', 'placeholder' => 'tls']) }}
                        </div>
                    </div>

                    <div class="row mb-6">
                        {{ Form::label('mail_from_address', 'Mail From Address:', ['class' => 'col-lg-4 form-label required']) }}
                        <div class="col-lg-8">
                            {{ Form::email('mail_from_address', $setting['mail_from_address'] ?? null, ['class' => 'form-control', 'placeholder' => 'example@gmail.com']) }}
                        </div>
                    </div>

                    <div class="row mb-6">
                        {{ Form::label('mail_from_name', 'Mail From Name:', ['class' => 'col-lg-4 form-label required']) }}
                        <div class="col-lg-8">
                            {{ Form::text('mail_from_name', $setting['mail_from_name'] ?? null, ['class' => 'form-control', 'placeholder' => 'Your Company Name']) }}
                        </div>
                    </div>



                    <div class="row mb-6">
                        <div class="col-lg-4">
                            <div class="mb-4">
                                <h3 class="m-0">Google Calendar API Settings</h3>
                            </div>
                            {{ Form::label('google_credentials', 'Upload Google API JSON:', ['class' => 'form-label required']) }}
                        </div>
                        <div class="col-lg-8 mt-4">
                            {{ Form::file('google_credentials', ['class' => 'form-control', 'accept' => '.json']) }}
                            <small class="text-muted">
                                Upload credentials file downloaded from Google Cloud Console.
                            </small>
                        </div>
                    </div>
                    {{-- @php
                        $path = storage_path('app/google/google-calendar-credentials.json');

                        if (file_exists($path)) {
                            dd('File not found: ' . $path);
                        }
                        @endphp
                        <a href="{{storage_path('app/public/' . $setting['google_credentials'])}}" download="">Dowload</a> --}}
                    @if (isset($setting) && $setting['google_credentials'])

                        <div class="row mb-6">
                            <div class="col-lg-4"></div>
                            <div class="col-lg-8">
                                <span class="badge bg-success">
                                    Google credentials file uploaded successfully
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- AP-02 / CP-12: Jotform API Key --}}
                    <div class="row mb-6 mt-6 pt-6 border-top">
                        <div class="col-lg-4">
                            <div class="mb-4">
                                <h3 class="m-0">Jotform API Settings</h3>
                            </div>
                            {{ Form::label('jotform_api_key', 'Jotform API Key:', ['class' => 'form-label']) }}
                        </div>
                        <div class="col-lg-8 mt-4">
                            {{ Form::text('jotform_api_key', $setting['jotform_api_key'] ?? null, [
                                'class' => 'form-control',
                                'placeholder' => 'Paste your Jotform API key here',
                                'autocomplete' => 'off',
                            ]) }}
                            <small class="text-muted d-block mt-2">
                                Used to download the official signed consent PDF from Jotform into the patient's documents.
                                Create a key at
                                <a href="https://www.jotform.com/myaccount/api" target="_blank" rel="noopener">
                                    jotform.com/myaccount/api
                                </a>
                                with <strong>Full Access</strong> permission.
                                Leave blank to fall back to a generated summary PDF.
                            </small>
                            @if(!empty($setting['jotform_api_key']))
                                <span class="badge bg-success mt-2">
                                    <i class="fas fa-check me-1"></i> API key configured
                                </span>
                            @else
                                <span class="badge bg-warning text-dark mt-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Not configured — consent PDFs will use fallback format
                                </span>
                            @endif
                        </div>
                    </div>


                    <div class="row">
                        <!-- Submit Field -->
                        <div class="form-group col-sm-12">
                            {{ Form::submit(__('messages.user.save_changes'), ['class' => 'btn btn-primary', 'id' => 'settingSubmitBtn']) }}
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}
        </div>
    </div>
@endsection
