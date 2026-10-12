<?php

namespace App\Models;

use App\Notifications\CommentReplyNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * A comment under a blog. Readers comment without an account; their email address is
 * never shown. A first comment waits for approval, later comments from the same email
 * address appear right away. Replies by the blog owner hang under a comment.
 */
class Response extends Model
{
    use MassPrunable;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const SPAM = 'spam';

    public const STATUSES = [
        self::PENDING => 'Wacht op goedkeuring',
        self::APPROVED => 'Goedgekeurd',
        self::SPAM => 'Spam',
    ];

    protected $table = 'responses';
    protected $guarded = [];

    protected $hidden = ['email', 'ip_hash', 'user_agent'];

    protected $casts = [
        'is_author' => 'boolean',
        'notify_on_reply' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // A reader who asked for it gets a mail when the blog owner replies to their comment
        static::created(function (Response $reply) {
            $parent = $reply->parent;

            if ($reply->is_author && $reply->status === self::APPROVED && $parent?->notify_on_reply && ! $parent->is_author) {
                Notification::route('mail', $parent->email)->notify(new CommentReplyNotification($reply));
            }
        });
    }

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', self::APPROVED);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function approve(): void
    {
        $this->update(['status' => self::APPROVED, 'approved_at' => $this->approved_at ?? now()]);
    }

    public function markAsSpam(): void
    {
        $this->update(['status' => self::SPAM]);
    }

    /**
     * Someone whose comment was approved before is trusted: their next comments appear right away.
     */
    public static function isTrustedEmail(string $email): bool
    {
        return static::approved()->where('is_author', false)->where('email', mb_strtolower($email))->exists();
    }

    /**
     * Spam is cleaned up automatically after 30 days (php artisan model:prune, run by the scheduler).
     */
    public function prunable(): Builder
    {
        return static::where('status', self::SPAM)->where('created_at', '<', now()->subDays(30));
    }
}
