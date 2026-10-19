<?php

it('sends visitors on www or the temporary address to the main address, to the same page', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://inetjuhhh.nl']);

    $this->get('https://www.inetjuhhh.nl/kaart?reis=italie-2026')
        ->assertStatus(301)->assertRedirect('https://inetjuhhh.nl/kaart?reis=italie-2026');
    $this->get('https://nieuw.inetjuhhh.nl/blogs/poon-hill')
        ->assertStatus(301)->assertRedirect('https://inetjuhhh.nl/blogs/poon-hill');
});

it('leaves the main address alone', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://inetjuhhh.nl']);

    $this->get('https://inetjuhhh.nl/up')->assertOk();
});

it('never redirects locally', function () {
    config(['app.url' => 'https://inetjuhhh.nl']);

    $this->get('http://inetjuhhh.test/up')->assertOk();
});
