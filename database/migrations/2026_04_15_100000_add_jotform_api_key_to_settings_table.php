<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AP-02 / CP-12: Adds a `jotform_api_key` row to the `settings` table so the
 * admin can manage the Jotform API key from Admin → Settings → SMTP section.
 *
 * SettingRepository::update() only writes to existing keys, so the row must
 * exist before the admin form can save it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'jotform_api_key')->exists()) {
            DB::table('settings')->insert([
                'key'        => 'jotform_api_key',
                'value'      => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'jotform_api_key')->delete();
    }
};
