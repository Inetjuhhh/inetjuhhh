<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';
    protected $guarded = [];

    public function blogs()
    {
        return $this->belongsToMany(Blog::class, 'blog_country');
    }
}
