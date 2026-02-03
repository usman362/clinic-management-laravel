<div>
    <div class="row">
        <div class="col-xl-12 col-md-6 dashbord-doctor-card widget mb-5">
            <div class="dashbord-doctor-card-header">
                <h3>{{ __('messages.welcome_back') }}</h3>
                <span>{{ __('messages.dashboard') }}</span>
            </div>
            <div class="dashbord-doctor-card-body d-flex align-items-center justify-content-between">
                <div class="align-items-center py-3 py-sm-1">
                    <div class="image image-circle image-mini">
                        @if (getLogInUser()->hasRole('patient'))
                            <img class="img-fluid doctor-card-img" alt="img-fluid"
                                src="{{ getLogInUser()->patient->profile }}" />
                        @elseif(getLogInUser()->hasRole('doctor'))
                            <img class="img-fluid doctor-card-img" alt="img-fluid"
                                src="{{ getLogInUser()->profile_image }}" />
                        @else
                            <img class="img-fluid doctor-card-img" alt="img-fluid"
                                src="{{ getLogInUser()->profile_image }}" />
                        @endif
                    </div>
                    <div class="doctor-data">
                        <h3 class="text-gray-900">{{ getLogInUser()->full_name }}</h3>
                        <h4 class="mb-0 fw-400 fs-6">{{ getLogInUser()->email }}</h4>
                    </div>
                </div>
                <div class="col-5 ">
                    <div class="">
                        <div class="float-end">
                            <h4 class="fs-6 text-dark fw-bolder">{{ __('messages.user.edit_profile') }}</h4>
                            <a href="{{ route('profile.setting') }}"
                                class="btn btn-primary px-2 py-1">{{ __('messages.doctor.profile') }} →</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
