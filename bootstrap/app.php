<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Helpers\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApiRequest = function (Request $request): bool {
            return $request->is('api/*') || $request->expectsJson();
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApiRequest) {
            if (!$isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error($e->getMessage(), $e->status, $e->errors());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApiRequest) {
            if (!$isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error('Unauthenticated', 401);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isApiRequest) {
            if (!$isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error('Record not found', 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApiRequest) {
            if (!$isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error('Not found', 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApiRequest) {
            if (!$isApiRequest($request)) {
                return null;
            }

            $statusCode = $e->getStatusCode();
            $message = $e->getMessage() !== '' ? $e->getMessage() : 'HTTP Error';

            return ApiResponse::error($message, $statusCode);
        });

        $exceptions->render(function (\Throwable $e, Request $request) use ($isApiRequest) {
            if (!$isApiRequest($request)) {
                return null;
            }

            $message = $e->getMessage() !== '' ? $e->getMessage() : 'Server Error';
            return ApiResponse::error($message, 500);
        });
    })->create();
