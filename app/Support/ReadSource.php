<?php

namespace App\Support;

/**
 * How a reader came to a story, from the page they came from (and the newsletter's own mark in its
 * links, ?bron=nieuwsbrief). Only the kind is kept, never the address itself.
 */
class ReadSource
{
    public const LABELS = [
        'google' => 'Google',
        'zoekmachine' => 'Andere zoekmachine',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'pinterest' => 'Pinterest',
        'nieuwsbrief' => 'Nieuwsbrief',
        'eigen-site' => 'Via je eigen site',
        'direct' => 'Direct',
        'overig' => 'Overig',
    ];

    private const HOSTS = [
        'google' => '/(^|\.)google\.[a-z.]+$/',
        'zoekmachine' => '/(^|\.)(bing\.com|duckduckgo\.com|ecosia\.org|yahoo\.com|startpage\.com|qwant\.com|search\.brave\.com)$/',
        'facebook' => '/(^|\.)(facebook\.com|fb\.com|fb\.me|messenger\.com)$/',
        'instagram' => '/(^|\.)instagram\.com$/',
        'pinterest' => '/(^|\.)pinterest\.[a-z.]+$/',
    ];

    public static function from(?string $referrer, ?string $mark, string $ownHost): string
    {
        if (in_array(strtolower((string) $mark), ['nieuwsbrief', 'newsletter', 'mail'], true)) {
            return 'nieuwsbrief';
        }

        $host = strtolower((string) parse_url((string) $referrer, PHP_URL_HOST));

        if ($host === '') {
            return 'direct';
        }

        $host = preg_replace('/^(www|m|l|lm)\./', '', $host);
        $own = preg_replace('/^www\./', '', strtolower($ownHost));

        if ($host === $own || str_ends_with($host, '.' . $own)) {
            return 'eigen-site';
        }

        foreach (self::HOSTS as $source => $pattern) {
            if (preg_match($pattern, $host)) {
                return $source;
            }
        }

        return 'overig';
    }

    public static function label(string $source): string
    {
        return self::LABELS[$source] ?? ucfirst($source);
    }
}
