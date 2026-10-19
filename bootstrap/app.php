<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // www. and the temporary address go to the address in APP_URL (production only)
        $middleware->prepend(\App\Http\Middleware\RedirectToMainAddress::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // On the live site: a mail to Ine about every error (at most once an hour each); the log keeps it too
        $exceptions->report(function (Throwable $error) {
            \App\Support\ErrorAlert::send($error);
        });
    })->create();
