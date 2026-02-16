<div class="col-md-6 mb-md-10 mb-5">
    <label class="pb-2 fs-4 text-gray-600">{{ __('messages.doctor.specialization')  }}</label>
    <br>
    @foreach($doctorDetailData['data']->specializations as $specialization)
        <span class="badge my-1 me-1 bg-{{ getBadgeColor($loop->index) }}">{{ $specialization->name }}</span>
    @endforeach
</div>
<div class="col-md-6 d-flex flex-column mb-md-10 mb-5">
    <label class="pb-2 fs-4 text-gray-600">{{ __('messages.user.gender')  }}</label>
    <span class="fs-4 text-gray-800">{{ ($doctorDetailData['data']->user->gender == 1) ? __('messages.doctor.male') : __('messages.doctor.female') }}</span>
</div>
<div class="col-md-6 d-flex flex-column mb-md-10 mb-5">
    <label class="pb-2 fs-4 text-gray-600">{{ __('messages.setting.address')  }}</label>
    <span class="fs-4 text-gray-800">{{ !empty($doctorDetailData['data']->user->address->address1) ? $doctorDetailData['data']->user->address->address1 : __('messages.common.n/a') }}</span>
</div>
<div class="col-md-6 d-flex flex-column mb-md-10 mb-5">
    <label class="pb-2 fs-4 text-gray-600">{{ __('messages.patient.registered_on')  }}</label>
    <span class="fs-4 text-gray-800">{{$doctorDetailData['data']->user->created_at->diffForHumans()}}</span>
</div>
<div class="col-md-6 d-flex flex-column mb-md-10 mb-5">
    <label class="pb-2 fs-4 text-gray-600">{{ __('messages.patient.last_updated')  }}</label>
    <span class="fs-4 text-gray-800">{{$doctorDetailData['data']->user->updated_at->diffForHumans()}}</span>
</div>
