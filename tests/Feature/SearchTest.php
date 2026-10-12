<?php

use App\Models\Blog;
use App\Models\Country;
use App\Models\Trip;
use App\Models\User;
use App\Support\BlogSearch;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->author = User::factory()->create();
});

function searchable(string $title, array $attributes = [], array $paragraphs = []): Blog
{
    return Blog::create([
        'title' => $title, 'slug' => str($title)->slug(), 'excerpt' => 'No excerpt', 'status' => 'published',
        'placed_by_id' => test()->author->id,
        'content' => ['type' => 'doc', 'content' => array_map(fn ($text) => [
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ], $paragraphs)],
        ...$attributes,
    ]);
}

it('finds blogs by title, intro, place and the words in the story', function () {
    $alhambra = searchable('Het Alhambra met een peuter', ['location_name' => 'Granada, Spanje']);
    $tapas = searchable('Eten in Andalusië', [], ['In Granada krijg je bij elk drankje gratis tapas.']);
    searchable('Watervallen in IJsland');

    expect(BlogSearch::query('granada')->pluck('id')->all())->toEqualCanonicalizing([$alhambra->id, $tapas->id])
        ->and(BlogSearch::query('tapas')->pluck('id')->all())->toBe([$tapas->id]);
});

it('needs every word to match, and puts title matches first', function () {
    $inText = searchable('Een dag in de stad', [], ['We aten churros in Madrid.']);
    $inTitle = searchable('Churros in Madrid');
    searchable('Churros in Mexico');

    expect(BlogSearch::query('churros madrid')->pluck('id')->all())->toBe([$inTitle->id, $inText->id]);
});

it('finds blogs through their country and trip', function () {
    $blog = searchable('Zwarte stranden');
    $blog->countries()->attach(Country::create(['name' => 'IJsland', 'latitude' => 64, 'longitude' => -19]));
    $blog->update(['trip_id' => Trip::create(['name' => 'Babymoon 2021'])->id]);

    expect(BlogSearch::query('ijsland')->pluck('id')->all())->toBe([$blog->id])
        ->and(BlogSearch::query('babymoon')->pluck('id')->all())->toBe([$blog->id]);
});

it('never shows drafts in the search', function () {
    searchable('Geheim concept over Peru', ['status' => 'draft']);

    expect(BlogSearch::query('peru')->count())->toBe(0);
    $this->getJson('/zoeken/suggesties?q=peru')->assertJson(['total' => 0]);
});

it('does not match the markup of the editor', function () {
    searchable('Gewoon een verhaal', [], ['Een zin.']);

    // "paragraph" and "type" are in the stored JSON, but not in the story
    expect(BlogSearch::query('paragraph')->count())->toBe(0)
        ->and(BlogSearch::query('type')->count())->toBe(0);
});

it('suggests at most six blogs while typing', function () {
    foreach (range(1, 8) as $i) {
        searchable("Strand nummer {$i}");
    }

    $this->getJson('/zoeken/suggesties?q=strand')
        ->assertOk()
        ->assertJsonCount(6, 'results')
        ->assertJsonPath('total', 8)
        ->assertJsonStructure(['results' => [['title', 'url', 'place', 'date', 'cover']], 'places', 'didYouMean']);
});

it('waits for two letters before suggesting', function () {
    searchable('Strand');

    $this->getJson('/zoeken/suggesties?q=s')->assertJson(['results' => [], 'total' => 0]);
});

it('shows all matching blogs after Enter', function () {
    searchable('Snorkelen in Thailand');
    searchable('Treinreis door Thailand');
    searchable('Fjorden in Noorwegen');

    $this->get('/zoeken?q=thailand')
        ->assertOk()
        ->assertSee('2 verhalen over “thailand”', false)
        ->assertSee('Snorkelen in Thailand')
        ->assertSee('Treinreis door Thailand')
        ->assertDontSee('Fjorden in Noorwegen');
});

it('fixes small typos when nothing was found', function () {
    searchable('Snorkelen in Thailand');

    expect(BlogSearch::didYouMean('tailand'))->toBe('thailand');

    $this->get('/zoeken?q=tailand')
        ->assertOk()
        ->assertSee('Niets gevonden voor “tailand”', false)
        ->assertSee('Snorkelen in Thailand');

    $this->getJson('/zoeken/suggesties?q=tailand')->assertJson(['total' => 0, 'didYouMean' => 'thailand']);
});

it('says so kindly when nothing matches', function () {
    searchable('Snorkelen in Thailand');

    $this->get('/zoeken?q=xyzzy')->assertOk()->assertSee('Ik heb (nog) niets geschreven dat hierbij past.');
});

it('treats percent signs and underscores as normal characters', function () {
    searchable('100% genieten');
    searchable('Gewoon een titel');

    expect(BlogSearch::query('%')->count())->toBe(0)
        ->and(BlogSearch::query('100%')->count())->toBe(1);
});

it('has a working search bar on every page', function () {
    searchable('Snorkelen in Thailand');

    $this->get('/')->assertOk()
        ->assertSee('action="' . route('search') . '"', false)
        ->assertSee('name="q"', false);
});
