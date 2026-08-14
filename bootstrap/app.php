<?php

use App\Exceptions\PosOperationException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Services\MenuItemImageService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Business rule refusals are shown to the cashier as a message on the
        // screen they were already on, never as an error page.
        $exceptions->render(function (PosOperationException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        });

        // An upload larger than PHP's post_max_size is discarded by PHP before
        // validation ever runs, so it cannot be caught by a form rule. Say what
        // happened in plain words instead of showing a 413 page.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = 'That file is too large to upload. The most this server accepts is '
                .MenuItemImageService::maxUploadLabel().'.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 413)
                : back()->withInput($request->except('image'))->with('error', $message);
        });
    })->create();
