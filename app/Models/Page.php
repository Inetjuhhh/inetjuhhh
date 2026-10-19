<?php

namespace App\Models;

use App\Models\Concerns\RendersRichContent;
use App\Support\PrivacyText;
use Illuminate\Database\Eloquent\Model;

/**
 * A fixed page of the site, like "Ik & wij" or "Privacy". There is one record per page; the admin only edits it.
 */
class Page extends Model
{
    use RendersRichContent;

    public const ABOUT = 'ik-en-wij';

    public const PRIVACY = 'privacy';

    protected $guarded = [];
    protected $casts = [
        'content' => 'json',
    ];

    public static function about(): self
    {
        return static::firstOrCreate(['slug' => self::ABOUT], ['title' => 'Ik & wij']);
    }

    // The first time with a draft text, so the page is never empty (Ine edits it in the admin)
    public static function privacy(): self
    {
        return static::firstOrCreate(['slug' => self::PRIVACY], ['title' => 'Privacy', 'content' => PrivacyText::doc()]);
    }
}
