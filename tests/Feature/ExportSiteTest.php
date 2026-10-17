<?php

use App\Models\Trip;
use App\Models\User;

// The export itself needs mysqldump and MySQL; the tests run on SQLite, so only the safety check is tested here
it('refuses to export while test content is still in the database', function () {
    User::factory()->create(['email' => 'test@example.com']);
    Trip::create(['name' => 'Costa Rica 2027', 'slug' => 'costa-rica-2027', 'description' => 'TESTREIS: drie weken',
        'starts_on' => '2027-07-31', 'ends_on' => '2027-08-20']);

    $this->artisan('site:export')
        ->expectsOutputToContain('Costa Rica 2027')
        ->expectsOutputToContain('test@example.com')
        ->assertFailed();

    expect(storage_path('app/export/inetjuhhh-database.sql.gz'))->not->toBeFile();
});
