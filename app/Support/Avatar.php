<?php

namespace App\Support;

use App\Models\Setting;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ine's avatar: in the browser tab (site and admin), on a phone's home screen, in the site's menu
 * and footer, and at her replies under comments. Chosen in the admin (Beheer → Website); without a
 * choice, the avatar that came with the site (public/favicon.ico and friends) is used.
 *
 * A chosen photo is cut square and saved in a few sizes as PNG, under a new name every time: the
 * web server lets browsers keep icons for a month, so a new name is the only way they fetch a new one.
 */
class Avatar
{
    public const SETTING = 'avatar';

    // 32: browser tab, 180: phone home screen, 512: menu, footer and comments (sharp on every screen)
    public const SIZES = [32, 180, 512];

    private const DIRECTORY = 'avatar';

    public static function url(int $size): string
    {
        $chosen = Setting::get(self::SETTING);

        if ($chosen) {
            return asset("storage/{$chosen['path']}-{$size}.png");
        }

        return match (true) {
            $size <= 32 => asset('favicon-32.png'),
            $size <= 180 => asset('apple-touch-icon.png'),
            default => asset('storage/img/avatar.ico'),
        };
    }

    /**
     * The icon for the browser tab: the chosen avatar, or the site's own favicon.ico.
     */
    public static function faviconUrl(): string
    {
        return Setting::get(self::SETTING) ? static::url(32) : asset('favicon.ico');
    }

    public static function mediaId(): ?int
    {
        return Setting::get(self::SETTING)['media_id'] ?? null;
    }

    /**
     * Makes the avatar from a photo in the media library.
     *
     * @throws RuntimeException when the file isn't a photo PHP can read (for example an SVG)
     */
    public static function useMedia(Media $media): void
    {
        $contents = Storage::disk($media->disk)->get($media->path);
        $image = $contents ? @imagecreatefromstring($contents) : false;

        if (! $image) {
            throw new RuntimeException('Deze foto kan niet als avatar gebruikt worden. Kies een JPG, PNG of WebP.');
        }

        // The middle square of the photo
        $side = min(imagesx($image), imagesy($image));
        $x = intdiv(imagesx($image) - $side, 2);
        $y = intdiv(imagesy($image) - $side, 2);

        $path = self::DIRECTORY . '/' . Str::lower(Str::random(10));
        $disk = Storage::disk('public');

        foreach (self::SIZES as $size) {
            $icon = imagecreatetruecolor($size, $size);
            imagealphablending($icon, false);
            imagesavealpha($icon, true);
            imagecopyresampled($icon, $image, 0, 0, $x, $y, $size, $size, $side, $side);

            ob_start();
            imagepng($icon, null, 9);
            $disk->put("{$path}-{$size}.png", ob_get_clean());
        }

        $old = Setting::get(self::SETTING);
        Setting::put(self::SETTING, ['media_id' => $media->getKey(), 'path' => $path]);
        static::removeFiles($old);
    }

    /**
     * Back to the avatar that came with the site.
     */
    public static function reset(): void
    {
        $old = Setting::get(self::SETTING);
        Setting::put(self::SETTING, null);
        static::removeFiles($old);
    }

    private static function removeFiles(?array $setting): void
    {
        if (! $setting) {
            return;
        }

        Storage::disk('public')->delete(array_map(fn (int $size) => "{$setting['path']}-{$size}.png", self::SIZES));
    }
}
