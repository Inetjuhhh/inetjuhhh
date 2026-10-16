<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How often a blog was read on one day. Only a number: nothing about who read it.
 */
class BlogView extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
}
