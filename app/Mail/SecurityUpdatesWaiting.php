<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Beveiligingsupdates voor inetjuhhh.nl": the morning mail from site:security-check when updates are
 * waiting on the server. Plain text, like the error mail.
 */
class SecurityUpdatesWaiting extends Mailable
{
    public function __construct(public string $title, public string $body)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.error-alert');
    }
}
