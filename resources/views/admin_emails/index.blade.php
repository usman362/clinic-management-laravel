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
        <div class="d-flex flex-column">
            <div>
                <div class="table-responsive">
                    <table class="table table-striped" id="table-appointments">
                        <thead>
                            <tr>
                                <th scope="col">
                                    Subject
                                </th>
                                <th scope="col" class="text-center">
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody id="appointments-tbody">
                            @php
                                $patientEmail = \App\Models\AdminEmail::where('type', 'patient_email')->first();
                            @endphp
                            @if (isset($patientEmail))
                                <tr class="bg-light rappasoft-striped-row">
                                    <td>
                                        {{ $patientEmail->subject }}
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <a href="{{ route('admin.emails.edit', $patientEmail->id) }}"
                                                class="btn px-1 text-primary fs-3" data-bs-toggle="tooltip" title="Edit">
                                                <svg class="svg-inline--fa fa-pen-to-square" aria-hidden="true"
                                                    focusable="false" data-prefix="fas" data-icon="pen-to-square"
                                                    role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
                                                    data-fa-i2svg="">
                                                    <path fill="currentColor"
                                                        d="M490.3 40.4C512.2 62.27 512.2 97.73 490.3 119.6L460.3 149.7L362.3 51.72L392.4 21.66C414.3-.2135 449.7-.2135 471.6 21.66L490.3 40.4zM172.4 241.7L339.7 74.34L437.7 172.3L270.3 339.6C264.2 345.8 256.7 350.4 248.4 353.2L159.6 382.8C150.1 385.6 141.5 383.4 135 376.1C128.6 370.5 126.4 361 129.2 352.4L158.8 263.6C161.6 255.3 166.2 247.8 172.4 241.7V241.7zM192 63.1C209.7 63.1 224 78.33 224 95.1C224 113.7 209.7 127.1 192 127.1H96C78.33 127.1 64 142.3 64 159.1V416C64 433.7 78.33 448 96 448H352C369.7 448 384 433.7 384 416V319.1C384 302.3 398.3 287.1 416 287.1C433.7 287.1 448 302.3 448 319.1V416C448 469 405 512 352 512H96C42.98 512 0 469 0 416V159.1C0 106.1 42.98 63.1 96 63.1H192z">
                                                    </path>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
