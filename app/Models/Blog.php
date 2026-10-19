<?php

namespace App\Models;

use App\Models\Concerns\RendersRichContent;
use App\Support\BlogImages;
use App\Support\BlogSearch;
use App\Support\Geocoder;
use Awcodes\Curator\Models\Media as CuratorMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Blog extends Model
{
    use RendersRichContent;

    protected $table = 'blogs';
    protected $guarded = [];
    protected $casts = [
        'content' => 'json',
        'published_at' => 'datetime',
        'subscribers_notified_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        // Look up the place on the map when it changes, unless a pin was placed by hand
        static::saving(function (Blog $blog) {
            if (! $blog->isDirty('location_name') || $blog->isDirty(['latitude', 'longitude'])) {
                return;
            }

            if (blank($blog->location_name)) {
                [$blog->latitude, $blog->longitude] = [null, null];
            } elseif ($found = Geocoder::find($blog->location_name)) {
                [$blog->latitude, $blog->longitude] = $found;
            }
            // Not found: keep the pin that was there, rather than dropping the blog off the map
        });

        // Keep the plain text for the search bar in step with the content
        static::saving(function (Blog $blog) {
            if ($blog->isDirty('content') || $blog->search_text === null) {
                $blog->search_text = BlogSearch::plainText($blog->content);
            }
        });

        // The mail to subscribers is never sent on saving: Ine sends it with the button "Mail abonnees"
        // in the admin, when she wants (NewBlogNotifier)
    }

    public function placed_by()
    {
        return $this->belongsTo(User::class, 'placed_by_id', 'id');
    }

    public function responses()
    {
        return $this->hasMany(Response::class);
    }

    /**
     * Approved comments from readers, oldest first, with the owner's approved replies.
     */
    public function comments()
    {
        return $this->hasMany(Response::class)
            ->whereNull('parent_id')
            ->approved()
            ->oldest()
            ->with(['replies' => fn ($query) => $query->approved()]);
    }

    // How often it was read, per day (see ViewCounter)
    public function views()
    {
        return $this->hasMany(BlogView::class);
    }

    public function likes()
    {
        return $this->hasMany(BlogLike::class);
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

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function featuredImage()
    {
        return $this->belongsTo(CuratorMedia::class, 'featured_image_id');
    }

    /**
     * Only blogs visitors may see: status published and publish date reached (or not set).
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')
            ->where(fn (Builder $query) => $query
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByRaw('COALESCE(published_at, created_at) DESC');
    }

    // Reading order of a trip
    public function scopeOldestFirst(Builder $query): void
    {
        $query->orderByRaw('COALESCE(published_at, created_at) ASC');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && ($this->published_at === null || $this->published_at->isPast());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * A secret link to view a draft or scheduled blog, valid for two hours.
     */
    public function previewUrl(): string
    {
        return URL::temporarySignedRoute('blogs.preview', now()->addHours(2), ['blog' => $this->getKey()]);
    }

    /**
     * Where the blog sits on the travel map: its own place, else the centre of its first country.
     *
     * @return array{0: float, 1: float}|null
     */
    public function mapPosition(): ?array
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return [$this->latitude, $this->longitude];
        }

        $country = $this->countries->first(fn (Country $country) => $country->latitude !== null);

        return $country ? [$country->latitude, $country->longitude] : null;
    }

    /**
     * The intro to show above the story, or null when the story itself starts with it
     * (most blogs from WordPress do): then readers would read the same lines twice.
     */
    public function introAboveStory(): ?string
    {
        $intro = trim((string) $this->excerpt);

        if ($intro === '' || $intro === 'No excerpt') {
            return null;
        }

        $words = fn (string $text) => mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text)));
        // The intro is often the story's start cut off with "…", so compare the first 60 characters
        $start = mb_substr($words(rtrim($intro, '.… ')), 0, 60);

        return str_starts_with($words(BlogSearch::plainText($this->content)), $start) ? null : $intro;
    }

    /**
     * The tags on the photo: the country with the place ("India – Varanasi"), then any other countries.
     *
     * @return list<array{label: string, country: ?Country}>
     */
    public function placeTags(): array
    {
        $countries = $this->countries;

        if (blank($this->location_name)) {
            return $countries->map(fn (Country $c) => ['label' => $c->name, 'country' => $c])->all();
        }

        // "Varanasi, India": the part after the last comma is the country
        $parts = array_map('trim', explode(',', $this->location_name));
        $countryName = count($parts) > 1 ? array_pop($parts) : null;
        $place = implode(', ', $parts);

        // "Thailand" alone, or a place without a country: use the blog's country when it has one
        if (! $countryName) {
            $match = $countries->first(fn (Country $c) => mb_strtolower($c->name) === mb_strtolower($place));
            if ($match) {
                $countryName = $match->name;
                $place = null;
            } elseif ($countries->count() === 1) {
                $countryName = $countries->first()->name;
            }
        }

        $country = $countries->first(fn (Country $c) => mb_strtolower($c->name) === mb_strtolower((string) $countryName));

        $tags = [[
            'label' => collect([$countryName, $place])->filter()->join(' – '),
            'country' => $country,
        ]];

        foreach ($countries as $other) {
            if (! $country || ! $other->is($country)) {
                $tags[] = ['label' => $other->name, 'country' => $other];
            }
        }

        return $tags;
    }

    public function publishedDate(): Carbon
    {
        return $this->published_at ?? $this->created_at;
    }

    // The featured photo, else the first photo in the story, else the site's own placeholder
    public function coverUrl(int $width = 1200, int $height = 800, string $format = 'webp'): string
    {
        $photo = $this->coverMedia();

        return $photo
            ? ($format === 'webp' ? BlogImages::mediaUrl($photo, $width) : BlogImages::url($photo->path, $width, $format))
            : asset('images/cover-placeholder.svg');
    }

    // The cover in several widths, for a srcset (empty without a photo of its own)
    public function coverSrcset(array $widths): string
    {
        $photo = $this->coverMedia();

        return $photo ? BlogImages::srcset($photo, $widths) : '';
    }

    // Whether the blog has a photo of its own to show (a mail leaves the placeholder out)
    public function hasCover(): bool
    {
        return $this->coverMedia() !== null;
    }

    /**
     * The photo for the preview when the blog is shared (WhatsApp, Facebook, …): the cover as JPG,
     * which every app shows, 1200 wide, as a full address. Null without a photo of its own.
     */
    public function shareImageUrl(): ?string
    {
        return $this->hasCover() ? url($this->coverUrl(1200, 630, 'jpg')) : null;
    }

    /**
     * One or two sentences about the blog, for search engines and share previews.
     */
    public function shareDescription(): string
    {
        $text = filled($this->excerpt) && $this->excerpt !== 'No excerpt' ? $this->excerpt : BlogSearch::plainText($this->content);

        return Str::limit(trim(preg_replace('/\s+/', ' ', $text)), 200);
    }

    private ?CuratorMedia $coverMedia = null;

    private bool $coverLooked = false;

    private function coverMedia(): ?CuratorMedia
    {
        if (! $this->coverLooked) {
            $this->coverLooked = true;
            $this->coverMedia = $this->featuredImage ?? $this->firstPhotoInStory();
        }

        return $this->coverMedia;
    }

    // The first photo of a photo or gallery block in the story
    private function firstPhotoInStory(): ?CuratorMedia
    {
        foreach ($this->content['content'] ?? [] as $node) {
            if (($node['type'] ?? null) === 'customBlock' && in_array($node['attrs']['id'] ?? null, ['photo', 'gallery'], true)) {
                if ($media = BlogImages::media($node['attrs']['config']['media'] ?? null)->first()) {
                    return $media;
                }
            }
        }

        return null;
    }
}
