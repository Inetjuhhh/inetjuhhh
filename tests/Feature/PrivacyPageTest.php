<?php

use App\Filament\Pages\EditPrivacyPage;
use App\Models\Page;
use App\Models\User;
use Livewire\Livewire;

it('shows the privacy page with the draft text the first time', function () {
    $this->get('/privacy')->assertOk()
        ->assertSee('Even eerlijk over je gegevens')
        ->assertSee('Brevo')->assertSee('Cloudflare')->assertSee('Hetzner')->assertSee('Esri');

    expect(Page::where('slug', Page::PRIVACY)->count())->toBe(1);
});

it('links to the privacy page in the footer and at the forms', function () {
    $this->get('/')->assertOk()->assertSee('href="' . route('privacy') . '"', false);
});

it('lets the admin edit the privacy text, and keeps the edit', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(EditPrivacyPage::class)
        ->assertOk()
        ->set('data.title', 'Privacy & cookies')
        ->call('save')
        ->assertNotified('Opgeslagen');

    expect(Page::privacy()->title)->toBe('Privacy & cookies');
    $this->get('/privacy')->assertSee('Privacy &amp; cookies', false);
});

it('loads nothing from other servers on an ordinary page', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('fonts.bunny.net')
        ->not->toContain('code.jquery.com')
        ->not->toContain('cdn.jsdelivr.net')
        ->not->toContain('challenges.cloudflare.com/turnstile/v0/api.js"');
});
