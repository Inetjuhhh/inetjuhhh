<?php

use App\Filament\Pages\Statistics as StatisticsPage;
use App\Filament\Widgets\Continents;
use App\Filament\Widgets\PopularBlogs;
use App\Filament\Widgets\ReaderCountries;
use App\Filament\Widgets\ReadingDays;
use App\Filament\Widgets\ReadSources;
use App\Filament\Widgets\ReadsPerMonth;
use App\Filament\Widgets\SubscribersChart;
use App\Filament\Widgets\TopTrips;
use App\Models\Blog;
use App\Models\BlogLike;
use App\Models\Country;
use App\Models\Trip;
use App\Models\User;
use App\Support\Statistics;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
| The statistics on the dashboard and per blog.
*/

function statsBlog(string $title, ?Trip $trip = null, array $countries = []): Blog
{
    $blog = Blog::create([
        'title' => $title, 'slug' => str($title)->slug(), 'excerpt' => '', 'status' => 'published',
        'published_at' => now()->subDays(10), 'placed_by_id' => User::first()->id, 'trip_id' => $trip?->id,
        'content' => ['type' => 'doc', 'content' => []],
    ]);
    $blog->countries()->attach($countries);

    return $blog;
}

function statsReads(Blog $blog, int $views, ?string $date = null, string $country = 'NL', string $city = 'Utrecht', string $source = 'google'): void
{
    $date ??= today()->toDateString();
    DB::table('blog_views')->insert(['blog_id' => $blog->id, 'date' => $date, 'views' => $views]);
    DB::table('blog_view_stats')->insert(['blog_id' => $blog->id, 'date' => $date, 'country' => $country, 'city' => $city, 'source' => $source, 'views' => $views]);
}

function statsLikes(Blog $blog, int $count): void
{
    foreach (range(1, $count) as $i) {
        BlogLike::create(['blog_id' => $blog->id, 'visitor_hash' => "v{$blog->id}-{$i}", 'created_at' => now()]);
    }
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->italy = Country::create(['name' => 'Italië', 'continent' => 'Europa']);
    $this->peru = Country::create(['name' => 'Peru', 'continent' => 'Zuid-Amerika']);
    $this->trip = Trip::create(['name' => 'Italië 2026', 'starts_on' => '2026-07-20', 'ends_on' => '2026-08-10']);

    $this->tuscany = statsBlog('Toscaanse hemel', $this->trip, [$this->italy->id]);
    $this->rome = statsBlog('Rome in een dag', $this->trip, [$this->italy->id]);
    $this->andes = statsBlog('Condors in de Colca', null, [$this->peru->id]);

    statsReads($this->tuscany, 100);
    statsReads($this->rome, 40, source: 'facebook', city: 'Amsterdam');
    statsReads($this->andes, 30, country: 'BE', city: 'Gent', source: 'nieuwsbrief');
    statsLikes($this->tuscany, 5);
    statsLikes($this->rome, 8);
});

it('ranks the blogs by reads, likes or likes per 100 readers', function () {
    expect(Statistics::blogs('30', 'reads')->pluck('title')->all())->toBe(['Toscaanse hemel', 'Rome in een dag', 'Condors in de Colca'])
        ->and(Statistics::blogs('30', 'likes')->first()->title)->toBe('Rome in een dag')
        // Rome: 8 likes of 40 readers (20 per 100) beats Tuscany: 5 of 100
        ->and(Statistics::blogs('30', 'rate')->first()->rate)->toBe(20.0)
        ->and(Statistics::blogs('30')->firstWhere('title', 'Condors in de Colca')->rate)->toBe(0.0);

    // Too few readers for a fair rate
    statsReads(statsBlog('Bijna niet gelezen'), 5, country: 'DE');
    expect(Statistics::blogs('30')->firstWhere('title', 'Bijna niet gelezen')->rate)->toBeNull();

    Livewire::test(PopularBlogs::class)
        ->assertSeeInOrder(['Toscaanse hemel', 'Rome in een dag'])
        ->set('by', 'likes')->assertSeeInOrder(['Rome in een dag', 'Toscaanse hemel'])
        ->set('by', 'nonsense')->assertSet('by', 'reads');
});

it('counts only the period asked for', function () {
    statsReads($this->andes, 500, today()->subDays(100)->toDateString());

    expect(Statistics::blogs('30')->first()->title)->toBe('Toscaanse hemel')
        ->and(Statistics::blogs('365')->first()->title)->toBe('Condors in de Colca');
});

