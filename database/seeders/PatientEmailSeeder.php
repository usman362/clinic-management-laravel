<?php

namespace Database\Seeders;

use App\Models\AdminEmail;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PatientEmailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AdminEmail::updateOrCreate(
            ['type' => 'patient_email'],
            [
                'subject' => 'Welcome to Bilingual Therapy',
                'body' => '
                        <p>We\'re pleased to support you through this process. Please use the link below and follow the on-screen instructions to
                        book the appointments for the academic assessment with our clinicians. Appointments may be scheduled in any order,
                        according to clinicians\' availability.</p>

                        <p>When booking, please remember to enter the details of the patient who will be
                        seen (the child).</p>

                        <p><strong>Booking Link:</strong> [booking_link]</p>

                        <p>Thank you,<br>
                        Bilingual Therapy Team</p>
                        '
            ]
        );
    }
}
