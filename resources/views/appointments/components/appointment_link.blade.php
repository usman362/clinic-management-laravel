<div class="d-flex align-items-center">
    <div class="d-flex flex-column">
        <a href="{{route('patients.appointments.book-by-token', $row->appointment_unique_id)}}" class="mb-1 text-decoration-none fs-6">
            {{route('patients.appointments.book-by-token', $row->appointment_unique_id)}}
        </a>
    </div>
</div>
