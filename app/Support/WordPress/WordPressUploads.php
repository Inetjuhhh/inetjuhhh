<?php

namespace App\Support\WordPress;

/**
 * WordPress keeps every photo in several versions: Foto.jpg, Foto-scaled.jpg, Foto-768x1024.jpg …
 * They all lead to the same photo here, so they all get the same key: "2019/01/foto".
 */
class WordPressUploads
{
    public static function key(string $urlOrPath): string
    {
        $path = parse_url($urlOrPath, PHP_URL_PATH) ?? $urlOrPath;
        $path = preg_replace('#^.*?/?(wp-content/)?uploads/#', '', ltrim(rawurldecode($path), '/'));
        $path = preg_replace('/\.[a-z0-9]+$/i', '', $path);             // extension
        $path = preg_replace('/(-\d+x\d+|-scaled|-rotated|-e\d{10,})+$/', '', $path);   // the extra versions

        return mb_strtolower($path);
    }
}
