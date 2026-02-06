@extends('layouts.app')
@section('title')
    {{ __('Emails') }}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')
        <div class="d-flex justify-content-between align-items-end mb-5">
            <h1>@yield('title')</h1>
            <a class="btn btn-outline-primary float-end" href="{{ url()->previous() }}">{{ __('messages.common.back') }}</a>
        </div>
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{route('admin.emails.update',$email->id)}}">
                    @csrf

                    <input type="hidden" name="type" id="type" value="{{$email->type}}">

                    <div class="row">
                        <!-- Subject -->
                        <div class="col-lg-12">
                            <div class="mb-5">
                                <label for="subject" class="form-label required">Subject:</label>
                                <input type="text" class="form-control" id="subject" name="subject"
                                    placeholder="Subject" value="{{$email->subject}}">
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="col-lg-12">
                            <div class="mb-5">
                                <label for="body" class="form-label required">Body:</label>
                                    <textarea name="body" id="body" class="form-control" rows="10">{{ $email->body }}</textarea>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="col-lg-12">
                            <input type="submit" class="btn btn-primary me-2" value="Save">
                            <a href="{{ url('/admin/services') }}" class="btn btn-secondary">Discard</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        ClassicEditor
            .create(document.querySelector('#body'), {
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
