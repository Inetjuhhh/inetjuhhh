<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Response extends Model
{
    protected $table = 'responses';
    protected $guarded = [];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
}
