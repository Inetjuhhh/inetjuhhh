<?php

use App\Filament\Resources\BlogResource\Pages\CreateBlog;
use App\Filament\Resources\BlogResource\Pages\EditBlog;
use App\Filament\Resources\BlogResource\Pages\ListBlogs;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function doc(array ...$nodes): array
{
    return ['type' => 'doc', 'content' => $nodes];
}

function paragraph(string $text): array
{
    return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
}

function block(string $id, array $config): array
{
    return ['type' => 'customBlock', 'attrs' => ['id' => $id, 'config' => $config]];
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the blog pages in the admin', function () {
    $blog = Blog::create([
        'title' => 'Met galerij', 'slug' => 'met-galerij', 'excerpt' => 'Intro', 'status' => 'draft',
        'placed_by_id' => $this->user->id,
        'content' => doc(paragraph('Hallo'), block('tip', ['type' => 'tip', 'text' => 'Neem water mee'])),
    ]);

    Livewire::test(ListBlogs::class)->assertOk()->assertCanSeeTableRecords([$blog]);
    Livewire::test(CreateBlog::class)->assertOk();
    Livewire::test(EditBlog::class, ['record' => $blog->id])->assertOk();
});

it('saves a draft with only a title', function () {
    Livewire::test(CreateBlog::class)
        ->fillForm(['title' => 'Half af verhaal', 'slug' => 'half-af-verhaal', 'status' => 'draft'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Blog::where('slug', 'half-af-verhaal')->first())
        ->status->toBe('draft')
        ->placed_by_id->toBe($this->user->id);
});

it('needs the details before publishing and sets the publish date', function () {
    Livewire::test(CreateBlog::class)
        ->fillForm(['title' => 'Klaar', 'slug' => 'klaar', 'status' => 'published'])
        ->call('create')
        ->assertHasFormErrors(['excerpt', 'content', 'countries', 'categories']);

    $country = Country::create(['name' => 'Nepal']);
    $category = Category::create(['name' => 'Travel']);

    Livewire::test(CreateBlog::class)
        ->fillForm([
            'title' => 'Klaar', 'slug' => 'klaar', 'status' => 'published', 'excerpt' => 'Intro',
            'content' => doc(paragraph('Het verhaal')),
            'countries' => [$country->id], 'categories' => [$category->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $blog = Blog::where('slug', 'klaar')->first();
    expect($blog->published_at)->not->toBeNull()
        ->and($blog->isPublished())->toBeTrue()
        ->and($blog->countries->pluck('id')->all())->toBe([$country->id]);
});

it('autosaves a new draft into one record', function () {
    $page = Livewire::test(CreateBlog::class)
        ->fillForm(['title' => 'Onderweg geschreven', 'slug' => 'onderweg-geschreven'])
        ->call('autosave');

    expect(Blog::count())->toBe(1);
    $page->assertSet('autosavedAt', fn ($value) => filled($value));

    $page->fillForm(['excerpt' => 'Nog een zin erbij'])->call('autosave');
    $page->call('create')->assertHasNoFormErrors();

    expect(Blog::count())->toBe(1)
        ->and(Blog::first()->excerpt)->toBe('Nog een zin erbij');
});

it('does not autosave published blogs', function () {
    $blog = Blog::create([
        'title' => 'Live', 'slug' => 'live', 'excerpt' => 'Intro', 'status' => 'published',
        'placed_by_id' => $this->user->id, 'content' => doc(paragraph('Oud')),
    ]);

    Livewire::test(EditBlog::class, ['record' => $blog->id])
        ->fillForm(['title' => 'Half aangepast'])
        ->call('autosave');

    expect($blog->fresh()->title)->toBe('Live');
});

it('only shows published blogs whose date has passed', function () {
    $make = fn (string $slug, string $status, ?Carbon $date) => Blog::create([
        'title' => $slug, 'slug' => $slug, 'excerpt' => 'Intro', 'status' => $status, 'published_at' => $date,
        'placed_by_id' => $this->user->id, 'content' => doc(paragraph('Tekst')),
    ]);

    $live = $make('live', 'published', now()->subDay());
    $scheduled = $make('ingepland', 'published', now()->addDay());
    $draft = $make('concept', 'draft', null);

    $this->get('/blogs/live')->assertOk();
    $this->get('/blogs/ingepland')->assertNotFound();
    $this->get('/blogs/concept')->assertNotFound();
    $this->get("/blogs/{$live->id}")->assertRedirect('/blogs/live');

    $this->get($draft->previewUrl())->assertOk()->assertSee('Voorbeeld');
    $this->get($scheduled->previewUrl())->assertOk();
    $this->get("/blogs/preview/{$draft->id}")->assertForbidden();

    $this->get('/')->assertSee('live')->assertDontSee('ingepland')->assertDontSee('concept');
});

it('renders blocks on the website and keeps the text safe', function () {
    $blog = Blog::create([
        'title' => 'Blokken', 'slug' => 'blokken', 'excerpt' => 'Intro', 'status' => 'published',
        'placed_by_id' => $this->user->id,
        'content' => doc(
            paragraph('<script>alert(1)</script>Gewone tekst'),
            block('tip', ['type' => 'warning', 'title' => 'Let op!', 'text' => 'Sneaker waves']),
            block('video', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']),
            block('map', ['place' => 'Ghorepani, Nepal', 'zoom' => '12']),
            block('practical-info', ['title' => 'Praktisch', 'items' => [['label' => 'Budget', 'value' => '€40 per dag']]]),
        ),
    ]);

    $html = $blog->renderContent();

    expect($html)
        ->toContain('Gewone tekst')
        ->not->toContain('<script>')
        ->toContain('Sneaker waves')
        ->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->toContain('maps.google.com/maps?q=Ghorepani')
        ->toContain('€40 per dag')
        ->not->toContain('renderedblock');
});
