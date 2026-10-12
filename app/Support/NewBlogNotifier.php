<?php

namespace App\Support;

use App\Mail\NewBlogPublished;
use App\Models\Blog;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Mail;

/**
 * Puts a "new blog online" mail for every confirmed subscriber on the queue, once per blog.
 */
class NewBlogNotifier
{
    /**
     * @return int How many mails were queued (0 when this blog was already sent)
     */
    public static function send(Blog $blog): int
    {
        // Claim the blog first: a save and the scheduled command at the same moment can't both send it
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
     * Blogs planned for later whose publish date has passed. Run by the scheduler.
     */
    public static function sendDue(): int
    {
        return Blog::published()
            ->whereNull('subscribers_notified_at')
            ->oldestFirst()
            ->get()
            ->sum(fn (Blog $blog) => static::send($blog));
    }
}
