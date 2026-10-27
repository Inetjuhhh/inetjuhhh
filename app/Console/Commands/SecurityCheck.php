<?php

namespace App\Console\Commands;

use App\Mail\SecurityUpdatesWaiting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Every morning (routes/console.php): are there security updates waiting on the server? It only looks,
 * Ine installs them herself (UPDATESTEPS.md). A mail only when there is something to do, and every
 * morning again until it is done, so nothing is missed.
 *
 *   php artisan site:security-check           checks and mails when needed
 *   php artisan site:security-check --show    only shows what it finds, no mail
 *
 * What it looks at:
 *  - Ubuntu packages with a security fix (nginx, MySQL, OpenSSL, the kernel, …): "-security" in apt's list.
 *    PHP comes from its own source (the ondrej PPA) that doesn't say "security", so every PHP update counts.
 *    apt's list must be fresh: Ubuntu refreshes it every day by itself (APT::Periodic::Update-Package-Lists).
 *  - A restart the server is waiting for after an update (/var/run/reboot-required)
 *  - Known security holes in the Laravel packages (composer audit: Laravel, Filament, Livewire, …)
 */
class SecurityCheck extends Command
{
    protected $signature = 'site:security-check {--show : Only show what was found, without sending a mail}';

    protected $description = 'Mail Ine when security updates are waiting on the server';

    /** Where Ubuntu notes that a restart is needed (and in .pkgs: because of what) */
    public static string $rebootFile = '/var/run/reboot-required';

    public function handle(): int
    {
        $findings = array_filter([
            'Ubuntu-pakketten met een beveiligingsupdate' => $this->packages(),
            'De server wil herstarten' => $this->reboot(),
            'Laravel-pakketten met een bekend beveiligingslek' => $this->composer(),
        ]);

        if ($findings === []) {
            $this->info('Niets te doen: geen beveiligingsupdates.');

            return self::SUCCESS;
        }

        $body = $this->text($findings);
        $this->line($body);

        if (! $this->option('show') && filled(config('blog.admin_emails'))) {
            $count = array_sum(array_map('count', $findings));
            Mail::to(config('blog.admin_emails'))->send(new SecurityUpdatesWaiting(
                'Beveiligingsupdates voor ' . parse_url(config('app.url'), PHP_URL_HOST) . ': ' . $count . ' ' . ($count === 1 ? 'ding' : 'dingen') . ' te doen',
                $body,
            ));
        }

        return self::SUCCESS;
    }

    /** @return list<string> "nginx 1.24.0-2ubuntu7.5 → 1.24.0-2ubuntu7.6" */
    private function packages(): array
    {
        $result = Process::run(['apt', 'list', '--upgradable']);

        if (! $result->successful()) {
            return [];
        }

        // nginx/noble-updates,noble-security 1.24.0-2ubuntu7.6 amd64 [upgradable from: 1.24.0-2ubuntu7.5]
        return collect(explode("\n", $result->output()))
            ->map(fn (string $line) => preg_match('~^([^/\s]+)/(\S+) (\S+) \S+ \[upgradable from: ([^\]]+)\]~', trim($line), $m) ? $m : null)
            ->filter(fn (?array $m) => $m && (str_contains($m[2], '-security') || Str::startsWith($m[1], 'php')))
            ->map(fn (array $m) => "{$m[1]} {$m[4]} → {$m[3]}")
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function reboot(): array
    {
        if (! File::exists(static::$rebootFile)) {
            return [];
        }

        // Which updates asked for it (the kernel, OpenSSL, …), when Ubuntu says so
        $because = File::exists(static::$rebootFile . '.pkgs')
            ? array_values(array_unique(array_filter(array_map('trim', explode("\n", File::get(static::$rebootFile . '.pkgs'))))))
            : [];

        return ['Nodig na: ' . ($because ? implode(', ', $because) : 'een eerdere update')];
    }

    /** @return list<string> "filament/filament 5.10.2: titel van het lek (CVE-…) https://…" */
    private function composer(): array
    {
        $result = Process::path(base_path())
            // Its cache in storage, so it also works for the user the scheduler runs as
            ->env(['COMPOSER_HOME' => storage_path('app/composer')])
            ->timeout(120)
            // The full path on the server (SERVERSTEPS step 4): cron, which runs the scheduler, doesn't look in /usr/local/bin
            ->run([is_file('/usr/local/bin/composer') ? '/usr/local/bin/composer' : 'composer', 'audit', '--locked', '--no-dev', '--format=json', '--no-interaction']);

        $advisories = json_decode($result->output(), true)['advisories'] ?? null;

        if (! is_array($advisories)) {
            // Composer couldn't check (no internet, not installed): the log says so, the mail doesn't cry wolf
            if (! $result->successful()) {
                logger()->warning('site:security-check: composer audit gaf geen antwoord', ['output' => Str::limit($result->errorOutput() ?: $result->output(), 500)]);
            }

            return [];
        }

        return collect($advisories)
            ->flatMap(fn (array $list, string $package) => array_map(fn (array $advisory) => trim("{$package}" . (filled($advisory['severity'] ?? null) ? " [{$advisory['severity']}]" : '') . ": {$advisory['title']}" . (filled($advisory['cve'] ?? null) ? " ({$advisory['cve']})" : '') . ' ' . ($advisory['link'] ?? '')), $list))
            ->values()
            ->all();
    }

    /** @param array<string, list<string>> $findings */
    private function text(array $findings): string
    {
        $lines = ['Hoi Ine,', '', 'Er staan beveiligingsupdates klaar op je server. Er is nog niets geïnstalleerd: dat doe jij.', ''];

        foreach ($findings as $heading => $items) {
            $lines[] = $heading . ':';
            foreach ($items as $item) {
                $lines[] = '  - ' . $item;
            }
            $lines[] = '';
        }

        if (isset($findings['Ubuntu-pakketten met een beveiligingsupdate'])) {
            $lines[] = 'Installeren:  sudo apt update && sudo apt upgrade';
        }
        if (isset($findings['De server wil herstarten'])) {
            $lines[] = 'Herstarten:   sudo reboot   (de site is dan ongeveer een minuut weg)';
        }
        if (isset($findings['Laravel-pakketten met een bekend beveiligingslek'])) {
            $lines[] = 'Laravel:      op je laptop composer update, testen, pushen, en op de server ./deploy.sh';
        }

        $lines[] = '';
        $lines[] = 'Meer uitleg staat in UPDATESTEPS.md. Zolang er iets openstaat, krijg je deze mail elke ochtend.';

        return implode("\n", $lines);
    }
}
