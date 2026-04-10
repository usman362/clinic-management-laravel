<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AP-04: Add explicit status workflow + payment tracking to packages.
 *
 * Status lifecycle:
 *   pending → link_sent → appointments_booked → feedback_sent → feedback_booked → completed
 *
 * Payment tracking is manual (physical payments): admin/doctor marks payment received.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Explicit lifecycle status (replaces derived-only approach).
            $table->string('status', 30)->default('pending')->after('appointment_type');

            // Manual payment tracking (clinic uses physical payments).
            $table->boolean('payment_received')->default(false)->after('payment_method');
            $table->timestamp('payment_received_at')->nullable()->after('payment_received');
            $table->text('payment_notes')->nullable()->after('payment_received_at');
        });

        // Back-fill existing packages: derive status from their appointments.
        \App\Models\Package::with('appointments')->chunk(50, function ($packages) {
            foreach ($packages as $package) {
                $appts = $package->appointments;
                $total = $appts->count();
                if ($total === 0) {
                    $package->update(['status' => 'pending']);
                    continue;
                }

                $booked = $appts->whereIn('status', [
                    \App\Models\Appointment::BOOKED,
                    \App\Models\Appointment::CHECK_IN,
                    \App\Models\Appointment::CHECK_OUT,
                ])->count();
                $done = $appts->where('status', \App\Models\Appointment::CHECK_OUT)->count();

                if ($total > 0 && $done === $total) {
                    $status = 'completed';
                } elseif ($package->feedback_sent_at) {
                    $status = 'feedback_sent';
                } elseif ($booked > 0) {
                    $status = 'appointments_booked';
                } else {
                    $status = 'link_sent';
                }
                $package->update(['status' => $status]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['status', 'payment_received', 'payment_received_at', 'payment_notes']);
        });
    }
};
