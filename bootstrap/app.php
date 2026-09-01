<?php

use App\Http\Middleware\AnswerAjaxRedirectsWithJson;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\LogPageView;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The back office, behind whatever ADMIN_ROUTE_PREFIX says. Registered here
        // rather than wrapped inside the file so a route added to routes/admin.php
        // cannot forget to move with the prefix. Route names are unaffected, so every
        // route() call in the app and the tests follows on its own.
        then: function () {
            Route::middleware('web')
                ->prefix(config('app.admin_prefix'))
                ->group(__DIR__.'/../routes/admin.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs on every web request so an idle session cannot be resumed.
        $middleware->web(append: [
            EnforceIdleTimeout::class,
            // Turns a controller redirect into JSON for callers that asked for JSON,
            // so the listings can delete over AJAX without rewriting every destroy().
            AnswerAjaxRedirectsWithJson::class,
            // Last, so it sees the finished response and can record the status.
            LogPageView::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
