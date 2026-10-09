<?php

namespace App\Models;

use App\Filament\Blocks\BlogBlock;
use App\Support\BlogImages;
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

    public function publishedDate(): Carbon
    {
        return $this->published_at ?? $this->created_at;
    }

    // Featured image from the media library, else the first old upload, else a placeholder (stable per blog)
    public function coverUrl(int $width = 1200, int $height = 800): string
    {
        if ($this->featuredImage) {
            return BlogImages::url($this->featuredImage->path, $width);
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
