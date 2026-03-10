<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Track when the feedback link/email was sent for an assessment package
            $table->timestamp('feedback_sent_at')->nullable()->after('payment_method');

            // Link a feedback package back to its parent assessment package
            $table->unsignedBigInteger('parent_package_id')->nullable()->after('feedback_sent_at');
            $table->foreign('parent_package_id')->references('id')->on('packages')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign(['parent_package_id']);
            $table->dropColumn(['feedback_sent_at', 'parent_package_id']);
        });
    }
};
