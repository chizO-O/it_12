<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\NormalizeAccountEmail;
use App\Http\Middleware\ThrottlePublicRegistration;
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
            'role' => EnsureUserHasRole::class,
        ]);
        $middleware->redirectUsersTo('/dashboard');
        $middleware->appendToGroup('web', ThrottlePublicRegistration::class);
        $middleware->appendToGroup('web', NormalizeAccountEmail::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
