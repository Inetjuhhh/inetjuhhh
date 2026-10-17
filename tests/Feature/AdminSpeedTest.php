<?php

use App\Filament\Blocks\GalleryBlock;
use App\Filament\Blocks\PhotoBlock;
use App\Filament\InitialsAvatar;
use App\Models\User;
use Awcodes\Curator\Models\Media;

/*
| What keeps the admin light: no picture from an outside service, small photo previews in the editor.
*/

// Only the addresses in ADMIN_EMAILS may open the admin outside local development (tests included)
function adminUser(): User
{
    return User::factory()->create(['email' => config('blog.admin_emails')[0]]);
}

it('makes the avatar from the initials, without an outside service', function () {
    $avatar = (new InitialsAvatar())->get(User::factory()->create(['name' => 'Ine Vermeer']));

    expect($avatar)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($avatar, strlen('data:image/svg+xml;base64,'))))->toContain('>IV</text>');

    $this->actingAs(adminUser())->get('/admin')->assertOk()->assertDontSee('ui-avatars.com');
});

it('shows small photos in the previews of the editor', function () {
    $photo = Media::create(['disk' => 'public', 'directory' => 'wordpress/2024', 'visibility' => 'public', 'name' => 'groot',
        'path' => 'wordpress/2024/groot.webp', 'width' => 2048, 'height' => 1365, 'size' => 1000, 'type' => 'image/webp', 'ext' => 'webp']);

    expect(PhotoBlock::toPreviewHtml(['media' => $photo->id]))->toContain('w=600')->not->toContain('w=1200')->toContain('loading="lazy"')
        ->and(GalleryBlock::toPreviewHtml(['media' => [$photo->id, $photo->id]]))->toContain('w=240')->not->toContain('w=600');
});

it('uses the admin font from this site, not from an outside font service', function () {
    $this->actingAs(adminUser())->get('/admin')
        ->assertOk()
        ->assertSee('/fonts/figtree/figtree.css', false)
        ->assertDontSee('fonts.bunny.net');

    expect(public_path('fonts/figtree/figtree-latin-400-normal.woff2'))->toBeFile();
});

it('lets the admin change their password on the profile page and reset it from the login page', function () {
    $this->actingAs(adminUser())->get('/admin/profile')->assertOk()->assertSee('Wachtwoord');

    auth()->logout();
    $this->get('/admin/login')->assertOk()->assertSee('/admin/password-reset/request', false);
});
