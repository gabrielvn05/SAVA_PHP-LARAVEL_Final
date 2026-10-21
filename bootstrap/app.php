<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureSingleSession;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PreventSensitivePageCache;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'firmaec/grabar_archivos_firmados',
        ]);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(PreventSensitivePageCache::class);
        $middleware->appendToGroup('web', EnsurePasswordChanged::class);
        $middleware->appendToGroup('web', EnsureProfileComplete::class);
        $middleware->appendToGroup('web', EnsureSingleSession::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
