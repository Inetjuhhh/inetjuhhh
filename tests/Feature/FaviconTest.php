<?php

it('shows the avatar in the browser tab and on a phone home screen', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('rel="icon" href="' . asset('favicon.ico') . '"')
        ->toContain('rel="apple-touch-icon" href="' . asset('apple-touch-icon.png') . '"');

    // The starter kit's favicon.ico was an empty file: an empty tab
    foreach (['favicon.ico', 'favicon-32.png', 'apple-touch-icon.png'] as $file) {
        expect(filesize(public_path($file)))->toBeGreaterThan(1000);
    }
});
