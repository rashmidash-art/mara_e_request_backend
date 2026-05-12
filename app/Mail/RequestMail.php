<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this
            ->subject($this->data['mail_subject'] ?? 'Request Notification')
            ->view('emails.request_mail')
            ->with($this->data); // extracts array keys as blade variables
    }
}
