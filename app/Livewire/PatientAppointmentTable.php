<?php

namespace App\Livewire;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;

#[Lazy]
class PatientAppointmentTable extends LivewireTableComponent
{
    #[Locked]
    public $doctorId;

    protected $model = Appointment::class;

    public bool $showButtonOnHeader = true;

    protected string $tableName = 'appointments';

    public string $buttonComponent = 'patients.appointments.add_button';

    public bool $showFilterOnHeader = true;

    public array $FilterComponent = [
        'patients.appointments.components.filter',
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
        // CP-09: Include CANCELLED appointments so patient can see & rebook them.
        // Only BOOKING_PENDING (incomplete booking wizard) is excluded.
        // The action blade already shows a rebook button for CANCELLED rows.
        $query = Appointment::with([
            'doctor.user',
            'services',
            'transaction',
            'doctor.reviews',
        ])->where('appointments.appointment_type', 'assessment')
          ->where('patient_id', getLoginUser()->patient->id)
          ->where('appointments.status', '!=', Appointment::BOOKING_PENDING)
          ->select('appointments.*');

        $query->when(
            $this->statusFilter != '' && $this->statusFilter != Appointment::ALL_STATUS,
            function (Builder $q) {
                if ($this->statusFilter != Appointment::ALL) {
                    $q->where('appointments.status', '=', $this->statusFilter);
                }
            }
        );

        if ($this->dateFilter != '') {
            $timeEntryDate = explode(' - ', $this->dateFilter);
            $startDate = Carbon::parse($timeEntryDate[0])->format('Y-m-d');
            $endDate = Carbon::parse($timeEntryDate[1])->format('Y-m-d');
            $query->whereBetween('appointments.date', [$startDate, $endDate]);
        }

        // $query->whereIn('appointments.id', function ($q) {
        //     $q->selectRaw('MAX(appointments.id)')
        //     ->from('appointments')
        //     ->groupBy('appointments.relation_id');
        // });
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
        // CP-18: Restructured columns per client feedback V7.
        // Order: Appointment (service), Date/Time, Location, Doctor, Status, Action
        return [
            // Appointment = service name
            Column::make('Appointment', 'services.name')
                ->view('patients.appointments.components.service_name')
                ->sortable()
                ->searchable(),

            // Date / Time
            Column::make('Date/Time', 'date')
                ->view('patients.appointments.components.appointment_at')
                ->sortable(),

            // Location (from service.address)
            Column::make('Location', 'services.address')
                ->view('patients.appointments.components.location')
                ->searchable(),

            // Doctor
            Column::make(
                __('messages.doctor.doctor'),
                'doctor.user.first_name'
            )->view('patients.appointments.components.doctor')
                ->sortable()
                ->searchable(
                    function (Builder $query, $direction) {
                        return $query->whereHas('doctor.user', function (Builder $q) use ($direction) {
                            $q->whereRaw("TRIM(CONCAT(first_name,' ',last_name,' ')) like ?", ['%'.$direction.'%']);
                        });
                    }
                ),

            // Hidden searchable column for doctor email
            Column::make(__('messages.patient.name'), 'doctor.user.email')
                ->hideIf('doctor.user.email')
                ->searchable(),

            // Status
            Column::make(__('messages.appointment.status'), 'status')->view('patients.appointments.components.status'),

            // Action
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
