<?php

use App\Exceptions\StockException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->alias(['active' => EnsureUserIsActive::class, 'perm' => \App\Http\Middleware\RequirePermission::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Business-rule violations are shown to the user like validation errors, never as a 500.
        $exceptions->render(function (StockException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'errors' => ['stock' => [$e->getMessage()]]], 422);
            }

            return back()->withInput()->withErrors(['stock' => $e->getMessage()]);
        });
        $exceptions->dontReport([StockException::class]);
    })->create();
