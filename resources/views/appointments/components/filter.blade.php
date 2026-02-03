{{-- <div class="row ms-auto">
    <div class="col-md-12 mt-3">
        <div class="d-flex align-items-center">
            <span class="badge bg-primary badge-circle me-1 slot-color-dot"></span>
            <span class="me-4">{{ __('messages.common.' . strtolower(\App\Models\Appointment::STATUS[1])) }}</span>
            <span class="badge bg-success badge-circle me-1 slot-color-dot"></span>
            <span class="me-4">{{ __('messages.common.' . strtolower(\App\Models\Appointment::STATUS[2])) }}</span>
            <span class="badge bg-warning badge-circle me-1 slot-color-dot"></span>
            <span class="me-4">{{ __('messages.common.' . strtolower(\App\Models\Appointment::STATUS[3])) }}</span>
            <span class="badge bg-danger badge-circle me-1 slot-color-dot"></span>
            <span class="me-4">{{ __('messages.common.' . strtolower(\App\Models\Appointment::STATUS[4])) }}</span>
        </div>
    </div>
</div> --}}
<div class="d-flex flex-xxl-row flex-column  mt-md-0 mt-sm-3">


    <div class="d-flex justify-content-end flex-wrap">
        <div class="d-flex mt-3 align-items-center">
            <a href="{{ route('appointments.calendar') }}" class="btn btn-icon btn-primary me-2 ms-xl-3">
                <i class="fas fa-calendar-alt fs-3"></i>
            </a>
        </div>
       <div class="d-flex align-items-center flex-wrap justify-content-end">
        {{-- <div class="mt-3 ms-3">
            <input type="text" class="form-control form-control-solid custom-width px-3 flatpickr-input"
            placeholder="{{ __('messages.common.pick_date_range') }}" id="appointmentDateFilter" />
        </div> --}}
        <div class="ms-3 mt-3">
            <a type="button" class="btn btn-primary" href="{{ route('appointments.create') }}" data-turbo="false">
                {{ __('Create Package') }}
            </a>
        </div>
       </div>
    </div>
</div>
