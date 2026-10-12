<?php

namespace App\Notifications;

use App\Models\Response;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lets a reader know the blog owner replied to their comment (only when they asked for it).
 */
class CommentReplyNotification extends Notification
{
    use Queueable;

    public function __construct(public Response $reply)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $blog = $this->reply->blog;

        return (new MailMessage)
            ->subject("Je hebt een antwoord op je reactie bij \"{$blog->title}\"")
            ->greeting("Hoi {$this->reply->parent->name},")
            ->line('Je vroeg om een berichtje als er een antwoord op je reactie kwam. Dat is er:')
            ->line('> ' . str_replace("\n", "\n> ", $this->reply->response))
            ->action('Lees het antwoord', route('blogs.show', $blog) . '#reactie-' . $this->reply->id)
            ->line('Je krijgt alleen een mail voor antwoorden op deze ene reactie.');
    }
}
