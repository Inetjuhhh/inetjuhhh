<?php

use App\Models\Blog;
use App\Models\Country;
use App\Models\User;
use App\Support\WordPress\WordPressUploads;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    // A folder of our own, so the real one in storage/app/wordpress is never touched
    $this->directory = sys_get_temp_dir() . '/wp-redirects-' . uniqid();
    mkdir($this->directory);
    $this->mapFile = "{$this->directory}/redirects.json";
    config(['wordpress-import.directory' => $this->directory]);

    $this->blog = Blog::create([
        'title' => 'Jutten of jatten', 'slug' => 'jutten-of-jatten', 'excerpt' => 'Intro', 'status' => 'published',
        'wordpress_id' => 2232, 'placed_by_id' => User::factory()->create()->id, 'content' => ['type' => 'doc', 'content' => []],
    ]);
});

afterEach(function () {
    @unlink($this->mapFile);
    @rmdir($this->directory);
});

function redirectMap(array $posts = [], array $uploads = []): void
{
    file_put_contents(test()->mapFile, json_encode(['posts' => $posts, 'uploads' => $uploads]));
}

it('sends an old blog link to the blog', function () {
    redirectMap(['jutten-of-jatten' => 2232]);

    $this->get('/2019/01/12/jutten-of-jatten/')->assertRedirect('/blogs/jutten-of-jatten')->assertStatus(301);
});

it('still finds the blog when its link was changed later', function () {
    redirectMap(['jutten-of-jatten' => 2232]);
    $this->blog->update(['slug' => 'jutten-op-ameland']);

    $this->get('/2019/01/12/jutten-of-jatten')->assertRedirect('/blogs/jutten-op-ameland');
});

it('sends a WordPress short link to the blog', function () {
    $this->get('/?p=2232')->assertRedirect('/blogs/jutten-of-jatten')->assertStatus(301);
});

it('sends every version of an old photo to the new one', function () {
    $media = Media::create(['disk' => 'public', 'directory' => 'wordpress/2019', 'visibility' => 'public', 'name' => 'foto',
        'path' => 'wordpress/2019/foto.webp', 'width' => 10, 'height' => 10, 'size' => 10, 'type' => 'image/webp', 'ext' => 'webp']);
    redirectMap(uploads: [WordPressUploads::key('https://inetjuhhh.nl/wp-content/uploads/2019/01/Foto.jpg') => $media->id]);

    foreach (['Foto.jpg', 'Foto-768x1024.jpg', 'Foto-scaled.jpg'] as $version) {
        $response = $this->get("/wp-content/uploads/2019/01/{$version}")->assertStatus(301);
        expect($response->headers->get('Location'))->toContain('wordpress/2019/foto.webp');
    }
});

it('sends old categories to a country, the map or the search', function () {
    $thailand = Country::create(['name' => 'Thailand', 'latitude' => 15, 'longitude' => 101]);

    $this->get('/category/blog/reizen/thailand/')->assertRedirect(route('blogs.blogCountry', $thailand->id));
    $this->get('/category/travel/')->assertRedirect(route('blogs.map'));
    $this->get('/category/familie/')->assertRedirect(route('search', ['q' => 'Family']));
    $this->get('/tag/wintersport/')->assertRedirect(route('search', ['q' => 'wintersport']));
});

it('sends old pages and page numbers on', function () {
    $this->get('/page/3/')->assertRedirect('/?page=3');
    $this->get('/travel/')->assertRedirect('/kaart');
});

it('gives a 404 for an old link that never existed', function () {
    redirectMap();

    $this->get('/2019/01/12/bestaat-niet/')->assertNotFound();
    $this->get('/wp-content/uploads/2019/01/onbekend.jpg')->assertNotFound();
});
