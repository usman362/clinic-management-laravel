@extends('layouts.app')
@section('title')
    {{ __('Pending Bookings') }}
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-column">
            @include('flash::message')
            {{ Form::hidden('patient_appointment', getLogInUser()->hasRole('patient'), ['id' => 'userRole']) }}
            {{-- <livewire:patient-appointment-table/> --}}

            <div wire:id="c9Jwyw8qbfEaKn3b4c5F">
                <div wire:key="appointments-wrapper" x-data="tableWrapper($wire, )">
                    <div id="datatable-c9Jwyw8qbfEaKn3b4c5F">

                        <div x-data="reorderFunction($wire, 'table-appointments', 'id')">
                            <div>
                                <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->
                            </div>
                            <!--[if BLOCK]><![endif]--> <!--[if BLOCK]><![endif]-->
                            <div wire:offline.class.remove="d-none" class="d-none">
                                <div class="alert alert-danger d-flex align-items-center">
                                    <svg style="width:1.3em;height:1.3em;" xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" data-slot="icon">
                                        <path fill-rule="evenodd"
                                            d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z"
                                            clip-rule="evenodd"></path>
                                    </svg> <span class="d-inline-block ml-2">You are not connected to the internet.</span>
                                </div>
                            </div>
                            <!--[if ENDBLOCK]><![endif]-->
                            <!--[if ENDBLOCK]><![endif]-->

                            <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->

                            <div class="d-flex flex-column ">
                                <!--[if BLOCK]><![endif]-->
                                <div>
                                    <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->
                                </div>
                                <!--[if ENDBLOCK]><![endif]-->
                                <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->

                                <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->

                                <div class="d-md-flex justify-content-between mb-3">
                                    <div class="d-md-flex">
                                        <div x-show="!currentlyReorderingStatus">
                                            <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->
                                        </div>

                                        <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->



                                        <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->

                                        <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->
                                    </div>

                                    <div x-show="!currentlyReorderingStatus" class="d-md-flex">
                                        <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->

                                        <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->

                                        <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->




                                        <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                </div>
                                <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->


                                <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->
                            </div>

                            <div wire:key="appointments-twrap" class="table-responsive">
                                <table wire:key="appointments-table" class="table table-striped" id="table-appointments">
                                    <thead>
                                        <tr class="text-uppercase">
                                            <th class="text-muted mt-1 fw-bold fs-7">{{ __('Sno') }}</th>
                                            <th class="text-muted mt-1 fw-bold fs-7">{{ __('Booking') }}</th>
                                            <th class="text-muted mt-1 fw-bold fs-7 text-center">
                                                {{ __('Action') }}</th>
                                        </tr>
                                    </thead>

                                    <tbody wire:key="appointments-tbody" id="appointments-tbody" class="">
                                        @forelse($appointments as $key => $appointment)
                                            <tr>
                                                <td>
                                                    {{$key + 1}}
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="d-flex flex-column">
                                                            <a href="javascript:void(0)"
                                                                class="text-primary-800 mb-1 fs-6 text-decoration-none">
                                                                New Booking – Action Required
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="mb-1 fs-6 text-muted fw-bold text-center">
                                                    <a href="{{ route('patients.appointments.edit', $appointment->id) }}"
                                                        class="badge bg-light-info">
                                                        <i class="fa fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted fw-bold">
                                                    {{ __('messages.common.no_data_available') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    <!--[if BLOCK]><![endif]--> <!--[if ENDBLOCK]><![endif]-->
                                </table>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
