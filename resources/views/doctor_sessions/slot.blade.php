@php
    /** @var \App\Models\WeekDay $weekDaySlot */
    $durationOptions = \App\Models\DoctorSession::SESSION_MEETING_TIME;
@endphp
<div class="align-items-center justify-content-between mt-md-0 mt-10 timeSlot">
    <div class="flex-xs-column d-flex align-items-center mb-3 add-slot">
        <div class="d-inline-block me-3">
            {{ Form::select('startTimes['.$day.'][]', $slots, isset($weekDaySlot) ? $weekDaySlot->full_start_time :  $slots[array_key_first($slots)] ,['class' => 'form-control form-control-solid form-select startTimeSlot', 'data-control'=>'select2','disabled'=>false]) }}
        </div>
        <span class="small-border me-3">-</span>
        <div class="d-inline-block me-3">
            {{ Form::select('endTimes['.$day.'][]', $slots, isset($weekDaySlot) ? $weekDaySlot->full_end_time :  end($slots),['class' => 'form-control form-control-solid form-select endTimeSlot', 'data-control'=>'select2','disabled'=>false]) }}
        </div>
        <div class="d-inline-block me-2">
            {{-- DP-02 V9: slot duration is REQUIRED. It drives which service
                 types this block is offered for (1hr service → only matches
                 1hr blocks, 2hr service → only matches 2hr blocks). Blocks
                 saved without a duration are ambiguous and cannot be used. --}}
            {{ Form::select('slotDurations['.$day.'][]', $durationOptions, isset($weekDaySlot) && $weekDaySlot->session_meeting_time ? $weekDaySlot->session_meeting_time : null, [
                'class' => 'form-control form-control-solid form-select slotDurationSelect',
                'data-control' => 'select2',
                'placeholder' => 'Slot duration *',
                'style' => 'min-width: 130px;',
                'required' => 'required',
            ]) }}
        </div>
        <a href="javascript:void(0)" class="deleteBtn mt-5">
            <i class="fa-solid fa-trash ms-5 fs-3 text-danger"></i>
        </a>
    </div>
    <span class="error-msg text-danger"></span>
</div>
