<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;

class PatientAppointmentBookMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        $name = $this->data['patient_name'];
        $patientId = $this->data['patient_id'];
        $link = $this->data['booking_link'];

        $subject = 'Welcome to Bilingual Therapy';

        return $this->markdown('emails.patient_appointment_booked_mail', [
            'name' => $name,
            'patientId' => $patientId,
            'link' => $link,
        ])->subject($subject);
    }
}
