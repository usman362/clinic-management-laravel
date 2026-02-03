<div class="d-flex align-items-center">
    <span class="slot-color-dot badge bg-{{ getBadgeStatusColor($row->status) }} badge-circle me-2"></span>

    <select class="io-select2 form-select appointment-status-change appointment-status"
        data-control="select2"
        data-id="{{ $row->id }}">

        {{-- BOOKING PENDING --}}
        <option value="5"
            {{ $row->status == 5 ? 'selected' : '' }}
            {{ !in_array($row->status, [5]) ? 'disabled' : '' }}>
            {{ \App\Models\Appointment::STATUS[5] }}
        </option>

        {{-- BOOKED --}}
        <option value="1"
            {{ $row->status == 1 ? 'selected' : '' }}
            {{ !in_array($row->status, [5,1]) ? 'disabled' : '' }}>
            {{ __('messages.common.' . strtolower(\App\Models\Appointment::STATUS[1])) }}
        </option>

        {{-- CHECK IN --}}
        <option value="2"
            {{ $row->status == 2 ? 'selected' : '' }}
            {{ !in_array($row->status, [1,2]) ? 'disabled' : '' }}>
            {{ __('messages.common.' . strtolower(\App\Models\Appointment::STATUS[2])) }}
        </option>

        {{-- CHECK OUT --}}
        <option value="3"
            {{ $row->status == 3 ? 'selected' : '' }}
            {{ !in_array($row->status, [2,3]) ? 'disabled' : '' }}>
            {{ __('messages.common.' . strtolower(\App\Models\Appointment::STATUS[3])) }}
        </option>

        {{-- CANCELLED --}}
        <option value="4"
            {{ $row->status == 4 ? 'selected' : '' }}
            {{ in_array($row->status, [3,4]) ? 'disabled' : '' }}>
            {{ __('messages.common.' . strtolower(\App\Models\Appointment::STATUS[4])) }}
        </option>

    </select>
</div>
