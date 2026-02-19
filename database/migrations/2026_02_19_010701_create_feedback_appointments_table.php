<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback_appointments', function (Blueprint $table) {
            $table->id();

            // Link to original appointment
            $table->bigInteger('appointment_id')->unsigned()->unique();

            // Copy doctor & patient
            $table->bigInteger('doctor_id')->unsigned();
            $table->unsignedInteger('patient_id');

            // Copy main appointment info
            $table->string('date');
            $table->string('from_time');
            $table->string('from_time_type');
            $table->string('to_time');
            $table->string('to_time_type');
            $table->tinyInteger('status')->default(5); // default feedback status
            $table->text('description')->nullable();
            $table->bigInteger('service_id')->unsigned();
            $table->string('payable_amount');
            $table->integer('payment_type')->default(1);
            $table->integer('payment_method')->default(1);
            $table->string('appointment_unique_id');
            $table->string('relation_id')->nullable();
            $table->string('charge')->nullable();
            $table->string('appointment_type')->default('assessment');

            $table->timestamps();

            // Indexes & foreign keys
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_appointments');
    }
};
