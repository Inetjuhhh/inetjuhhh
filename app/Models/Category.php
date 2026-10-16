<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $table = 'categories';
    protected $guarded = [];

    protected static function booted(): void
    {
        // The address of its page (/categorie/lifestyle) follows the name
        static::saving(function (Category $category) {
            $category->slug = Str::slug($category->name);
        });
    }

    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

    public function blogs()
    {
        return $this->belongsToMany(Blog::class, 'blog_subcategory');
    }

}
