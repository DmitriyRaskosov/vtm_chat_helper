<?php

use App\Chronicle\Exceptions\ChronicleNotFoundException;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidInputException;
use App\Models\Chronicle;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::bind('chronicle', function (string $value): Chronicle {
                $chronicle = Chronicle::query()->whereKey($value)->first();

                return $chronicle ?? throw new ChronicleNotFoundException;
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (DomainException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $payload = ['message' => $e->getMessage()];

            if ($e instanceof InvalidInputException) {
                $payload['errors'] = (object) [];
            }

            return response()->json($payload, $e->statusCode());
        });
    })->create();
