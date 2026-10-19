<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

/**
 * The whole database as one gzipped SQL file, with mysqldump: every table's structure, and the rows of
 * all tables except the ones that only matter right now (sessions, cache, queued jobs: those go along
 * empty). Used by site:export (moving to a server) and site:backup (the nightly copy on the server).
 */
class DatabaseDump
{
    public const EMPTY_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    /**
     * @param  callable(string): void|null  $onWarning  what mysqldump says on its error output
     */
    public static function to(string $file, ?callable $onWarning = null): void
    {
        $db = config('database.connections.mysql');
        $base = [
            self::mysqldump(), '--host=' . $db['host'], '--port=' . $db['port'], '--user=' . $db['username'],
            '--single-transaction', '--no-tablespaces', '--set-gtid-purged=OFF', '--default-character-set=utf8mb4',
        ];
        // The password as an environment variable, so it never shows in the list of running processes
        $env = filled($db['password']) ? ['MYSQL_PWD' => $db['password']] : [];

        $gz = gzopen($file, 'wb6');

        try {
            // First every table's structure, then the rows of all tables except the local-only ones
            $runs = [
                [...$base, '--no-data', '--routines', $db['database']],
                [...$base, '--no-create-info', '--skip-triggers',
                    ...array_map(fn ($table) => "--ignore-table={$db['database']}.{$table}", self::EMPTY_TABLES),
                    $db['database']],
            ];

            foreach ($runs as $command) {
                Process::env($env)->timeout(600)
                    ->run($command, function (string $type, string $output) use ($gz, $onWarning) {
                        $type === 'out' ? gzwrite($gz, $output) : ($onWarning && $onWarning(trim($output)));
                    })
                    ->throw();
            }
        } finally {
            gzclose($gz);
        }
    }

    private static function mysqldump(): string
    {
        if ($path = env('MYSQLDUMP_PATH')) {
            return $path;
        }

        // Laragon keeps MySQL in its own folder, not on the PATH; on the server it is simply "mysqldump"
        $laragon = glob('C:/laragon/bin/mysql/*/bin/mysqldump.exe') ?: [];

        return end($laragon) ?: 'mysqldump';
    }
}
