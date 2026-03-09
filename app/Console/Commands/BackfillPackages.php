<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPackages extends Command
{
    protected $signature = 'packages:backfill {--dry-run : Show what would be created without saving}';

    protected $description = 'Backfill Package records for existing appointment groups (relation_id).';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Get one representative appointment per relation_id group
        $groups = Appointment::select('relation_id', 'patient_id', 'appointment_type', 'description', 'payable_amount', 'payment_type', 'payment_method', 'created_at')
            ->whereNotNull('relation_id')
            ->whereIn('id', function ($q) {
                $q->selectRaw('MIN(id)')
                  ->from('appointments')
                  ->whereNotNull('relation_id')
                  ->groupBy('relation_id');
            })
            ->get();

        $existingRelationIds = Package::pluck('relation_id')->flip();
        $created = 0;
        $skipped = 0;

        foreach ($groups as $appt) {
            if ($existingRelationIds->has($appt->relation_id)) {
                $skipped++;
                continue;
            }

            $this->line("  Creating package for relation_id: {$appt->relation_id} (patient_id: {$appt->patient_id})");

            if (! $dryRun) {
                Package::create([
                    'relation_id'      => $appt->relation_id,
                    'patient_id'       => $appt->patient_id,
                    'created_by'       => 1, // system/admin default
                    'appointment_type' => $appt->appointment_type ?? 'assessment',
                    'description'      => $appt->description,
                    'payable_amount'   => $appt->payable_amount,
                    'payment_type'     => $appt->payment_type,
                    'payment_method'   => $appt->payment_method,
                    'created_at'       => $appt->created_at,
                    'updated_at'       => $appt->created_at,
                ]);
            }

            $created++;
        }

        $this->info("Done. Created: {$created}, Skipped (already exist): {$skipped}." . ($dryRun ? ' [DRY RUN]' : ''));

        return self::SUCCESS;
    }
}
