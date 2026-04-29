<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CP-40: settings table can contain DUPLICATE rows for the same key.
 *
 * On at least one production install, both the original migration and a
 * repo-level seeder insert a `jotform_api_key` row, leaving two records
 * with the same key. `getSettingValue` (helpers.php) used keyBy('key')
 * which picks the LAST collision — frequently the empty one — making
 * the admin's saved key appear unsaved.
 *
 * This migration collapses every duplicate group: keep the row whose
 * `value` is non-empty (or the lowest id when all are empty), delete
 * the rest. Idempotent and safe to re-run.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $duplicates = DB::table('settings')
            ->select('key', DB::raw('COUNT(*) as c'))
            ->groupBy('key')
            ->having('c', '>', 1)
            ->pluck('key');

        foreach ($duplicates as $key) {
            $rows = DB::table('settings')->where('key', $key)->orderBy('id')->get();

            // Pick the row to keep: prefer one with non-empty value;
            // otherwise the lowest-id row.
            $keep = $rows->firstWhere(fn ($r) => filled($r->value)) ?: $rows->first();

            $deleteIds = $rows->pluck('id')->reject(fn ($id) => $id === $keep->id)->values()->all();
            if (! empty($deleteIds)) {
                DB::table('settings')->whereIn('id', $deleteIds)->delete();
            }
        }
    }

    public function down(): void
    {
        // No-op — duplicates were never desirable.
    }
};
