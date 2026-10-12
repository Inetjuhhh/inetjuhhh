<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The link a new subscriber clicks to confirm it was really them who signed up.
 */
class ConfirmSubscription extends Mailable
{
    public function __construct(public Subscriber $subscriber)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bevestig je inschrijving op ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.subscribers.confirm', with: [
            'name' => $this->subscriber->name,
            'url' => $this->subscriber->confirmUrl(),
        ]);
    }
}
