<?php

use App\Console\Commands\SecurityCheck;
use App\Mail\SecurityUpdatesWaiting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;

afterEach(fn () => File::delete([SecurityCheck::$rebootFile, SecurityCheck::$rebootFile . '.pkgs']));

beforeEach(function () {
    Mail::fake();
    config(['blog.admin_emails' => ['inetjuhhh@gmail.com']]);
    SecurityCheck::$rebootFile = storage_path('framework/testing/reboot-required');
    File::delete([SecurityCheck::$rebootFile, SecurityCheck::$rebootFile . '.pkgs']);
});

// What apt and composer answer on the server
function serverSays(string $apt = "Listing...\n", array $advisories = []): void
{
    Process::fake([
        '*apt*' => Process::result($apt),
        '*composer*' => Process::result(json_encode(['advisories' => $advisories, 'abandoned' => []])),
    ]);
}

it('stays quiet when nothing is waiting', function () {
    serverSays();

    $this->artisan('site:security-check')->assertSuccessful();

    Mail::assertNothingSent();
});

it('mails the security updates and PHP, but not ordinary updates', function () {
    serverSays(<<<'APT'
        Listing...
        nginx/noble-updates,noble-security 1.24.0-2ubuntu7.6 amd64 [upgradable from: 1.24.0-2ubuntu7.5]
        php8.4-fpm/noble 8.4.14-1+ubuntu24.04.1+deb.sury.org+1 amd64 [upgradable from: 8.4.13-1+ubuntu24.04.1+deb.sury.org+1]
        vim/noble-updates 2:9.1.0016-1ubuntu7.9 amd64 [upgradable from: 2:9.1.0016-1ubuntu7.8]
        APT);

    $this->artisan('site:security-check')->assertSuccessful();

    Mail::assertSent(SecurityUpdatesWaiting::class, function (SecurityUpdatesWaiting $mail) {
        return $mail->hasTo('inetjuhhh@gmail.com')
            && str_contains($mail->title, '2 dingen te doen')
            && str_contains($mail->body, 'nginx 1.24.0-2ubuntu7.5 → 1.24.0-2ubuntu7.6')
            && str_contains($mail->body, 'php8.4-fpm')
            && ! str_contains($mail->body, 'vim')
            && str_contains($mail->body, 'sudo apt update && sudo apt upgrade')
            && str_contains($mail->body, 'nog niets geïnstalleerd');
    });
    // It only looks: nothing is installed
    Process::assertNotRan(fn ($process) => str_contains(implode(' ', (array) $process->command), 'upgrade'));
});

it('mails when the server waits for a restart, with what asked for it', function () {
    serverSays();
    File::ensureDirectoryExists(dirname(SecurityCheck::$rebootFile));
    File::put(SecurityCheck::$rebootFile, "*** System restart required ***\n");
    File::put(SecurityCheck::$rebootFile . '.pkgs', "linux-image-6.8.0-88-generic\nlibssl3t64\nlibssl3t64\n");

    $this->artisan('site:security-check')->assertSuccessful();

    Mail::assertSent(SecurityUpdatesWaiting::class, fn ($mail) => str_contains($mail->body, 'Nodig na: linux-image-6.8.0-88-generic, libssl3t64')
        && str_contains($mail->body, 'sudo reboot'));
});

it('mails a known security hole in a Laravel package', function () {
    serverSays(advisories: ['livewire/livewire' => [['title' => 'Remote code execution via property updates', 'cve' => 'CVE-2025-54068', 'severity' => 'critical', 'link' => 'https://github.com/advisories/GHSA-29cq-5w36-x7w3']]]);

    $this->artisan('site:security-check')->assertSuccessful();

    Mail::assertSent(SecurityUpdatesWaiting::class, fn ($mail) => str_contains($mail->body, 'livewire/livewire [critical]: Remote code execution via property updates (CVE-2025-54068)')
        && str_contains($mail->body, './deploy.sh'));
});

it('does not mail when composer could not check (no internet)', function () {
    Process::fake(['*apt*' => Process::result("Listing...\n"), '*composer*' => Process::result('', 'Could not connect', 1)]);

    $this->artisan('site:security-check')->assertSuccessful();

    Mail::assertNothingSent();
});

it('only shows and does not mail with --show', function () {
    serverSays("nginx/noble-security 1.24.0-2ubuntu7.6 amd64 [upgradable from: 1.24.0-2ubuntu7.5]\n");

    $this->artisan('site:security-check --show')->expectsOutputToContain('nginx')->assertSuccessful();

    Mail::assertNothingSent();
});
