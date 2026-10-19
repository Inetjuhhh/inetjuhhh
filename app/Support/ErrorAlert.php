<?php

namespace App\Support;

use App\Mail\ErrorOccurred;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * A mail to Ine when something goes wrong on the live site (a page with an error, a mail or a job that
 * fails), so she knows even on holiday. Called for every error Laravel reports (bootstrap/app.php);
 * errors Laravel doesn't report (a page that doesn't exist, a form filled in wrongly) never get here.
 *
 * The same error is mailed at most once an hour, and at most 10 mails an hour in total. The counting is
 * kept in files, not in the database: when the database is the problem, the alert must still go out.
 */
class ErrorAlert
{
    public const PER_HOUR = 10;

    public static function send(Throwable $error): void
    {
        if (! app()->isProduction() || blank(config('blog.admin_emails'))) {
            return;
        }

        try {
            $store = Cache::store('file');
            $fingerprint = 'error-alert:' . sha1(get_class($error) . '|' . $error->getFile() . '|' . $error->getLine());

            // Already mailed this hour, or the hourly limit is reached: it is in the log anyway
            if (! $store->add($fingerprint, true, 3600)) {
                return;
            }

            $store->add('error-alert:count', 0, 3600);
            if ($store->increment('error-alert:count') > self::PER_HOUR) {
                return;
            }

            $title = 'Foutje op ' . parse_url(config('app.url'), PHP_URL_HOST) . ': ' . str(class_basename($error))->headline();

            Mail::to(config('blog.admin_emails'))->sendNow(new ErrorOccurred($title, static::text($error)));
        } catch (Throwable) {
            // The alert itself failing (mail server down) must never make things worse: the log has it
        }
    }

    private static function text(Throwable $error): string
    {
        // A page that was being opened (a job, the scheduler or a command has no route)
        $request = request()->route() ? request() : null;
        $file = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $error->getFile());

        return implode("\n", array_filter([
            'Hoi Ine,',
            '',
            'Er ging net iets mis op je site. Meestal is het eenmalig; komt het vaker, kijk dan even.',
            '',
            'Wat:     ' . class_basename($error) . ': ' . str($error->getMessage())->limit(500),
            'Waar:    ' . $file . ' (regel ' . $error->getLine() . ')',
            $request ? 'Pagina:  ' . $request->method() . ' ' . $request->fullUrl() : 'Pagina:  geen (een achtergrondtaak, de planner of een commando)',
            'Wanneer: ' . now()->timezone('Europe/Amsterdam')->translatedFormat('l j F Y, H:i'),
            '',
            'Alles staat in het logboek op de server:',
            '  tail -50 /var/www/inetjuhhh/storage/logs/laravel-' . now()->format('Y-m-d') . '.log',
            '',
            'Wat je kunt doen staat in UPDATESTEPS.md, hoofdstuk 2 en 5.',
            'Deze fout mail ik je hooguit één keer per uur, en in totaal hooguit ' . self::PER_HOUR . ' mails per uur.',
        ], fn ($line) => $line !== null));
    }
}
