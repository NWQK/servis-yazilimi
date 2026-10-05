<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;
    public $data;
    public function __construct($data)
    {
        $this->data = $data;
    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data = $this->data;
        $settings = $data['settings'];
        $subject = $data['subject'];

        return $this->from($settings['FROM_EMAIL'], $settings['FROM_NAME'])->view('email.email_verification')->subject($subject)->with(['subject'=>$subject, 'company_name'=>$settings['company_name'] ?? 'SanayiRandevu'])->with('data', $data);
    }
}
