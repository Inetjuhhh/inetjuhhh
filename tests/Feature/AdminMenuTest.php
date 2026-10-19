<?php

use App\Models\User;
use Filament\Facades\Filament;

it('splits the admin menu into writing, readers and the lists you set up once', function () {
    // Outside local development only the addresses in ADMIN_EMAILS get into the admin
    $this->actingAs(User::factory()->create(['email' => config('blog.admin_emails')[0]]));
    $this->get('/admin')->assertOk();

    $menu = collect(Filament::getNavigation())
        ->mapWithKeys(fn ($group) => [$group->getLabel() ?? '-' => collect($group->getItems())->map->getLabel()->values()->all()]);

    expect($menu->only(['Schrijven', 'Lezers', 'Beheer'])->all())->toBe([
        'Schrijven' => ['Blogs', 'Reizen', 'Mediabibliotheek', 'Ik & wij'],
        'Lezers' => ['Reacties', 'Abonnees'],
        'Beheer' => ['Categorieën', 'Tags', 'Landen', 'Website Instellingen', 'Privacy'],
    ]);
});
