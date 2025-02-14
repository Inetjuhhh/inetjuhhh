<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    protected $table = 'blogs';
    protected $guarded = [];

    public function placed_by()
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    public function responses()
    {
        return $this->hasMany(Response::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'blog_subcategory');
    }

}
