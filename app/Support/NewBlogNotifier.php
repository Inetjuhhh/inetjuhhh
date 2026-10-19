<?php

namespace App\Support;

use App\Mail\NewBlogPublished;
use App\Models\Blog;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Mail;

/**
 * The "new blog online" mail to every confirmed subscriber. Never automatic: Ine sends it herself with
 * the button "Mail abonnees" in the admin (BlogResource::mailSubscribersAction), once per blog.
 */
class NewBlogNotifier
{
    /**
     * A blog can be sent once it is online for readers and hasn't been sent before.
     */
    public static function canSend(Blog $blog): bool
    {
        return $blog->isPublished() && $blog->subscribers_notified_at === null;
    }

    /**
     * Puts a mail for every confirmed subscriber on the queue.
     *
     * @return int How many mails were queued (0 when there are no subscribers, or the blog was already sent)
     */
    public static function send(Blog $blog): int
    {
        // No subscribers yet: leave the blog unsent, so the button is still there once there are some
        if (! static::canSend($blog) || ! Subscriber::confirmed()->exists()) {
            return 0;
        }

        // Claim the blog first: two clicks at the same moment can't both send it
        $claimed = Blog::whereKey($blog->getKey())
            ->whereNull('subscribers_notified_at')
            ->update(['subscribers_notified_at' => now()]);

        if ($claimed === 0) {
            return 0;
        }

        $blog->subscribers_notified_at = now();
        $blog->syncOriginalAttribute('subscribers_notified_at');

        $count = 0;

        Subscriber::confirmed()->lazyById()->each(function (Subscriber $subscriber) use ($blog, &$count) {
            Mail::to($subscriber->email, $subscriber->name)->queue(new NewBlogPublished($blog, $subscriber));
            $count++;
        });

        return $count;
    }

    /**
     * The same mail to one address, right away (not via the queue), to see how it looks before
     * sending it to everyone. Nothing is marked as sent; the unsubscribe link in it goes nowhere.
     */
    public static function sendTest(Blog $blog, string $email, string $name): void
    {
        $example = new Subscriber(['name' => $name, 'email' => $email]);
        $example->token = 'voorbeeld';

        Mail::to($email, $name)->sendNow(new NewBlogPublished($blog, $example));
    }
}
