<?php

use App\Models\Trip;
use App\Models\User;

// The export itself needs mysqldump and MySQL; the tests run on SQLite, so only the safety check is tested here
it('refuses to export while test content is still in the database', function () {
    User::factory()->create(['email' => 'test@example.com']);
    Trip::create(['name' => 'Costa Rica 2027', 'slug' => 'costa-rica-2027', 'description' => 'TESTREIS: drie weken',
        'starts_on' => '2027-07-31', 'ends_on' => '2027-08-20']);

    // A real export from earlier may be there; the refused run must not touch it
    $file = storage_path('app/export/inetjuhhh-database.sql.gz');
    $before = is_file($file) ? filemtime($file) : null;

    $this->artisan('site:export')
        ->expectsOutputToContain('Costa Rica 2027')
        ->expectsOutputToContain('test@example.com')
        ->assertFailed();

    clearstatcache();
    expect(is_file($file) ? filemtime($file) : null)->toBe($before);
});
