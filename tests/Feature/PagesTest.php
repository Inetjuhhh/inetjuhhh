<?php

use App\Filament\Pages\EditAboutPage;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| The Lifestyle category page and the fixed "Ik & wij" page, with its admin page and its import.
*/

function aboutText(string $text): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
}

function lifestyleBlog(string $title, array $categories, string $status = 'published'): Blog
{
    $blog = Blog::create([
        'title' => $title, 'slug' => str($title)->slug(), 'excerpt' => 'Intro', 'status' => $status,
        'placed_by_id' => User::first()->id, 'content' => aboutText('Hallo'),
    ]);
    $blog->categories()->sync(collect($categories)->pluck('id'));

    return $blog;
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('gives a category an address from its name', function () {
    expect(Category::create(['name' => 'Ik & Sam'])->slug)->toBe('ik-sam');
});

it('shows the published Lifestyle blogs on the Lifestyle page', function () {
    $lifestyle = Category::create(['name' => 'Lifestyle']);
    $travel = Category::create(['name' => 'Travel']);
    lifestyleBlog('Sam wordt drie', [$lifestyle]);
    lifestyleBlog('Lissabon', [$travel]);
    lifestyleBlog('Nog een concept', [$lifestyle], status: 'draft');

    $this->get(route('blogs.category', 'lifestyle'))
        ->assertOk()
        ->assertSee('Sam wordt drie')
        ->assertDontSee('Lissabon')
        ->assertDontSee('Nog een concept');
});

it('links Lifestyle and Ik & wij in the menu', function () {
    Category::create(['name' => 'Lifestyle']);

    $this->get('/')
        ->assertSee(route('blogs.category', 'lifestyle'), false)
        ->assertSee(route('about'), false)
        ->assertDontSee('>Familie<', false);
});

it('shows the text of Ik & wij', function () {
    Page::about()->update(['content' => aboutText('Mijn naam is Ine.')]);

    $this->get('/ik-en-wij')->assertOk()->assertSee('Ik &amp; wij', false)->assertSee('Mijn naam is Ine.');
});

it('sends the old WordPress about page and categories to the new pages', function () {
    $this->get('/over-ine')->assertRedirect('/ik-en-wij');
    $this->get('/category/sam/')->assertRedirect('/categorie/lifestyle');
    $this->get('/category/lifestyle/')->assertRedirect('/categorie/lifestyle');
});

it('lets the text of Ik & wij be edited in the admin, and nothing else', function () {
    $this->actingAs($this->user);

    Livewire::test(EditAboutPage::class)
        ->assertOk()
        ->set('data.title', 'Wij drieën')
        ->set('data.content', aboutText('Sam is nu vijf.'))
        ->call('save')
        ->assertHasNoErrors();

    expect(Page::count())->toBe(1)
        ->and(Page::about()->title)->toBe('Wij drieën')
        ->and(Page::about()->renderContent())->toContain('Sam is nu vijf.');
});

describe('wordpress:import-about', function () {
    beforeEach(function () {
        Storage::fake('public');

        $image = imagecreatetruecolor(40, 30);
        ob_start();
        imagejpeg($image);
        Http::fake(['inetjuhhh.nl/*' => Http::response(ob_get_clean())]);

        $attachment = fn (int $id) => "<item><title>foto {$id}</title><wp:post_id>{$id}</wp:post_id><wp:post_type><![CDATA[attachment]]></wp:post_type>"
            . "<wp:attachment_url>https://inetjuhhh.nl/wp-content/uploads/2019/10/foto-{$id}.jpg</wp:attachment_url></item>";
        $photo = fn (int $id) => "<a href=\"x\"><img class=\"alignnone size-medium wp-image-{$id}\" src=\"https://inetjuhhh.nl/wp-content/uploads/2019/10/foto-{$id}-300x225.jpg\" /></a>";

        $page = '<p><strong>Welkom op mijn blog!</strong></p><p>Mijn naam is Ine.</p><p>Liefs, Ine</p>'
            . '<p>' . $photo(1) . $photo(2) . $photo(3) . '</p><p>Familiedag</p><p>' . $photo(4) . '</p>';

        $this->directory = sys_get_temp_dir() . '/wp-about-' . uniqid();
        mkdir($this->directory);
        file_put_contents("{$this->directory}/export.xml", '<?xml version="1.0" encoding="UTF-8" ?>'
            . '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:wp="http://wordpress.org/export/1.2/"><channel>'
            . $attachment(1) . $attachment(2) . $attachment(3) . $attachment(4)
            . '<item><title>Over mij en ons</title><wp:post_id>9</wp:post_id><wp:post_name>over-ine</wp:post_name><wp:post_type><![CDATA[page]]></wp:post_type>'
            . '<content:encoded><![CDATA[' . $page . ']]></content:encoded><excerpt:encoded></excerpt:encoded></item>'
            . '</channel></rss>');
        config(['wordpress-import.directory' => $this->directory]);
    });

    afterEach(function () {
        array_map('unlink', glob("{$this->directory}/*"));
        rmdir($this->directory);
    });

    it('brings over the text and the first three photos as a gallery', function () {
        $this->artisan('wordpress:import-about')->assertSuccessful();

        $content = Page::about()->content['content'];
        $gallery = end($content);

        expect(collect($content)->where('type', 'paragraph'))->toHaveCount(3)
            ->and($gallery['attrs']['id'])->toBe('gallery')
            ->and($gallery['attrs']['config']['media'])->toHaveCount(3)
            ->and(Page::about()->renderContent())->toContain('Mijn naam is Ine.')->not->toContain('Familiedag');
    });

    it('keeps what was written in the admin unless asked to overwrite it', function () {
        Page::about()->update(['content' => aboutText('Nieuwe tekst')]);

        $this->artisan('wordpress:import-about')->assertSuccessful();
        expect(Page::about()->renderContent())->toContain('Nieuwe tekst');

        $this->artisan('wordpress:import-about', ['--force' => true])->assertSuccessful();
        expect(Page::about()->renderContent())->toContain('Mijn naam is Ine.');
    });
});
