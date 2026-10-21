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

    // The list opens on the published blogs; drafts after choosing them (or clearing the filter)
    Livewire::test(ListBlogs::class)->assertOk()->assertCanNotSeeTableRecords([$blog])
        ->filterTable('status', 'draft')->assertCanSeeTableRecords([$blog])
        ->removeTableFilters()->assertCanSeeTableRecords([$blog]);
    Livewire::test(CreateBlog::class)->assertOk();
    Livewire::test(EditBlog::class, ['record' => $blog->id])->assertOk();
});

it('opens the editor through the link in the list', function () {
    $blog = Blog::create([
        'title' => 'Lentekriebels', 'slug' => 'lentekriebels', 'excerpt' => 'Intro', 'status' => 'published',
        'placed_by_id' => $this->user->id, 'content' => doc(paragraph('Hallo')),
    ]);

    // The blog's own route key is its slug; the admin uses the id, and the link has to agree
    $url = \App\Filament\Resources\BlogResource::getUrl('edit', ['record' => $blog]);

    expect($url)->toEndWith("/admin/blogs/{$blog->id}/edit");

    // Outside local development only the addresses in ADMIN_EMAILS get into the admin
    config(['blog.admin_emails' => [$this->user->email]]);
    $this->get($url)->assertOk();
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
        ->assertHasFormErrors(['excerpt', 'content', 'categories'])
        ->assertHasNoFormErrors(['countries']);

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

it('lists the published blogs first, newest publication on top', function () {
    $make = fn (string $title, string $when) => Blog::create([
        'title' => $title, 'slug' => str($title)->slug(), 'excerpt' => 'Intro', 'status' => 'published', 'published_at' => $when,
        'placed_by_id' => $this->user->id, 'content' => doc(paragraph('Hallo')),
    ]);
    $old = $make('Oud verhaal', '2020-05-01');
    $new = $make('Nieuw verhaal', '2026-05-01');
    $middle = $make('Middelste verhaal', '2023-05-01');
    // Edited just now, but published long ago: still low in the list
    $old->touch();

    Livewire::test(ListBlogs::class)->assertCanSeeTableRecords([$new, $middle, $old], inOrder: true);
});

it('deletes a blog with its countries, tags and comments, from the editor and from the list', function () {
    $country = Country::create(['name' => 'Italië']);
    $make = function (string $slug) use ($country) {
        $blog = Blog::create([
            'title' => ucfirst($slug), 'slug' => $slug, 'excerpt' => 'Intro', 'status' => 'published',
            'placed_by_id' => $this->user->id, 'content' => doc(paragraph('Hallo')),
        ]);
        $blog->countries()->attach($country);
        $blog->tags()->create(['name' => "Tag {$slug}"]);
        $blog->responses()->create(['name' => 'Femke', 'email' => 'femke@example.com', 'response' => 'Mooi!', 'status' => 'approved']);

        return $blog;
    };

    $first = $make('weg-via-editor');
    Livewire::test(EditBlog::class, ['record' => $first->id])->callAction('delete');

    $second = $make('weg-via-lijst');
    Livewire::test(ListBlogs::class)->callTableBulkAction('delete', [$second]);

    expect(Blog::count())->toBe(0)
        ->and(\Illuminate\Support\Facades\DB::table('blog_country')->count())->toBe(0)
        ->and(\App\Models\Response::count())->toBe(0)
        ->and(Country::find($country->id))->not->toBeNull();
});

it('publishes a lifestyle story without a country', function () {
    $category = Category::create(['name' => 'Lifestyle']);

    Livewire::test(CreateBlog::class)
        ->fillForm([
            'title' => 'Shovel vs. Ine', 'slug' => 'shovel-vs-ine', 'status' => 'published', 'excerpt' => 'Thuis',
            'content' => doc(paragraph('Het verhaal')), 'categories' => [$category->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $blog = Blog::where('slug', 'shovel-vs-ine')->first();
    expect($blog->isPublished())->toBeTrue()->and($blog->countries)->toBeEmpty();
    $this->get(route('blogs.show', $blog))->assertOk()->assertSee('Shovel vs. Ine');
});

it('shows the country and place of each blog in the list, and finds a blog by its place', function () {
    $india = Country::create(['name' => 'India']);
    $varanasi = Blog::create([
        'title' => 'Aan de Ganges', 'slug' => 'aan-de-ganges', 'excerpt' => 'Intro', 'status' => 'published',
        'published_at' => now()->subDay(), 'location_name' => 'Varanasi, India',
        'placed_by_id' => $this->user->id, 'content' => doc(paragraph('Hallo')),
    ]);
    $varanasi->countries()->attach($india);
    $other = Blog::create([
        'title' => 'Thuis', 'slug' => 'thuis', 'excerpt' => 'Intro', 'status' => 'published',
        'published_at' => now()->subDay(), 'placed_by_id' => $this->user->id, 'content' => doc(paragraph('Hallo')),
    ]);

    Livewire::test(ListBlogs::class)
        ->assertSee('India – Varanasi')
        ->searchTable('Varanasi')
        ->assertCanSeeTableRecords([$varanasi])
        ->assertCanNotSeeTableRecords([$other]);
});

it('shows every block of a long story once, also from the 11th block on', function () {
    $blocks = array_map(fn (int $i) => block('tip', ['type' => 'tip', 'text' => "Tip nummer {$i} klaar"]), range(1, 12));
    $blog = Blog::create([
        'title' => 'Lang verhaal', 'slug' => 'lang-verhaal', 'excerpt' => 'Intro', 'status' => 'published',
        'published_at' => now()->subDay(), 'placed_by_id' => $this->user->id, 'content' => doc(...$blocks),
    ]);

    $page = $this->get(route('blogs.show', $blog))->assertOk()->getContent();

    foreach (range(1, 12) as $i) {
        expect(substr_count($page, "Tip nummer {$i} klaar"))->toBe(1);
    }
    expect($page)->not->toContain('renderedblock');
});

it('opens the editor by clicking a blog in the list, and shows it on the site with the eye', function () {
    $published = Blog::create([
        'title' => 'Online verhaal', 'slug' => 'online-verhaal', 'excerpt' => 'Intro', 'status' => 'published',
        'published_at' => now()->subDay(), 'placed_by_id' => $this->user->id, 'content' => doc(paragraph('Hallo')),
    ]);

    Livewire::test(ListBlogs::class)
        ->assertSeeHtml('href="' . \App\Filament\Resources\BlogResource::getUrl('edit', ['record' => $published]) . '"')
        ->assertTableActionHasUrl('view', route('blogs.show', $published), $published)
        ->assertTableActionDoesNotExist('edit');
});
