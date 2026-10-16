<?php

namespace App\Mail;

use App\Models\Blog;
use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * "There's a new blog online", one per subscriber, sent through the queue.
 *
 * Keeps the subscriber's details as plain values instead of the model: someone who
 * unsubscribes while the mails are going out would otherwise make the job fail.
 */
class NewBlogPublished extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $name;

    public string $unsubscribeUrl;

    public function __construct(public Blog $blog, Subscriber $subscriber)
    {
        $this->name = $subscriber->name;
        $this->unsubscribeUrl = $subscriber->unsubscribeUrl();
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nieuw verhaal: ' . $this->blog->title);
    }

    // The unsubscribe button mail apps show next to the sender (Gmail, Apple Mail, Outlook)
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $blog = $this->blog;

        return new Content(markdown: 'mail.subscribers.new-blog', with: [
            'blog' => $blog,
            'url' => route('blogs.show', $blog),
            // JPEG: not every mail app shows WebP (Outlook on Windows doesn't)
            // A real photo only: the placeholder is an SVG, which most mail apps don't show
            'cover' => $blog->hasCover() ? url($blog->coverUrl(1200, 700, 'jpg')) : null,
            'excerpt' => $blog->excerpt !== 'No excerpt' ? $blog->excerpt : null,
            'author' => $blog->placed_by?->name,
        ]);
    }
}
