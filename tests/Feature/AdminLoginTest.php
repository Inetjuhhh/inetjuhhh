<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(fn () => Filament::setCurrentPanel('admin'));

it('ticks "Onthoud mij" on the login page', function () {
    Livewire::test(Login::class)->assertSet('data.remember', true);
});

it('remembers the admin after logging in', function () {
    $user = User::factory()->create(['email' => config('blog.admin_emails')[0], 'password' => 'geheim-wachtwoord']);

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'geheim-wachtwoord')
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('logs in with Enter from every field, the checkbox too', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('x-on:keydown.enter', escape: false)
        ->assertSee('requestSubmit', escape: false);
});
