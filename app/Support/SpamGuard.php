<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The spam checks for comments, from cheapest to most expensive. Bots that fill in the hidden
 * field or post too fast are dropped silently (they get the normal "thank you", so they learn
 * nothing); suspicious content is kept as spam for the admin to review.
 */
class SpamGuard
{
    public const HONEYPOT = 'website';

    public const STARTED = 'started';

    /**
     * Value for the hidden "started" field: when the form was shown, encrypted so it can't be faked.
     */
    public static function startedToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * True when the request is clearly from a bot and should be ignored entirely.
     */
    public static function isBot(Request $request): bool
    {
        if (filled($request->input(self::HONEYPOT))) {
            return true;
        }

        try {
            $started = (int) Crypt::decryptString((string) $request->input(self::STARTED));
        } catch (Throwable) {
            return true;
        }

        $seconds = now()->getTimestamp() - $started;

        // Too fast for a human, or a form kept around for days and replayed
        return $seconds < config('blog.comments.min_seconds') || $seconds > 60 * 60 * 24;
    }

    /**
     * True when the text looks like spam: too many links or a blocked word.
     */
    public static function looksLikeSpam(string $text, string $name): bool
    {
        $content = mb_strtolower($text . ' ' . $name);

        // One match per link: "https://www.…" is one link, not two
        if (preg_match_all('~(?:https?://|www\.)\S+~i', $content) > config('blog.comments.max_links')) {
            return true;
        }

        // Forum and HTML links are written for other sites, never by a reader here
        if (preg_match('~\[url|\[link|<a\s|href\s*=~i', $content)) {
            return true;
        }

        // A real name has no link in it
        if (preg_match('~https?://|www\.|\.(com|net|org|ru|xyz|top|info|biz|io)\b~i', $name)) {
            return true;
        }

        // Mostly Cyrillic, Chinese, Arabic … on a Dutch blog: almost always a spam run
        $letters = preg_match_all('~\p{L}~u', $content);
        $foreign = preg_match_all('~[\p{Cyrillic}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Arabic}\p{Thai}\p{Hebrew}]~u', $content);
        if ($letters > 0 && $foreign / $letters > 0.3) {
            return true;
        }

        foreach (config('blog.comments.blocked_words') as $word) {
            if (str_contains($content, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cloudflare Turnstile check; always passes when no keys are configured.
     */
    public static function passesTurnstile(Request $request): bool
    {
        $secret = config('services.turnstile.secret_key');

        if (blank($secret)) {
            return true;
        }

        try {
            return (bool) Http::asForm()->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => (string) $request->input('cf-turnstile-response'),
                    'remoteip' => $request->ip(),
                ])
                ->json('success');
        } catch (Throwable) {
            // Cloudflare unreachable: let the comment through to moderation rather than lose it
            return true;
        }
    }
}
