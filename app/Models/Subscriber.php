<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A reader who wants an email for every new blog. Double opt-in: only after clicking the link
 * in the confirmation mail are they on the list, so nobody can sign up someone else.
 */
class Subscriber extends Model
{
    use Prunable;

    protected $guarded = [];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscriber $subscriber) {
            $subscriber->token ??= Str::random(48);
        });
    }

    public function scopeConfirmed(Builder $query): void
    {
        $query->whereNotNull('confirmed_at');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function confirmUrl(): string
    {
        return URL::temporarySignedRoute('subscribers.confirm', now()->addDays(7), ['subscriber' => $this->id]);
    }

    public function unsubscribeUrl(): string
    {
        return route('subscribers.unsubscribe', $this->token);
    }

    /**
     * Sign-ups that were never confirmed are removed after 30 days.
     */
    public function prunable(): Builder
    {
        return static::whereNull('confirmed_at')->where('created_at', '<', now()->subDays(30));
    }
}
