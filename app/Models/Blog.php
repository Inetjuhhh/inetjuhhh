<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;

class Blog extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'blogs';
    protected $guarded = [];
    protected $casts = [
        'tags' => 'array',
        'attachments' => 'array',
    ];

    public function placed_by()
    {
        return $this->belongsTo(User::class, 'placed_by_id', 'id');
    }

    public function responses()
    {
        return $this->hasMany(Response::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'blog_tag');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'blog_subcategory');
    }

    public function countries()
    {
        return $this->belongsToMany(Country::class, 'blog_country');
    }

}
