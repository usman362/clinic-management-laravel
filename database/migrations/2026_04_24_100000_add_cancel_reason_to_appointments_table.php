<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `cancel_reason` column to the `appointments` table so the UI can
 * distinguish between a patient-initiated cancellation (eligible for rebook)
 * and a clinic-initiated cancellation — e.g. when an admin edits a package
 * and removes a service row, the corresponding appointment is cancelled but
 * MUST NOT be rebookable because the service is no longer offered in that
 * package.
 *
 * Possible values (free-form string; current enum values only for clarity):
 *   null              — patient-initiated or legacy cancel (rebookable)
 *   'clinic_removed'  — service removed from package (NOT rebookable)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'cancel_reason')) {
                $table->string('cancel_reason', 64)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
        });
    }
};
