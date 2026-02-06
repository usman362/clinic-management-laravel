@extends('layouts.app')
@section('title')
    {{ __('Google Calendar Events') }}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')
        <div class="d-flex justify-content-between align-items-end mb-5">
            <h1>@yield('title')</h1>
            <a class="btn btn-outline-primary float-end" href="{{ url()->previous() }}">{{ __('messages.common.back') }}</a>
        </div>
        <div class="d-flex flex-column">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $event)
                            <tr>
                                <td>{{ $event->getSummary() ?? '-' }}</td>
                                {{-- Start Time --}}
                                <td>
                                    @php
                                        $start = $event->getStart()->getDateTime() ?? $event->getStart()->getDate();
                                    @endphp
                                    {{ \Carbon\Carbon::parse($start)->format('d M, Y - h:i A') }}
                                </td>

                                {{-- End Time --}}
                                <td>
                                    @php
                                        $end = $event->getEnd()->getDateTime() ?? $event->getEnd()->getDate();
                                    @endphp
                                    {{ \Carbon\Carbon::parse($end)->format('d M, Y - h:i A') }}
                                </td>
                                <td>{{ $event->getDescription() ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center">No upcoming appointments found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
