<?php

namespace App\Models;

use App\Support\Geocoder;
use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Trip extends Model
{
    // A journey or a city trip (the label on its card on the Reizen page)
    public const KIND_TRIP = 'trip';

    public const KIND_CITY = 'city';

    public const KINDS = [
        self::KIND_TRIP => 'Reis',
        self::KIND_CITY => 'Citytrip',
    ];

    protected $guarded = [];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'route' => 'array',
    ];

    protected static function booted(): void
    {
        // The planned stops are looked up on the map by their name, like the place of a story
        static::saving(function (Trip $trip) {
            $trip->route = collect($trip->route ?? [])
                ->filter(fn ($stop) => filled($stop['name'] ?? null))
                ->map(function (array $stop) {
                    $name = trim($stop['name']);
                    $known = filled($stop['lat'] ?? null) && ($stop['found_for'] ?? null) === $name;
                    $found = $known ? [$stop['lat'], $stop['lng']] : Geocoder::find($name);

                    return ['name' => $name, 'lat' => $found[0] ?? null, 'lng' => $found[1] ?? null, 'found_for' => $found ? $name : null];
                })
                ->values()
                ->all() ?: null;
        });

        // A stable, unique link for the filter (?reis=vietnam-cambodja-2025)
        static::saving(function (Trip $trip) {
            if (blank($trip->slug) || $trip->isDirty('name')) {
                $base = Str::slug($trip->name) ?: 'reis';
                $slug = $base;

                for ($i = 2; static::where('slug', $slug)->whereKeyNot($trip->getKey())->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }

                $trip->slug = $slug;
            }
        });
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('starts_on', '>', today())->orderBy('starts_on');
    }

    public function isUpcoming(): bool
    {
        return $this->starts_on?->isAfter(today()) ?? false;
    }

    /**
     * The planned stops that were found on the map.
     *
     * @return list<array{name: string, lat: float, lng: float}>
     */
    public function plannedStops(): array
    {
        return collect($this->route ?? [])
            ->filter(fn (array $stop) => isset($stop['lat'], $stop['lng']))
            ->map(fn (array $stop) => ['name' => $stop['name'], 'lat' => (float) $stop['lat'], 'lng' => (float) $stop['lng']])
            ->values()
            ->all();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }

    /**
     * The trip's published stories in reading order: the order they were published in.
     *
     * @return Collection<int, Blog>
     */
    public function parts(): Collection
    {
        // Remembered for the rest of the request: a blog page asks twice (the route and the map)
        return $this->parts ??= $this->blogs()->published()->get()
            ->sortBy(fn (Blog $blog) => $blog->publishedDate())
            ->values();
    }

    /** @var Collection<int, Blog>|null */
    private ?Collection $parts = null;

    public function coverImage()
    {
        return $this->belongsTo(Media::class, 'cover_image_id');
    }

    /**
     * The featured photos of the trip's stories, in reading order: what its card photo is chosen from.
     *
     * @return Collection<int, Media>
     */
    public function coverChoices(): Collection
    {
        return $this->parts()->loadMissing('featuredImage')->pluck('featuredImage')->filter()->unique('id')->values();
    }

    /**
     * The photo on the trip's card: the one chosen in the admin, otherwise the featured photo of the
     * story halfway through the trip (the first is often packing, the last coming home).
     */
    public function coverMedia(): ?Media
    {
        if ($this->coverImage) {
            return $this->coverImage;
        }

        $choices = $this->coverChoices();

        return $choices->get(intdiv(max($choices->count() - 1, 0), 2));
    }

    /**
     * "maart – april 2026", "juni 2025" or null when no dates were entered.
     */
    public function period(): ?string
    {
        $start = $this->starts_on;
        $end = $this->ends_on;

        if (! $start && ! $end) {
            return null;
        }

        if (! $start || ! $end || $start->isSameMonth($end)) {
            return ($start ?? $end)->translatedFormat('F Y');
        }

        return $start->isSameYear($end)
            ? $start->translatedFormat('F') . ' – ' . $end->translatedFormat('F Y')
            : $start->translatedFormat('F Y') . ' – ' . $end->translatedFormat('F Y');
    }

    public function filterUrl(): string
    {
        return route('blogs.index', ['reis' => $this->slug]);
    }

    public function mapUrl(): string
    {
        return route('blogs.map', ['reis' => $this->slug]);
    }
}
