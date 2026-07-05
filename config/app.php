<?php

use App\Exceptions\AiProviderException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (AiProviderException $e) {
            return $e->render();
        });

        // Safety net: the backend is a pure JSON API for the mobile client.
        // Any other unhandled exception on an api/* route (e.g. a mis-
        // configured AI provider, unsupported route, bad method, etc.)
        // must still come back as JSON — never as an HTML error page —
        // so the Flutter client's JSON decoder never breaks.
        $exceptions->renderable(function (\Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null; // let web routes keep default HTML rendering
            }

            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            return response()->json([
                'success' => false,
                'message' => $status === 500 ? 'Internal server error.' : $e->getMessage(),
            ], $status);
        });
    })->create();