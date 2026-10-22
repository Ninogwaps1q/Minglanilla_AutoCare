<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Services\Messaging\MessageStorage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureUserIsActive::class]);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $exception, Request $request): ?Response {
            if (MessageStorage::isUnavailable($exception)
                && ($request->routeIs('customer.messages.*') || $request->routeIs('shop.messages.*'))) {
                return response()->view('errors.503', [
                    'title' => 'Messages are temporarily unavailable',
                    'message' => 'We cannot load conversations right now. Please try again later; your other account features are still available.',
                ], 503);
            }

            return null;
        });

        $exceptions->render(function (PDOException $exception, Request $request): ?Response {
            $driverCode = (int) ($exception->errorInfo[1] ?? $exception->getCode());
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

            if (! in_array($driverCode, [1040, 1203, 2002, 2003, 2006, 2013, 2055], true)
                && ! str_starts_with($sqlState, '08')) {
                return null;
            }

            $message = 'We cannot connect to the service right now. Please wait a moment and try again. If you were submitting a payment or booking, check its status before submitting again.';
            $headers = ['Retry-After' => '30', 'Cache-Control' => 'no-store'];

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $message], 503, $headers);
            }

            return response()->view('errors.503', ['message' => $message], 503, $headers);
        });
    })->create();
