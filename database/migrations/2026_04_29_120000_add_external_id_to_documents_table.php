<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CP-47: Track the originating Jotform submission id per document.
 *
 * In multi-doctor packages, the patient signs a SEPARATE submission per
 * doctor (each iframe → its own Jotform submission). Without an explicit
 * link from the Document row to the submission, the download flow
 * resolves "latest submission for this patient" — which returns the
 * SAME id for every document and the admin sees the same signature
 * appear on every doctor's PDF.
 *
 * Saving the submission id at consent time lets each document render
 * its own signed PDF, and lets the create-side pick distinct submissions
 * when multiple doctors are signed in quick succession.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }
        if (! Schema::hasColumn('documents', 'external_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->string('external_id', 64)->nullable()->after('appointment_id')
                    ->comment('Jotform submission id for consent docs');
                $table->index('external_id', 'documents_external_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'external_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex('documents_external_id_idx');
                $table->dropColumn('external_id');
            });
        }
    }
};
