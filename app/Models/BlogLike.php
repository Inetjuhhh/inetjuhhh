<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A heart for a blog. Visitors don't log in: a random id in a cookie (stored here only as a
 * hash) makes sure one browser can like a blog once.
 */
class BlogLike extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
}
