<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AP-16: Soft delete for Packages + Appointments.
 *
 * Replaces V8's hard-delete behaviour. When admin trashes a package the
 * Package + every appointment with the same relation_id (and any child
 * feedback packages + their appointments) get soft-deleted and stamped
 * with a shared ULID `delete_batch_id`. Restore looks up that batch id
 * and un-trashes exactly those rows — no risk of accidentally restoring
 * an unrelated package whose `deleted_at` is close in time.
 *
 * Force-delete (permanent) flips back to the V8 hard-cascade — handled
 * in AppointmentController, not at the schema layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->softDeletes();
            $table->char('delete_batch_id', 26)->nullable()->after('deleted_at');
            $table->index('delete_batch_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->softDeletes();
            $table->char('delete_batch_id', 26)->nullable()->after('deleted_at');
            $table->index('delete_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['delete_batch_id']);
            $table->dropColumn('delete_batch_id');
            $table->dropSoftDeletes();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['delete_batch_id']);
            $table->dropColumn('delete_batch_id');
            $table->dropSoftDeletes();
        });
    }
};
