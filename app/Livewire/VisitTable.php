<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\Lazy;

#[Lazy]
class VisitTable extends LivewireTableComponent
{
    protected $model = Visit::class;

    public bool $showButtonOnHeader = true;

    public string $buttonComponent = 'visits.components.add_button';

    protected $listeners = ['refresh' => '$refresh', 'resetPage'];

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
        $user = getLogInUser();
        $query = Visit::with(['doctor.user', 'patient.user'])->select('visits.*');

        if ($user) {
            if ($user->hasRole('doctor') && $user->doctor) {
                $query->where('doctor_id', $user->doctor->id);
            } elseif ($user->hasRole('patient') && $user->patient) {
                $query->where('patient_id', $user->patient->id);
            } elseif (! $user->hasRole('clinic_admin') && ! $user->hasRole('staff')) {
                $query->whereRaw('1 = 0');
            }
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function placeholder()
    {
         return view('livewire.staff_skeleton');
    }

    public function columns(): array
    {
        return [
            Column::make(__('messages.visit.doctor'), 'doctor.user.first_name')->view('visits.components.doctor')
                ->sortable()->searchable(
                    function (Builder $query, $direction) {
                        return $query->whereHas('doctor.user', function (Builder $q) use ($direction) {
                            $q->whereRaw("TRIM(CONCAT(first_name,' ',last_name,' ')) like ?", ['%'.$direction.'%']);
                        });
                    }
                ),
            Column::make(__('messages.visit.doctor'), 'doctor.doctorUser.email')
                ->hideIf('doctor.doctorUser.email')
                ->searchable(),
            Column::make(__('messages.visit.patient'), 'patient.patientUser.first_name')
                ->view('visits.components.patient')
                ->sortable(function (Builder $query, $direction) {
                    return $query->orderBy(User::select('first_name')->whereColumn('id', 'patient.user_id'), $direction);
                })
                ->searchable(),
            Column::make(__('messages.visit.patient'), 'patient.patientUser.last_name')
                ->hideIf('patient.patientUser.last_name')
                ->searchable(),
            Column::make(__('messages.visit.patient'), 'patient.patientUser.email')
                ->hideIf('patient.patientUser.email')
                ->searchable(),
            Column::make(__('messages.visit.visit_date'), 'visit_date')->view('visits.components.visit_date')
                ->sortable(),
            Column::make(__('messages.common.action'), 'id')->view('visits.components.action'),
        ];
    }
}
