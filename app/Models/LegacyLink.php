<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An old WordPress link and where it goes now: a blog (by its old slug) or a photo (by its old path).
 * Filled by the WordPress import; LegacyLinkController sends visitors on.
 */
class LegacyLink extends Model
{
    public const POST = 'post';

    public const UPLOAD = 'upload';

    protected $guarded = [];

    /**
     * Where an old link of this type goes now, or null when it never existed.
     */
    public static function target(string $type, string $oldKey): ?int
    {
        return static::where('type', $type)->where('old_key', $oldKey)->value('target_id');
    }
}
