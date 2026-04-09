<?php

namespace App\Livewire;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;

#[Lazy]
class PatientShowPageAppointmentTable extends LivewireTableComponent
{
    #[Locked]
    public $patientId;

    protected $model = Appointment::class;

    public bool $showFilterOnHeader = true;

    public array $FilterComponent = ['patients.appointment_filter', Appointment::STATUS];

    protected $listeners = ['refresh' => '$refresh', 'resetPage', 'changeStatusFilter', 'changeDateFilter'];

    public int $statusFilter = Appointment::ALL;

    public string $dateFilter = '';

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('created_at', 'desc')
            ->setQueryStringStatus(false);

        $this->setThAttributes(function (Column $column) {
            if ($column->isField('id')) {
                return [
                    'class' => 'text-center',
                ];
            }

            return [];
        });
    }
    public function placeholder()
    {
          return view('livewire.appointment_skeleton');
    }

    public function builder(): Builder
    {
        $query = Appointment::with(['doctor', 'services'])
            ->where('patient_id', '=', $this->patientId)
            ->whereNotIn('appointments.status', [Appointment::BOOKING_PENDING, Appointment::CANCELLED])
            ->select('appointments.*');

        if (getLogInUser()->hasRole('doctor')) {
            $query = Appointment::with(['doctor.user', 'doctor.reviews', 'services'])
                ->where('patient_id', '=', $this->patientId)
                ->whereNotIn('appointments.status', [Appointment::BOOKING_PENDING, Appointment::CANCELLED])
                ->whereDoctorId(getLogInUser()->doctor->id)
                ->select('appointments.*');
        }

        $query->when($this->statusFilter != '' && $this->statusFilter != Appointment::ALL_STATUS,
            function (Builder $q) {
                if ($this->statusFilter != Appointment::ALL) {
                    $q->where('appointments.status', '=', $this->statusFilter);
                }
            });

        if ($this->dateFilter != '') {
            $timeEntryDate = explode(' - ', $this->dateFilter);
            $startDate = Carbon::parse($timeEntryDate[0])->format('Y-m-d');
            $endDate = Carbon::parse($timeEntryDate[1])->format('Y-m-d');
            $query->whereBetween('appointments.date', [$startDate, $endDate]);
        }

        return $query;
    }

    public function changeStatusFilter($status)
    {
        $this->statusFilter = $status;
        $this->setBuilder($this->builder());
    }

    public function changeDateFilter($date)
    {
        $this->dateFilter = $date;
        $this->setBuilder($this->builder());
    }

    public function columns(): array
    {
        return [
            Column::make(__('messages.appointment.appointment'), 'services.name')->view('patients.components.appointment_service')
                ->sortable()
                ->searchable(
                    function (Builder $query, $direction) {
                        return $query->whereHas('services', function (Builder $q) use ($direction) {
                            $q->where('name', 'like', '%' . $direction . '%');
                        });
                    }
                ),
            Column::make(__('messages.appointment.appointment_at'), 'date')->view('patients.components.appointment_at')
                ->sortable()->searchable(),
            Column::make(__('messages.setting.address'), 'services.address')->view('patients.components.appointment_location')
                ->sortable(),
            Column::make(__('messages.doctor.doctor'), 'doctor.user.first_name')->view('patients.components.doctor')
                ->sortable()
                ->searchable(
                    function (Builder $query, $direction) {
                        return $query->whereHas('doctor.user', function (Builder $q) use ($direction) {
                            $q->whereRaw("TRIM(CONCAT(first_name,' ',last_name,' ')) like ?", ['%'.$direction.'%']);
                        });
                    }
                ),
            Column::make(__('messages.appointment.status'), 'id')
                ->format(function ($value, $row) {
                    return view('patients.components.status')
                        ->with([
                            'row' => $row,
                            'book' => Appointment::BOOKED,
                            'checkIn' => Appointment::CHECK_IN,
                            'checkOut' => Appointment::CHECK_OUT,
                            'cancel' => Appointment::CANCELLED,
                        ]);
                }),
            Column::make(__('messages.common.action'), 'id')->view('patients.components.appointments_action'),
        ];
    }
}