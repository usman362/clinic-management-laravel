<?php

namespace App\Livewire;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\Lazy;

#[Lazy]
class PatientFeedbackBookingAppointmentTable extends LivewireTableComponent
{
    public $doctorId;

    public $relationId;

    protected $model = Appointment::class;

    public bool $showButtonOnHeader = false;

    protected string $tableName = 'appointments';

    public string $buttonComponent = 'patients.appointments.add_button';

    public bool $showFilterOnHeader = true;

    public array $FilterComponent = [
        'patients.appointments.components.feedback-filter',
        Appointment::PAYMENT_TYPE_ALL,
        Appointment::STATUS,
    ];

    protected $listeners = [
        'refresh' => '$refresh',
        'resetPage',
        'changeStatusFilter',
        'changeDateFilter',
        'changePaymentTypeFilter',
        'changePaymentStatusFilter',
    ];

    public int $statusFilter = Appointment::ALL;

    public string $paymentTypeFilter = '';

    public string $paymentStatusFilter = '';

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

    public function builder(): Builder
    {
        $query = Appointment::with([
            'doctor.user',
            'services',
            'transaction',
            'doctor.reviews',
        ])->where('patient_id', getLoginUser()->patient->id)
        ->where('appointments.status', '!=', 5)
        ->where('appointments.appointment_type', 'feedback')
        ->where('relation_id', $this->relationId)->select('appointments.*');

        $query->when(
            $this->statusFilter != '' && $this->statusFilter != Appointment::ALL_STATUS,
            function (Builder $q) {
                if ($this->statusFilter != Appointment::ALL) {
                    $q->where('appointments.status', '=', $this->statusFilter);
                }
            }
        );

        return $query->select('appointments.*');
    }

    public function placeholder()
    {
        return view('livewire.appointment_skeleton');
    }

    public function changeStatusFilter($status)
    {
        $this->statusFilter = $status;
        $this->setBuilder($this->builder());
    }

    public function changePaymentTypeFilter($type)
    {
        $this->paymentTypeFilter = $type;
        $this->setBuilder($this->builder());
    }

    public function changeDateFilter($date)
    {
        $this->dateFilter = $date;
        $this->setBuilder($this->builder());
        $this->resetPagination();
    }

    public function columns(): array
    {
        return [
            Column::make(
                __('messages.doctor.doctor'),
                'doctor.user.first_name'
            )->view('patients.appointments.components.doctor')
                ->sortable()
                ->searchable(
                    function (Builder $query, $direction) {
                        return $query->whereHas('doctor.user', function (Builder $q) use ($direction) {
                            $q->whereRaw("TRIM(CONCAT(first_name,' ',last_name,' ')) like '%{$direction}%'");
                        });
                    }
                ),
            Column::make(__('messages.appointment.patient'), 'patient.patientUser.first_name')
                ->view('appointments.components.patient_name')
                ->sortable(function (Builder $query, $direction) {
                    return $query->orderBy(\App\Models\User::select('first_name')->whereColumn('id', 'patient.user_id'), $direction);
                })
                ->searchable(),
            Column::make(__('messages.patient.name'), 'doctor.user.email')
                ->hideIf('doctor.user.email')
                ->searchable(),
            Column::make(__('messages.appointment.status'), 'status')->view('patients.appointments.components.status'),
            Column::make(__('messages.common.action'), 'id')
                ->format(function ($value, $row) {
                    return view('patients.appointments.components.action')
                        ->with([
                            'row' => $row,
                            'checkOut' => Appointment::CHECK_OUT,
                            'cancel' => Appointment::CANCELLED,
                        ]);
                }),
        ];
    }

    public function resetPagination()
    {
        $this->resetPage('appointmentsPage');
    }
}
