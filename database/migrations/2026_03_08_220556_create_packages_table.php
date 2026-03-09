<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('relation_id')->unique();          // links to appointments.relation_id
            $table->unsignedInteger('patient_id');            // patient who owns the package
            $table->unsignedBigInteger('created_by');         // admin/doctor who created it
            $table->string('appointment_type')->default('assessment'); // assessment | feedback
            $table->text('description')->nullable();
            $table->string('payable_amount')->nullable();
            $table->integer('payment_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
