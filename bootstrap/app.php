<?php

use App\Http\Middleware\AuditMiddleware;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AuditMiddleware::class,
        ]);
        $middleware->alias([
            'module' => EnsureModuleAccess::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response): Response {
            $request = request();

            if ($response->getStatusCode() !== 403 || $request->expectsJson()) {
                return $response;
            }

            return Inertia::render('Errors/Forbidden', [
                'backUrl' => url()->previous('/dashboard'),
            ])
                ->toResponse($request)
                ->setStatusCode(403);
        });
    })->create();
