<?php

use App\Mail\NewBlogPublished;
use App\Models\Blog;
use App\Models\Subscriber;
use App\Models\User;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
});

function photo(string $name): Media
{
    return Media::create(['disk' => 'public', 'directory' => 'wordpress/2024', 'visibility' => 'public', 'name' => $name,
        'path' => "wordpress/2024/{$name}.webp", 'width' => 10, 'height' => 10, 'size' => 10, 'type' => 'image/webp', 'ext' => 'webp']);
}

function storyWithBlocks(array $blocks, array $attributes = []): Blog
{
    return Blog::create([
        'title' => 'Sevilla', 'slug' => str()->random(8), 'excerpt' => 'Intro', 'status' => 'published',
        'placed_by_id' => test()->user->id, 'content' => ['type' => 'doc', 'content' => $blocks], ...$attributes,
    ]);
}

it('uses the featured photo first', function () {
    $blog = storyWithBlocks([], ['featured_image_id' => photo('uitgelicht')->id]);

    expect($blog->coverUrl())->toContain('uitgelicht.webp')->and($blog->hasCover())->toBeTrue();
});

it('uses the first photo in the story when there is no featured photo', function () {
    $blog = storyWithBlocks([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hallo']]],
        ['type' => 'customBlock', 'attrs' => ['id' => 'gallery', 'config' => ['media' => [photo('plein')->id, photo('kathedraal')->id]]]],
    ]);

    expect($blog->coverUrl())->toContain('plein.webp');
});

it('shows its own placeholder, never a stranger\'s photo, when there is no photo at all', function () {
    $blog = storyWithBlocks([]);

    expect($blog->coverUrl())->toEndWith('/images/cover-placeholder.svg')
        ->and($blog->coverUrl())->not->toContain('picsum')
        ->and($blog->hasCover())->toBeFalse();
});

it('leaves the photo out of the mail when the blog has none', function () {
    $mail = new NewBlogPublished(storyWithBlocks([]), Subscriber::create(['name' => 'Femke', 'email' => 'femke@example.com', 'confirmed_at' => now()]));

    $mail->assertDontSeeInHtml('cover-placeholder')->assertSeeInHtml('Lees het verhaal');
});
