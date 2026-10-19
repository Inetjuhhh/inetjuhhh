<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Foutje op inetjuhhh.nl": the alert to Ine when something went wrong (ErrorAlert). Plain text, sent
 * right away (not via the queue: the queue may be what's broken).
 */
class ErrorOccurred extends Mailable
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
