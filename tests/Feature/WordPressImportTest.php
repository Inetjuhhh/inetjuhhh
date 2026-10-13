<?php

use App\Mail\NewBlogPublished;
use App\Models\Blog;
use App\Models\Response;
use App\Models\Subscriber;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
| The whole import on a tiny export (tests/Fixtures/wordpress-export.xml) with the tricky parts of
| the real one. It runs with every `php artisan test`, so a change elsewhere (a field, a block, the
| admin) that would break the import shows up here, not on the day of the move.
*/

beforeEach(function () {
    Mail::fake();

    // Looking up places on the map, without the internet
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '38.7077', 'lon' => '-9.1366']])]);

    // A folder of our own: the real export, map and lists in storage/app/wordpress are never touched
    $this->directory = sys_get_temp_dir() . '/wp-import-' . uniqid();
    mkdir($this->directory);
    // WordPress puts invisible characters in video data; the import has to survive them
    $xml = str_replace('"codec"', "\"co\x00dec\"", file_get_contents(base_path('tests/Fixtures/wordpress-export.xml')));
    file_put_contents("{$this->directory}/export.xml", $xml);
    config(['wordpress-import.directory' => $this->directory]);

    $this->author = User::factory()->create(['email' => 'ine@example.com']);
    Subscriber::create(['name' => 'Femke', 'email' => 'femke@example.com', 'confirmed_at' => now()]);
});

afterEach(function () {
    array_map('unlink', glob("{$this->directory}/*"));
    rmdir($this->directory);
});

function runImport(array $options = []): void
{
    test()->artisan('wordpress:import', ['--author' => 'ine@example.com', ...$options])->assertSuccessful();
}

it('changes nothing in a dry run', function () {
    runImport(['--dry-run' => true]);

    expect(Blog::count())->toBe(0)->and(Response::count())->toBe(0);
});

it('imports the blogs with their titles, places, trips and categories', function () {
    runImport();

    expect(Blog::count())->toBe(4);

    $day1 = Blog::where('wordpress_id', 100)->first();
    expect($day1->title)->toBe('Dag 1 in de stad')
        ->and($day1->location_name)->toBe('Lissabon, Portugal')
        ->and($day1->latitude)->toBe(38.7077)
        ->and($day1->placeTags()[0]['label'])->toBe('Portugal – Lissabon')
        ->and($day1->categories->pluck('name')->all())->toBe(['Travel'])
        ->and($day1->tags->pluck('name')->all())->toBe(['citytrip'])
        ->and($day1->published_at->format('Y-m-d H:i'))->toBe('2023-12-27 09:00');

    // Two blogs about Lissabon, two days apart: a city trip; the day home borrows the trip's place
    $trip = Trip::sole();
    expect($trip->name)->toBe('Lissabon 2023')
        ->and($trip->kind)->toBe(Trip::KIND_CITY)
        ->and($trip->blogs()->count())->toBe(2)
        ->and(Blog::where('wordpress_id', 101)->value('location_name'))->toBe('Lissabon, Portugal');

    // A classic post without blocks gets its paragraphs from the empty lines
    $recipe = Blog::where('wordpress_id', 102)->first();
    expect(collect($recipe->content['content'])->where('type', 'paragraph')->count())->toBe(2)
        ->and($recipe->categories->pluck('name')->all())->toBe(['Food']);

    // A draft stays a draft, and gets a link of its own
    $draft = Blog::where('wordpress_id', 103)->first();
    expect($draft->status)->toBe('draft')->and($draft->slug)->toBe('nog-een-concept');
});

it('brings the comments over with your reply under the comment it answers', function () {
    runImport();

    $papa = Response::where('wordpress_comment_id', 1)->first();
    expect($papa->replies()->pluck('response')->all())->toBe(['Dankjewel!'])
        ->and(Response::where('wordpress_comment_id', 3)->value('status'))->toBe(Response::PENDING);
});

it('mails nobody about the old blogs', function () {
    runImport();

    Mail::assertNotQueued(NewBlogPublished::class);
    expect(Blog::whereNull('subscribers_notified_at')->where('status', 'published')->count())->toBe(0);
});

it('writes the map for the old links', function () {
    runImport();

    $map = json_decode(file_get_contents("{$this->directory}/redirects.json"), true);
    expect($map['posts'])->toBe(['dag-1-in-de-stad' => 100, 'back-in-holland' => 101, 'rabarbermoes' => 102]);

    $this->get('/2023/12/27/dag-1-in-de-stad/')->assertRedirect('/blogs/dag-1-in-de-stad');
});

it('can run again without duplicates', function () {
    runImport();
    runImport();

    expect(Blog::count())->toBe(4)
        ->and(Response::count())->toBe(3)
        ->and(Trip::count())->toBe(1);
});
