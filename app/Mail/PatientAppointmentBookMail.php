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
        $template = $this->data['template'];

        // Replace placeholders
        $body = str_replace(
            '[booking_link]',
            $this->data['booking_link'],
            $template->body
        );

        return $this->subject($template->subject)
            ->html($body);
    }
}
