<div class="d-flex flex-xxl-row flex-column mt-md-0 mt-sm-3">
    <div class="d-flex justify-content-end flex-wrap">
        <div class="d-flex mt-3 align-items-center">
            <a href="{{ route('feedback-appointments.calendar') }}" class="btn btn-icon btn-primary me-2 ms-xl-3"
               data-bs-toggle="tooltip" title="Calendar View">
                <i class="fas fa-calendar-alt fs-3"></i>
            </a>
        </div>
        @if(!getLogInUser()->hasRole('patient'))
        <div class="d-flex align-items-center flex-wrap justify-content-end">
            <div class="ms-3 mt-3">
                <a class="btn btn-primary" href="{{ route('feedback-appointments.create') }}" data-turbo="false">
                    <i class="fas fa-plus me-1"></i> {{ __('Create Feedback Package') }}
                </a>
            </div>
        </div>
        @endif
    </div>
</div>
