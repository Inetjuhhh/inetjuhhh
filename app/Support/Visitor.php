<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Recognises a browser without an account: a random id in a cookie. Only hashes are stored,
 * of that id and of the IP address, so nothing in the database points to a person.
 */
class Visitor
{
    private const COOKIE = 'visitor_id';

    public static function id(Request $request): string
    {
        $id = $request->cookie(self::COOKIE);

        if (! is_string($id) || ! Str::isUuid($id)) {
            $id = (string) Str::uuid();
            Cookie::queue(self::COOKIE, $id, 60 * 24 * 365 * 2);
        }

        return $id;
    }

    public static function hash(Request $request): string
    {
        return hash_hmac('sha256', self::id($request), config('app.key'));
    }

    /**
     * The hash for a browser that already has the cookie, without setting one: just reading a
     * blog never places a cookie, only clicking the heart does.
     */
    public static function existingHash(Request $request): ?string
    {
        $id = $request->cookie(self::COOKIE);

        return is_string($id) && Str::isUuid($id) ? hash_hmac('sha256', $id, config('app.key')) : null;
    }

    public static function ipHash(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip(), config('app.key'));
    }
}
