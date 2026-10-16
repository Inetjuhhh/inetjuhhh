<?php

use App\Filament\Widgets\BlogStats;
use App\Filament\Widgets\BlogsPerYear;
use App\Filament\Widgets\PopularBlogs;
use App\Filament\Widgets\ViewsChart;
use App\Filament\Widgets\WaitingComments;
use App\Models\Blog;
use App\Models\BlogView;
use App\Models\Response;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1';

beforeEach(function () {
    Notification::fake();

    $this->author = User::factory()->create();
    $this->blog = Blog::create([
        'title' => 'Poon Hill', 'slug' => 'poon-hill', 'excerpt' => 'Intro', 'status' => 'published',
        'published_at' => now()->subDay(),
        'placed_by_id' => $this->author->id, 'content' => ['type' => 'doc', 'content' => []],
    ]);
});

it('counts a reader once a day', function () {
    $this->withHeader('User-Agent', BROWSER)->get('/blogs/poon-hill')->assertOk();
    $this->withHeader('User-Agent', BROWSER)->get('/blogs/poon-hill')->assertOk();

    expect(BlogView::sole()->views)->toBe(1);

    // A different reader counts as well
    $this->withHeader('User-Agent', BROWSER)->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get('/blogs/poon-hill');
    expect(BlogView::sole()->views)->toBe(2);

    // ...and the next day the first reader counts again, on a new row
    $this->travel(1)->day();
    $this->withHeader('User-Agent', BROWSER)->get('/blogs/poon-hill');
    expect(BlogView::count())->toBe(2)->and(BlogView::sum('views'))->toBe(3);
});

it('does not count search engines, the author or previews', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get('/blogs/poon-hill')->assertOk();
    $this->withHeader('User-Agent', '')->get('/blogs/poon-hill')->assertOk();

    $this->actingAs($this->author)->withHeader('User-Agent', BROWSER)->get('/blogs/poon-hill')->assertOk();
    auth()->logout();

    $draft = Blog::create([
        'title' => 'Concept', 'slug' => 'concept', 'excerpt' => 'Intro', 'status' => 'draft',
        'placed_by_id' => $this->author->id, 'content' => ['type' => 'doc', 'content' => []],
    ]);
    $this->withHeader('User-Agent', BROWSER)->get(URL::signedRoute('blogs.preview', $draft))->assertOk();

    expect(BlogView::count())->toBe(0);
});

it('shows the statistics on the dashboard', function () {
    BlogView::insert([
        ['blog_id' => $this->blog->id, 'date' => today()->toDateString(), 'views' => 12],
        ['blog_id' => $this->blog->id, 'date' => today()->subDays(40)->toDateString(), 'views' => 4],
    ]);
    $this->actingAs($this->author);

    Livewire::test(BlogStats::class)->assertOk()->assertSee('Gelezen · 30 dagen')->assertSee('200% meer');
    Livewire::test(ViewsChart::class)->assertOk()->set('filter', '7')->assertOk();
    Livewire::test(BlogsPerYear::class)->assertOk();
    Livewire::test(PopularBlogs::class)->assertOk()->assertCanSeeTableRecords([$this->blog]);

    config(['blog.admin_emails' => [$this->author->email]]);
    // The page itself (the widgets load in one by one after it)
    $this->get('/admin')->assertOk();
});

it('approves a waiting comment from the dashboard', function () {
    $comment = Response::create([
        'blog_id' => $this->blog->id, 'response' => 'Wat mooi!', 'name' => 'Femke', 'email' => 'femke@example.com',
    ]);
    $this->actingAs($this->author);

    Livewire::test(WaitingComments::class)
        ->assertCanSeeTableRecords([$comment])
        ->callTableAction('approve', $comment);

    expect($comment->fresh()->status)->toBe(Response::APPROVED);
    Livewire::test(WaitingComments::class)->assertSee('Niets te beoordelen');
});
