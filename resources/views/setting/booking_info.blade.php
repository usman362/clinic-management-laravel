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
                        <h3 class="m-0">{{ __('Booking Information') }}
                        </h3>
                    </div>
                </div>
                <div class="card-body">

                    <div class="col-lg-12">
                        <div class="mb-5">
                            <label for="booking_info" class="form-label required">Content:</label>
                            <textarea name="booking_info" id="booking_info" class="form-control" rows="10">{{ $setting['booking_info'] }}</textarea>
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

@push('scripts')
    <script>
        ClassicEditor
            .create(document.querySelector('#booking_info'), {
                toolbar: [
                    'heading',
                    '|',
                    'bold',
                    'italic',
                    'underline',
                    'link',
                    'bulletedList',
                    'numberedList',
                    '|',
                    'blockQuote',
                    'undo',
                    'redo'
                ]
            })
            .catch(error => {
                console.error(error);
            });
    </script>
@endpush
