<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AP-16 (revision): Soft-delete on `appointments` was the wrong tool.
 *
 * Client requirement: "Appointments within deleted packages should all be
 * cancelled" — i.e. the patient must STILL SEE the appointments, but as
 * `CANCELLED` with the rebook button suppressed (the existing
 * `cancel_reason = 'clinic_removed'` guard already does that).
 *
 * Soft-deleting appointments hides them from every query, defeating the
 * audit-trail requirement. Undo the appointments-side soft-delete and
 * replace it with:
 *
 *   - status = CANCELLED + cancel_reason = 'clinic_removed' on each
 *     appointment when its parent package is trashed
 *   - `pre_cancel_status` column to remember the appointment's previous
 *     status, so a Restore can put it back exactly where it was
 *
 * `delete_batch_id` stays on `appointments` as the link from a soft-
 * deleted Package back to the affected appointment rows.
 *
 * `packages` keeps SoftDeletes — the trash list operates on packages,
 * not individual appointments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropSoftDeletes();
            // `pre_cancel_status` mirrors `status` (tinyint integer in this app
            // — see Appointment::STATUS constants). Used only by AP-16 restore.
            $table->unsignedTinyInteger('pre_cancel_status')->nullable()->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('pre_cancel_status');
            $table->softDeletes();
        });
    }
};
