<?php

use App\Support\DatabaseDump;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filesystems.disks.offsite.bucket' => 'inetjuhhh-backup']);
    Storage::fake('offsite');
    Storage::fake('public');

    // The tests have no MySQL: a stand-in writes a small "dump"
    app()->instance(DatabaseDump::class, new class extends DatabaseDump {
        public function dump(string $file, ?callable $onWarning = null): void
        {
            file_put_contents($file, gzencode('-- database'));
        }
    });
});

it('copies the database and the new photos outside Hetzner', function () {
    Storage::disk('public')->put('wordpress/2019/scooter.webp', 'foto 1');
    Storage::disk('public')->put('uitgelicht/vesuvius.jpg', 'foto 2');
    Storage::disk('offsite')->put('photos/uitgelicht/vesuvius.jpg', 'foto 2');   // already there

    $this->artisan('site:offsite-backup')
        ->expectsOutputToContain("Foto's: 1 nieuw gekopieerd, 1 stonden er al.")
        ->assertSuccessful();

    Storage::disk('offsite')->assertExists('database/database-' . now()->format('Y-m-d') . '.sql.gz');
    Storage::disk('offsite')->assertExists('photos/wordpress/2019/scooter.webp');
    expect(file_exists(storage_path('app/offsite-database.sql.gz')))->toBeFalse();   // the local temporary file is gone
});

it('keeps 30 days of database copies, and photos removed from the site', function () {
    Storage::disk('offsite')->put('database/database-' . now()->subDays(29)->format('Y-m-d') . '.sql.gz', 'x');
    Storage::disk('offsite')->put('database/database-' . now()->subDays(31)->format('Y-m-d') . '.sql.gz', 'x');
    Storage::disk('offsite')->put('photos/weg/oude-foto.jpg', 'x');

    $this->artisan('site:offsite-backup')->assertSuccessful();

    Storage::disk('offsite')->assertExists('database/database-' . now()->subDays(29)->format('Y-m-d') . '.sql.gz');
    Storage::disk('offsite')->assertMissing('database/database-' . now()->subDays(31)->format('Y-m-d') . '.sql.gz');
    Storage::disk('offsite')->assertExists('photos/weg/oude-foto.jpg');
});

it('puts missing photos back on the server, without touching the ones that are there', function () {
    Storage::disk('offsite')->put('photos/wordpress/2019/scooter.webp', 'uit de backup');
    Storage::disk('offsite')->put('photos/uitgelicht/vesuvius.jpg', 'oude versie');
    Storage::disk('public')->put('uitgelicht/vesuvius.jpg', 'huidige versie');

    $this->artisan('site:offsite-backup', ['--restore-photos' => true])
        ->expectsOutputToContain('Teruggezet: 1')
        ->assertSuccessful();

    expect(Storage::disk('public')->get('wordpress/2019/scooter.webp'))->toBe('uit de backup')
        ->and(Storage::disk('public')->get('uitgelicht/vesuvius.jpg'))->toBe('huidige versie');
});

it('does nothing until the storage is set up', function () {
    config(['filesystems.disks.offsite.bucket' => null]);
    Storage::disk('public')->put('foto.jpg', 'x');

    $this->artisan('site:offsite-backup')->expectsOutputToContain('Geen opslag buiten Hetzner ingesteld')->assertSuccessful();

    expect(Storage::disk('offsite')->allFiles())->toBeEmpty();
});

it('runs every night at 04:00, after the copy on the server', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'site:offsite-backup'));

    expect($event->expression)->toBe('0 4 * * *');
});
