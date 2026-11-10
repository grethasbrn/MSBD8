<?php

use App\Http\Middleware\EnsureRole;
use App\Support\DbErrors;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => EnsureRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Semua error di /api/* dijawab dalam bentuk JSON
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());

        // Aturan bisnis yang dilanggar (dari service PHP)
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });

        // Aturan bisnis yang dijaga database (kuota penuh, DP kurang, dobel jadwal, dst.)
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*') && ($msg = DbErrors::message($e))) {
                return response()->json(['message' => $msg], 422);
            }
        });
    })->create();
