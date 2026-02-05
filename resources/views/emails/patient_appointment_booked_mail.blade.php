@component('mail::layout')
    {{-- Header --}}
    @slot('header')
        @component('mail::header', ['url' => config('app.url')])
            <img src="{{ asset(getAppLogo()) }}" class="logo" alt="{{ getAppName() }}">
        @endcomponent
    @endslot

    {{-- Body --}}

    We're pleased to support you through this process. Please use the link below and follow the on-screen instructions to
    book the appointments for the academic assessment with our clinicians. Appointments may be scheduled in any order,
    according to clinicians' availability. When booking, please remember to enter the details of the patient who will be
    seen (the child).

    Booking Link: {{$link}}

    Thank you,
    **Bilingual Therapy Team**

    {{-- Footer --}}
    @slot('footer')
        @component('mail::footer')
            © {{ date('Y') }} {{ getAppName() }}.
        @endcomponent
    @endslot
@endcomponent
