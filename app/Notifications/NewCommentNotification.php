<?php

namespace App\Notifications;

use App\Filament\Resources\ResponseResource;
use App\Models\Response;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Tells the blog owner about a new comment. A comment that waits for approval gets a button
 * to approve it straight from the mail, through a signed link that only works for this comment.
 */
class NewCommentNotification extends Notification
{
    use Queueable;

    public function __construct(public Response $comment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $comment = $this->comment;
        $blog = $comment->blog;
        $waiting = $comment->status === Response::PENDING;

        $mail = (new MailMessage)
            ->subject(($waiting ? 'Nieuwe reactie om goed te keuren: ' : 'Nieuwe reactie: ') . $blog->title)
            ->greeting($waiting ? 'Er wacht een reactie op je goedkeuring' : 'Er is een nieuwe reactie geplaatst')
            ->line("**{$comment->name}** ({$comment->email}) schreef bij \"{$blog->title}\":")
            ->line('> ' . str_replace("\n", "\n> ", $comment->response));

        if ($waiting) {
            $mail->action('Goedkeuren', URL::temporarySignedRoute(
                'comments.approve', now()->addDays(14), ['comment' => $comment->id],
            ))->line('Spam of niet akkoord? Beoordeel de reactie in de admin: ' . ResponseResource::getUrl('index'));
        } else {
            $mail->action('Bekijk de reactie', route('blogs.show', $blog) . '#reactie-' . $comment->id)
                ->line('Deze lezer is al eerder goedgekeurd, dus de reactie staat meteen online.');
        }

        return $mail;
    }
}
