<?php

namespace App\Models;

use App\Support\Geocoder;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';
    protected $guarded = [];
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        // Blogs without their own place are pinned in the middle of their country
        static::saving(function (Country $country) {
            $nameChanged = $country->isDirty('name') || $country->latitude === null;
            $coordinatesTyped = $country->isDirty(['latitude', 'longitude']) && $country->latitude !== null;

            if ($nameChanged && ! $coordinatesTyped && ($found = Geocoder::find($country->name))) {
                [$country->latitude, $country->longitude] = $found;
            }
        });
    }

    public function blogs()
    {
        return $this->belongsToMany(Blog::class, 'blog_country');
    }
}
