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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
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

it('counts a reader once a day, after reading', function () {
    // Opening the page alone doesn't count: the page itself reports a read after 15 seconds
    $this->withHeader('User-Agent', BROWSER)->get('/blogs/poon-hill')->assertOk()->assertSee('poon-hill\/gelezen', false);
    expect(BlogView::count())->toBe(0);

    $this->withHeader('User-Agent', BROWSER)->postJson('/blogs/poon-hill/gelezen')->assertNoContent();
    $this->withHeader('User-Agent', BROWSER)->postJson('/blogs/poon-hill/gelezen')->assertNoContent();

    expect(BlogView::sole()->views)->toBe(1);

    // A different reader counts as well
    $this->withHeader('User-Agent', BROWSER)->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->postJson('/blogs/poon-hill/gelezen');
    expect(BlogView::sole()->views)->toBe(2);

    // ...and the next day the first reader counts again, on a new row
    $this->travel(1)->day();
    $this->withHeader('User-Agent', BROWSER)->postJson('/blogs/poon-hill/gelezen');
    expect(BlogView::count())->toBe(2)->and(BlogView::sum('views'))->toBe(3);
});

it('does not count bots, the author, her devices or previews', function () {
    foreach (['Mozilla/5.0 (compatible; Googlebot/2.1)', 'Mozilla/5.0 (compatible; GPTBot/1.2)', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0)', 'python-requests/2.31', ''] as $agent) {
        $this->withHeader('User-Agent', $agent)->postJson('/blogs/poon-hill/gelezen')->assertNoContent();
    }

    $this->actingAs($this->author)->withHeader('User-Agent', BROWSER)->postJson('/blogs/poon-hill/gelezen');
    auth()->logout();

    // A device she once used the admin on, also after logging out
    $this->withHeader('User-Agent', BROWSER)->withCookie(\App\Support\ViewCounter::OWNER_COOKIE, '1')->withCredentials()->postJson('/blogs/poon-hill/gelezen');

    $draft = Blog::create([
        'title' => 'Concept', 'slug' => 'concept', 'excerpt' => 'Intro', 'status' => 'draft',
        'placed_by_id' => $this->author->id, 'content' => ['type' => 'doc', 'content' => []],
    ]);
    $this->withHeader('User-Agent', BROWSER)->get(URL::signedRoute('blogs.preview', $draft))->assertOk()->assertDontSee('\/gelezen', false);
    $this->withHeader('User-Agent', BROWSER)->postJson('/blogs/concept/gelezen')->assertNoContent();

    expect(BlogView::count())->toBe(0);
});

it('marks a device once its owner uses the admin', function () {
    $this->actingAs(User::factory()->create(['email' => config('blog.admin_emails')[0]]))->get('/admin')->assertCookie(\App\Support\ViewCounter::OWNER_COOKIE);
});

it('keeps where readers came from, and nothing about them', function () {
    $this->withHeader('User-Agent', BROWSER)->postJson('/blogs/poon-hill/gelezen', ['referrer' => 'https://www.google.nl/']);
    $this->withHeader('User-Agent', BROWSER)->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->postJson('/blogs/poon-hill/gelezen', ['referrer' => 'https://l.facebook.com/l.php?u=x']);
    $this->withHeader('User-Agent', BROWSER)->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->postJson('/blogs/poon-hill/gelezen', ['bron' => 'nieuwsbrief']);
    $this->withHeader('User-Agent', BROWSER)->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])->postJson('/blogs/poon-hill/gelezen');

    expect(DB::table('blog_view_stats')->orderBy('source')->pluck('source')->all())->toBe(['direct', 'facebook', 'google', 'nieuwsbrief'])
        ->and(Schema::getColumnListing('blog_view_stats'))->not->toContain('ip')
        ->and(\App\Support\ViewCounter::countingSince())->toBe(now()->toDateString());
});

it('tells the kind of source from the page before', function (?string $referrer, ?string $mark, string $source) {
    expect(\App\Support\ReadSource::from($referrer, $mark, 'inetjuhhh.nl'))->toBe($source);
})->with([
    ['https://www.google.com/search?q=x', null, 'google'],
    ['https://duckduckgo.com/', null, 'zoekmachine'],
    ['https://lm.facebook.com/l.php', null, 'facebook'],
    ['https://l.instagram.com/?u=x', null, 'instagram'],
    ['https://inetjuhhh.nl/categorie/reizen', null, 'eigen-site'],
    ['https://www.inetjuhhh.nl/', null, 'eigen-site'],
    ['https://example.org/', null, 'overig'],
    ['', null, 'direct'],
    ['https://mail.google.com/', 'nieuwsbrief', 'nieuwsbrief'],
]);

it('looks up the country and city of a reader on the server itself', function () {
    if (! \App\Support\ReaderLocation::available()) {
        $this->markTestSkipped('No location file (php artisan stats:update-locations)');
    }

    $this->withHeader('User-Agent', BROWSER)->withServerVariables(['REMOTE_ADDR' => '145.97.1.1'])->postJson('/blogs/poon-hill/gelezen');

    $row = DB::table('blog_view_stats')->sole();
    expect($row->country)->toBe('NL')->and($row->city)->not->toBe('')
        ->and(\App\Support\ReaderLocation::countryName('NL'))->toBe('Nederland');
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
    Livewire::test(PopularBlogs::class)->assertOk()->assertSee('Poon Hill');

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
