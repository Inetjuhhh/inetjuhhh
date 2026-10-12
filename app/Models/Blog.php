<?php

namespace App\Models;

use App\Filament\Blocks\BlogBlock;
use App\Support\BlogImages;
use App\Support\BlogSearch;
use App\Support\Geocoder;
use App\Support\NewBlogNotifier;
use App\Support\RenderedBlocks;
use Awcodes\Curator\Models\Media as CuratorMedia;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Blog extends Model implements HasMedia
{
    use InteractsWithMedia;

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

        // Mail the subscribers the first time a blog goes online. Blogs planned for later are
        // picked up by the blogs:notify-subscribers command once their date has come.
        static::saved(function (Blog $blog) {
            if ($blog->isPublished() && $blog->subscribers_notified_at === null) {
                NewBlogNotifier::send($blog);
            }
        });
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

    public function publishedDate(): Carbon
    {
        return $this->published_at ?? $this->created_at;
    }

    // Featured image from the media library, else the first old upload, else a placeholder (stable per blog)
    public function coverUrl(int $width = 1200, int $height = 800, string $format = 'webp'): string
    {
        if ($this->featuredImage) {
            return BlogImages::url($this->featuredImage->path, $width, $format);
        }

        $legacy = $this->getMedia('blog_attachments')
            ->first(fn ($media) => str_starts_with($media->mime_type, 'image/'));

        return $legacy?->getUrl()
            ?? "https://picsum.photos/seed/blog-{$this->id}/{$width}/{$height}";
    }

    /**
     * The post content as safe HTML, including the editor blocks (galleries, maps, tips, ...).
     */
    public function renderContent(): string
    {
        if (blank($this->content)) {
            return '';
        }

        $stash = new RenderedBlocks();

        $html = RichContentRenderer::make($this->content)
            ->customBlocks(collect(BlogBlock::all())->mapWithKeys(fn ($block) => [$block => ['stash' => $stash]])->all())
            ->toHtml();

        // Serve images in the text resized as WebP instead of the original upload
        $html = preg_replace_callback(
            '~(<img\b[^>]*?\bsrc=")(?:https?://[^/"]+)?/storage/([^"]+\.(?:jpe?g|png|webp))(")~i',
            fn (array $m) => $m[1] . e(BlogImages::url(rawurldecode($m[2]))) . $m[3],
            $html,
        );

        return $stash->restore($html);
    }
}
