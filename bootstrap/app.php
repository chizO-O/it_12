<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\NormalizeAccountEmail;
use App\Http\Middleware\ThrottlePublicRegistration;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates HTTPS at its edge and proxies plain HTTP internally.
        // Honor the forwarded HTTPS scheme/port without trusting a forwarded host.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT,
        );

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
        $middleware->redirectUsersTo('/dashboard');
        $middleware->appendToGroup('web', ThrottlePublicRegistration::class);
        $middleware->appendToGroup('web', NormalizeAccountEmail::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
