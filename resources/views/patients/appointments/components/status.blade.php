<div class="w-120px d-flex align-items-center">
    <span class="badge bg-{{ getBadgeStatusColor($row->status) }} badge-circle slot-color-dot me-2"></span>
    <span class="">{{ $row->status == 5 ? \App\Models\Appointment::STATUS[$row->status] : __('messages.common.'.strtolower(\App\Models\Appointment::STATUS[$row->status]))}}</span>
</div>
