<?php

namespace App\Livewire;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\Lazy;

#[Lazy]
class PatientConfirmBookingsTable extends LivewireTableComponent
{
    public $doctorId;

    protected $model = Appointment::class;

    public bool $showButtonOnHeader = false;

    protected string $tableName = 'appointments';

    public string $buttonComponent = 'patients.appointments.add_button';

    public bool $showFilterOnHeader = false;

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
        $query = Appointment::with([
            'doctor.user',
            'services',
            'transaction',
            'doctor.reviews',
        ])->where('appointments.status','!=',5)->where('patient_id', getLoginUser()->patient->id)->select('appointments.*');

        $query->whereIn('appointments.id', function ($q) {
            $q->selectRaw('MAX(appointments.id)')
            ->from('appointments')
            ->groupBy('appointments.relation_id');
        });
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
                __('Booking'),
                'doctor.user.first_name'
            )->view('patients.appointments.components.title-booking'),
            Column::make(__('messages.common.action'), 'id')
                ->format(function ($value, $row) {
                    return view('patients.appointments.components.booking-action')
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
