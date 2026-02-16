<div class="d-flex align-items-center">
    <a href="{{route('doctors.show', $row->id)}}">
        <div class="image image-circle image-mini me-3">
            <img src="{{$row->user->profile_image}}" alt="" class="user-img">
        </div>
    </a>
    <div class="d-flex flex-column">
        <div class="d-inline-block align-top">
            <div class="d-inline-block align-self-center d-flex">
                <a href="{{route('doctors.show', $row->id)}}" class="mb-1 text-decoration-none fs-6">
                    {{$row->user->full_name}}
                </a>
            </div>
        </div>
        <span class="fs-6">{{$row->user->email}}</span>
    </div>
</div>
