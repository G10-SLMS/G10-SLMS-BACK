<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,
        ]);
    })
<<<<<<< HEAD

    ->withExceptions(function (Exceptions $exceptions): void {
=======
 ->withExceptions(function (Exceptions $exceptions): void {
>>>>>>> a10541ddd2ad76c5db80d32d375389516de82657
        //
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
