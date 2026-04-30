<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CP-42: Persist the Jotform API region (US / EU / HIPAA) in settings.
 *
 * Jotform exposes three different API hosts depending on where the
 * account is provisioned:
 *   - api.jotform.com         (default, US)
 *   - eu-api.jotform.com      (EU GDPR data residency)
 *   - hipaa-api.jotform.com   (HIPAA-enabled)
 *
 * An API key issued for one region cannot query forms from another —
 * the call returns 401/404 and our consent PDF flow silently falls
 * back to a generated summary. Storing the region next to the key
 * lets the controller pick the right host every time.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $exists = DB::table('settings')->where('key', 'jotform_region')->exists();
        if (! $exists) {
            DB::table('settings')->insert([
                'key'        => 'jotform_region',
                'value'      => 'us',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'jotform_region')->delete();
        }
    }
};
