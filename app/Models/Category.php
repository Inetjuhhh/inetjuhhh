<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    protected $guarded = [];

    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

    public function blogs()
    {
        return $this->belongsToMany(Blog::class, 'blog_subcategory');
    }

}
