<?php

use App\Mail\ErrorOccurred;
use App\Support\ErrorAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Mail::fake();
    Cache::store('file')->flush();
    config(['blog.admin_emails' => ['inetjuhhh@gmail.com']]);
});

afterEach(fn () => Cache::store('file')->flush());

function asLiveSite(): void
{
    app()->detectEnvironment(fn () => 'production');
}

it('mails Ine when a page breaks on the live site, with what and where', function () {
    asLiveSite();
    Route::get('/kapot', fn () => throw new RuntimeException('De database is weg'));

    $this->get('/kapot')->assertStatus(500);

    Mail::assertSent(ErrorOccurred::class, function (ErrorOccurred $mail) {
        return $mail->hasTo('inetjuhhh@gmail.com')
            && str_contains($mail->title, 'Runtime Exception')
            && str_contains($mail->body, 'De database is weg')
            && str_contains($mail->body, 'GET ' . url('/kapot'))
            && str_contains($mail->body, 'storage/logs/laravel-');
    });
});

it('mails the same error once an hour, not for every visitor', function () {
    asLiveSite();
    $error = new RuntimeException('Steeds hetzelfde');

    foreach (range(1, 5) as $i) {
        report($error);
    }
    Mail::assertSentCount(1);

    $this->travel(61)->minutes();
    report($error);
    Mail::assertSentCount(2);
});

it('sends at most ten alerts an hour, however many different errors there are', function () {
    asLiveSite();

    foreach (range(1, 15) as $i) {
        // A different place each time (as if thrown on another line), so each is a different error
        $error = new RuntimeException("Fout {$i}");
        (new ReflectionProperty(Exception::class, 'line'))->setValue($error, $i);
        report($error);
    }

    Mail::assertSentCount(ErrorAlert::PER_HOUR);
});

it('stays quiet for a page that does not exist', function () {
    asLiveSite();

    $this->get('/bestaat-echt-niet')->assertNotFound();

    Mail::assertNothingSent();
});

it('stays quiet on the laptop, where errors show up on the screen anyway', function () {
    report(new RuntimeException('Lokaal'));

    Mail::assertNothingSent();
});

it('sends a pretend error with site:test-alert, to check that alerts arrive', function () {
    asLiveSite();

    $this->artisan('site:test-alert')->expectsOutputToContain('Verstuurd naar inetjuhhh@gmail.com')->assertSuccessful();

    Mail::assertSent(ErrorOccurred::class, fn ($mail) => str_contains($mail->body, 'Dit is een test'));
});

it('never makes things worse when the mail itself fails', function () {
    asLiveSite();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Mailserver plat'));

    ErrorAlert::send(new RuntimeException('Iets anders'));

    expect(true)->toBeTrue(); // got here: no error out of the alert itself
});

it('does not mail when a bot is stopped from changing a locked Livewire property', function () {
    asLiveSite();

    report(new Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException('loadedDeferredSchemas'));

    Mail::assertNothingSent();
});
