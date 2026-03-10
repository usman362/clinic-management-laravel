<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_week_days', function (Blueprint $table) {
            $table->integer('session_meeting_time')->nullable()->after('end_time_type')
                ->comment('Slot duration in minutes for this specific time block. Falls back to doctor_sessions.session_meeting_time if null.');
        });
    }

    public function down(): void
    {
        Schema::table('session_week_days', function (Blueprint $table) {
            $table->dropColumn('session_meeting_time');
        });
    }
};
