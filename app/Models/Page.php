<?php

namespace App\Models;

use App\Models\Concerns\RendersRichContent;
use Illuminate\Database\Eloquent\Model;

/**
 * A fixed page of the site, like "Ik & wij". There is one record per page; the admin only edits it.
 */
class Page extends Model
{
    use RendersRichContent;

    public const ABOUT = 'ik-en-wij';

    protected $guarded = [];
    protected $casts = [
        'content' => 'json',
    ];

    public static function about(): self
    {
        return static::firstOrCreate(['slug' => self::ABOUT], ['title' => 'Ik & wij']);
    }
}
