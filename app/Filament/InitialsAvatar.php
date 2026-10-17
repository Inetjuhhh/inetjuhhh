<?php

namespace App\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The round picture in the admin's top right: the initials in the admin's teal, made here as a small SVG.
 * Filament's default fetches it from ui-avatars.com on every page, which also sends that service your name.
 */
class InitialsAvatar implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = Str::of(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            . '<rect width="64" height="64" fill="#3E8F86"/>'
            . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="system-ui, sans-serif" font-size="26" font-weight="600" fill="#ffffff">'
            . e($initials ?: '?') . '</text></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