it('shows countries with their cities, and where readers came from', function () {
    $countries = Statistics::countries('30');

    expect($countries->pluck('name')->all())->toBe(['Nederland', 'België'])
        ->and(collect($countries->first()['cities'])->pluck('name')->all())->toBe(['Utrecht', 'Amsterdam'])
        ->and(Statistics::sources('30')->pluck('label')->all())->toBe(['Google', 'Facebook', 'Nieuwsbrief'])
        // Per blog
        ->and(Statistics::countries('30', $this->andes->id)->pluck('name')->all())->toBe(['België']);

    Livewire::test(ReaderCountries::class)->assertSee(['Nederland', 'Utrecht', 'DB-IP']);
    Livewire::test(ReadSources::class)->assertSee(['Google', '59%']);
});

it('shows the biggest trips and the continents', function () {
    $trip = Statistics::trips()->first();
    expect($trip->name)->toBe('Italië 2026')->and($trip->blogs)->toBe(2)->and($trip->reads)->toBe(140)->and($trip->likes)->toBe(13);

    expect(Statistics::continents()->map(fn ($c) => "{$c->name}:{$c->blogs}:{$c->reads}")->all())->toBe(['Europa:2:140', 'Zuid-Amerika:1:30']);

    Livewire::test(TopTrips::class)->assertSee('Italië 2026');
    Livewire::test(Continents::class)->assertSee(['Europa', 'Zuid-Amerika']);

    // A country without a continent is pointed out
    statsBlog('Ergens', null, [Country::create(['name' => 'Atlantis'])->id]);
    Livewire::test(Continents::class)->assertSee('Nog zonder continent: Atlantis');
});

it('draws the charts', function () {
    Livewire::test(ReadsPerMonth::class)->assertOk();
    Livewire::test(ReadingDays::class)->assertOk();
    Livewire::test(SubscribersChart::class)->assertOk();

    expect(array_sum(Statistics::weekdays()))->toBe(170);
});

it('lists the blogs to search, and shows one blog in detail', function () {
    Livewire::test(StatisticsPage::class)
        ->assertSeeInOrder(['Toscaanse hemel', 'Rome in een dag', 'Condors in de Colca'])
        ->set('search', 'colca')->assertSee('Condors in de Colca')->assertDontSee('Toscaanse hemel')
        ->set('search', '')->set('trip', $this->trip->id)->assertDontSee('Condors in de Colca')
        ->set('trip', null)->set('sort', 'likes')->assertSeeInOrder(['Rome in een dag', 'Toscaanse hemel']);

    Livewire::withQueryParams(['blog' => $this->rome->id])->test(StatisticsPage::class)
        ->assertSee(['Rome in een dag', 'Gelezen per dag', 'Amsterdam', 'Facebook', '20,0 per 100 lezers', '#2']);

    // A blog that isn't online (any more) is just the list again
    $this->rome->update(['status' => 'draft']);
    Livewire::withQueryParams(['blog' => $this->rome->id])->test(StatisticsPage::class)->assertSee('Toscaanse hemel');
});

it('shows 25 blogs at a time in the list', function () {
    foreach (range(1, 30) as $i) {
        statsBlog("Extra blog {$i}");
    }

    Livewire::test(StatisticsPage::class)
        ->assertSee('25 van 33 blogs')->assertSee('Toon meer')
        ->call('showMore')->assertSee('33 van 33 blogs')->assertDontSee('Toon meer')
        ->set('search', 'extra')->assertSee('25 van 30 blogs');
});

it('shows more popular blogs on the dashboard on request', function () {
    foreach (range(1, 12) as $i) {
        statsReads(statsBlog("Extra blog {$i}"), $i);
    }
    // Never read in this period: not in the ranking
    statsBlog('Niet gelezen');

    Livewire::test(PopularBlogs::class)
        ->assertSee('5 van 15 blogs')->assertSee('Toon meer')
        ->call('showMore')->assertSee('15 van 15 blogs')->assertDontSee('Toon meer')->assertDontSee('Niet gelezen')
        // Another order starts at the top five again
        ->set('by', 'likes')->assertSet('shown', 5)->assertSee('2 van 2 blogs');
});

it('lists all countries and cities, the first ones open', function () {
    foreach (['DE', 'FR', 'ES', 'PT', 'AT', 'CH', 'DK', 'SE', 'NO', 'FI'] as $code) {
        DB::table('blog_view_stats')->insert(['blog_id' => $this->andes->id, 'date' => today()->toDateString(), 'country' => $code, 'city' => "Stad {$code}", 'source' => 'google', 'views' => 1]);
    }
    foreach (range(1, 9) as $i) {
        DB::table('blog_view_stats')->insert(['blog_id' => $this->tuscany->id, 'date' => today()->toDateString(), 'country' => 'NL', 'city' => "Dorp {$i}", 'source' => 'google', 'views' => 1]);
    }

    Livewire::test(ReaderCountries::class)
        ->assertSee(['van 12 landen', 'Toon meer landen', 'Stad FI', 'Toon meer plaatsen', 'Dorp 9']);
});
