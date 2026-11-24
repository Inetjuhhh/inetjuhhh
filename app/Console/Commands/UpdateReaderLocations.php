<?php

namespace App\Console\Commands;

use App\Support\ReaderLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads the free location file of DB-IP ("IP to City Lite", CC BY 4.0) to look up readers'
 * countries and cities on our own server (see ReaderLocation). DB-IP publishes a new one every
 * month; until the first download, readers are counted without a country.
 */
class UpdateReaderLocations extends Command
{
    protected $signature = 'stats:update-locations';

    protected $description = 'Download the latest free DB-IP location file for the reader statistics';

    public function handle(): int
    {
        $target = storage_path(ReaderLocation::PATH);
        $download = $target . '.download.gz';
        $unpacked = $target . '.new';

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }

        // This month's file; early in the month it may not be there yet, then last month's
        foreach ([now(), now()->subMonthNoOverflow()] as $month) {
            $url = 'https://download.db-ip.com/free/dbip-city-lite-' . $month->format('Y-m') . '.mmdb.gz';

            try {
                $response = Http::timeout(600)->withOptions(['sink' => $download])->get($url);
            } catch (Throwable $e) {
                $this->warn("{$url}: {$e->getMessage()}");

                continue;
            }

            if ($response->successful() && filesize($download) > 1_000_000) {
                break;
            }

            $this->warn("{$url}: not available (" . $response->status() . ')');
            @unlink($download);
        }

        if (! is_file($download)) {
            $this->error('No location file could be downloaded; the old one (if any) stays.');

            return self::FAILURE;
        }

        // Unpack in pieces: the file is about 130 MB
        $in = gzopen($download, 'rb');
        $out = fopen($unpacked, 'wb');
        while (! gzeof($in)) {
            fwrite($out, gzread($in, 1024 * 1024));
        }
        gzclose($in);
        fclose($out);
        unlink($download);

        ReaderLocation::reset();
        rename($unpacked, $target);

        $this->info('Location file updated: ' . round(filesize($target) / 1024 / 1024) . ' MB');

        return self::SUCCESS;
    }
}
