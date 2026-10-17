<?php

use App\Filament\Blocks\GalleryBlock;
use App\Filament\Blocks\PhotoBlock;
use App\Filament\InitialsAvatar;
use App\Models\User;
use Awcodes\Curator\Models\Media;

/*
| What keeps the admin light: no picture from an outside service, small photo previews in the editor.
*/

it('makes the avatar from the initials, without an outside service', function () {
    $avatar = (new InitialsAvatar())->get(User::factory()->create(['name' => 'Ine Vermeer']));

    expect($avatar)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($avatar, strlen('data:image/svg+xml;base64,'))))->toContain('>IV</text>');

    $this->actingAs(User::first())->get('/admin')->assertDontSee('ui-avatars.com');
});

it('shows small photos in the previews of the editor', function () {
    $photo = Media::create(['disk' => 'public', 'directory' => 'wordpress/2024', 'visibility' => 'public', 'name' => 'groot',
        'path' => 'wordpress/2024/groot.webp', 'width' => 2048, 'height' => 1365, 'size' => 1000, 'type' => 'image/webp', 'ext' => 'webp']);

    expect(PhotoBlock::toPreviewHtml(['media' => $photo->id]))->toContain('w=800')->not->toContain('w=1200')
        ->and(GalleryBlock::toPreviewHtml(['media' => [$photo->id, $photo->id]]))->toContain('w=240')->not->toContain('w=600');
});
