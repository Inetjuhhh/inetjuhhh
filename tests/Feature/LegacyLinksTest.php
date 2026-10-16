<?php

use App\Models\Blog;
use App\Models\Country;
use App\Models\LegacyLink;
use App\Models\User;
use App\Support\LegacyLinks\UploadKey;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->blog = Blog::create([
        'title' => 'Jutten of jatten', 'slug' => 'jutten-of-jatten', 'excerpt' => 'Intro', 'status' => 'published',
        'wordpress_id' => 2232, 'placed_by_id' => User::factory()->create()->id, 'content' => ['type' => 'doc', 'content' => []],
    ]);
});

function legacyLink(string $type, string $oldKey, int $targetId): void
{
    LegacyLink::create(['type' => $type, 'old_key' => $oldKey, 'target_id' => $targetId]);
}

it('sends an old blog link to the blog', function () {
    legacyLink(LegacyLink::POST, 'jutten-of-jatten', $this->blog->id);

    $this->get('/2019/01/12/jutten-of-jatten/')->assertRedirect('/blogs/jutten-of-jatten')->assertStatus(301);
});

it('still finds the blog when its link was changed later', function () {
    legacyLink(LegacyLink::POST, 'jutten-of-jatten', $this->blog->id);
    $this->blog->update(['slug' => 'jutten-op-ameland']);

    $this->get('/2019/01/12/jutten-of-jatten')->assertRedirect('/blogs/jutten-op-ameland');
});

it('sends a WordPress short link to the blog', function () {
    $this->get('/?p=2232')->assertRedirect('/blogs/jutten-of-jatten')->assertStatus(301);
});

it('sends every version of an old photo to the new one', function () {
    $media = Media::create(['disk' => 'public', 'directory' => 'wordpress/2019', 'visibility' => 'public', 'name' => 'foto',
        'path' => 'wordpress/2019/foto.webp', 'width' => 10, 'height' => 10, 'size' => 10, 'type' => 'image/webp', 'ext' => 'webp']);
    legacyLink(LegacyLink::UPLOAD, UploadKey::from('https://inetjuhhh.nl/wp-content/uploads/2019/01/Foto.jpg'), $media->id);

    foreach (['Foto.jpg', 'Foto-768x1024.jpg', 'Foto-scaled.jpg'] as $version) {
        $response = $this->get("/wp-content/uploads/2019/01/{$version}")->assertStatus(301);
        expect($response->headers->get('Location'))->toContain('wordpress/2019/foto.webp');
    }
});

it('sends old categories to a country, the map or the search', function () {
    $thailand = Country::create(['name' => 'Thailand', 'latitude' => 15, 'longitude' => 101]);
    $italy = Country::create(['name' => 'Italië', 'latitude' => 42, 'longitude' => 12]);

    $this->get('/category/blog/reizen/thailand/')->assertRedirect(route('blogs.blogCountry', $thailand->id));
    $this->get('/category/italie/')->assertRedirect(route('blogs.blogCountry', $italy->id));
    $this->get('/category/rome/')->assertRedirect(route('blogs.blogCountry', $italy->id));
    $this->get('/category/travel/')->assertRedirect('/kaart');
    $this->get('/category/familie/')->assertRedirect('/zoeken?q=Family');
    $this->get('/category/klein-leed/')->assertRedirect(route('search', ['q' => 'klein leed']));
    $this->get('/tag/wintersport/')->assertRedirect(route('search', ['q' => 'wintersport']));
});

it('sends old pages and page numbers on', function () {
    $this->get('/page/3/')->assertRedirect('/?page=3');
    $this->get('/travel/')->assertRedirect('/kaart');
});

it('gives a 404 for an old link that never existed', function () {
    $this->get('/2019/01/12/bestaat-niet/')->assertNotFound();
    $this->get('/wp-content/uploads/2019/01/onbekend.jpg')->assertNotFound();
});
