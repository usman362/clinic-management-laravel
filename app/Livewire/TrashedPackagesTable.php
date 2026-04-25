<?php

namespace App\Livewire;

use App\Models\Package;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Lazy;
use Rappasoft\LaravelLivewireTables\Views\Column;

/**
 * AP-16: Lists soft-deleted packages (assessment + feedback) so admin
 * can restore them or permanently delete them.
 */
#[Lazy]
class TrashedPackagesTable extends LivewireTableComponent
{
    protected $model = Package::class;

    protected string $tableName = 'trashed_packages';

    public bool $showFilterOnHeader = false;
    public bool $showButtonOnHeader = false;

    protected $listeners = [
        'refresh' => '$refresh',
        'resetPage',
    ];

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('deleted_at', 'desc')
            ->setQueryStringStatus(false);
    }

    public function placeholder()
    {
        return view('livewire.appointment_skeleton');
    }

    public function builder(): Builder
    {
        // AP-16 (revised): appointments are NOT soft-deleted (they're cancelled
        // in place), so a plain withCount() works — no need to bypass any
        // SoftDeletes scope on the appointments table.
        return Package::onlyTrashed()
            ->with(['patient.user', 'creator'])
            ->withCount('appointments');
    }

    public function columns(): array
    {
        return [
            Column::make('Patient', 'patient.user.first_name')
                ->view('packages.trash.components.patient_name')
                ->sortable(function (Builder $query, $direction) {
                    return $query->orderBy(
                        \App\Models\User::select('first_name')->whereColumn('id', 'patient.user_id'),
                        $direction
                    );
                })
                ->searchable(function (Builder $query, $term) {
                    return $query->whereHas('patient.user', function (Builder $q) use ($term) {
                        $q->whereRaw("TRIM(CONCAT(first_name,' ',last_name,' ')) like ?", ['%' . $term . '%']);
                    });
                }),
            Column::make('Type', 'appointment_type')
                ->view('packages.trash.components.type_badge')
                ->sortable(),
            // The rappasoft livewire-tables library qualifies every column
            // field with the model table (`packages.appointments_count`), so
            // pointing the column at the withCount() subquery alias produces
            // a "Unknown column" SQL error. Use a label closure instead — it
            // pulls the value from the eloquent model after the row loads
            // and never participates in the SELECT clause.
            Column::make('Appointments', 'id')
                ->label(fn ($row) => $row->appointments_count ?? 0),
            Column::make('Deleted At', 'deleted_at')
                ->view('packages.trash.components.deleted_at')
                ->sortable(),
            Column::make('Actions', 'id')
                ->view('packages.trash.components.action'),
        ];
    }
}
