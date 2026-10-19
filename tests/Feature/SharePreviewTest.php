<?php

use App\Models\Blog;
use App\Models\User;
use App\Support\Avatar;
use App\View\Components\AppLayout;
use Awcodes\Curator\Models\Media;

// What WhatsApp, Facebook and LinkedIn read: the <meta property="og:…"> tags of a page
function shareTags(string $html): array
{
    preg_match_all('~<meta (?:property|name)="((?:og|article|twitter):[a-z_:]+)" content="([^"]*)"~', $html, $m, PREG_SET_ORDER);

    return collect($m)->mapWithKeys(fn ($tag) => [$tag[1] => html_entity_decode($tag[2])])->all();
}

it('shows a blog with its photo, title and intro when it is shared', function () {
    $photo = Media::create(['disk' => 'public', 'directory' => 'uitgelicht', 'visibility' => 'public', 'name' => 'monaco',
        'path' => 'uitgelicht/monaco.webp', 'width' => 1600, 'height' => 1000, 'size' => 10, 'type' => 'image/webp', 'ext' => 'webp']);
    $blog = Blog::create([
        'title' => 'Bizarre luxe, in lagen gebouwd', 'slug' => 'bizarre-luxe', 'status' => 'published',
        'excerpt' => 'En nog een pinnetje om in onze wereldkaart toe te voegen: Monaco.',
        'featured_image_id' => $photo->id, 'placed_by_id' => User::factory()->create()->id,
        'content' => ['type' => 'doc', 'content' => []], 'published_at' => '2026-08-05 10:25:00',
    ]);

    $html = $this->get(route('blogs.show', $blog))->assertOk()->getContent();
    $tags = shareTags($html);

    expect($tags['og:type'])->toBe('article')
        ->and($tags['og:title'])->toBe('Bizarre luxe, in lagen gebouwd')
        ->and($tags['og:description'])->toBe('En nog een pinnetje om in onze wereldkaart toe te voegen: Monaco.')
        ->and($tags['og:url'])->toBe(route('blogs.show', $blog))
        ->and($tags['twitter:card'])->toBe('summary_large_image')
        ->and($tags['article:published_time'])->toStartWith('2026-08-05');

    // A full address of a JPG: every app can show that
    expect($tags['og:image'])->toStartWith('http')->toContain('monaco.webp')->toContain('fm=jpg')->toContain('w=1200');
    expect($html)->toContain('<meta name="description" content="En nog een pinnetje')
        ->not->toContain('name="robots"');
});

it('uses the avatar and a general description on other pages', function () {
    $tags = shareTags($this->get('/')->assertOk()->getContent());

    expect($tags['og:type'])->toBe('website')
        ->and($tags['og:description'])->toBe(AppLayout::DESCRIPTION)
        ->and($tags['og:image'])->toBe(Avatar::url(512));
});

it('keeps previews of drafts and search results out of search engines', function () {
    $draft = Blog::create(['title' => 'Concept', 'slug' => 'concept', 'status' => 'draft', 'excerpt' => '',
        'placed_by_id' => User::factory()->create()->id, 'content' => ['type' => 'doc', 'content' => []]]);

    $this->get($draft->previewUrl())->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $this->get('/zoeken?q=strand')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});
