<?php

use App\Models\Blog;
use App\Models\User;

function adminBarBlog(array $attributes = []): Blog
{
    return Blog::create($attributes + [
        'title' => 'Op de site', 'slug' => 'op-de-site', 'excerpt' => 'Intro', 'status' => 'published',
        'published_at' => now()->subDay(), 'placed_by_id' => User::factory()->create()->id,
        'content' => ['type' => 'doc', 'content' => []],
    ]);
}

it('shows the admin a bar back to the admin, with a link to edit the story', function () {
    $blog = adminBarBlog();
    $this->actingAs(User::factory()->create(['email' => config('blog.admin_emails')[0]]));

    $this->get(route('blogs.show', $blog))
        ->assertSee('Naar de admin')
        ->assertSee("/admin/blogs/{$blog->id}/edit", escape: false)
        ->assertDontSee('nog niet online');

    $this->get(route('blogs.index'))->assertSee('Naar de admin')->assertDontSee('Bewerk dit verhaal');
});

it('says so when the admin looks at a preview of a draft', function () {
    $draft = adminBarBlog(['title' => 'Concept', 'slug' => 'concept', 'status' => 'draft', 'published_at' => null]);
    $this->actingAs(User::factory()->create(['email' => config('blog.admin_emails')[0]]));

    $this->get($draft->previewUrl())->assertOk()->assertSee('Bewerk dit verhaal')->assertSee('Voorbeeld: nog niet online');
});

it('never shows the bar to readers', function () {
    $this->get(route('blogs.show', adminBarBlog()))->assertOk()->assertDontSee('Naar de admin');
});

it('never shows the bar on the live site to an account that may not use the admin', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->actingAs(User::factory()->create(['email' => 'iemand@example.com']));

    $this->get(route('blogs.show', adminBarBlog()))->assertDontSee('Naar de admin');
});
