<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureUser;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'user' => EnsureUser::class,
            'staff' => EnsureStaff::class,
            'permission' => EnsurePermission::class,
        ]);

        $middleware->prepend(SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return match (true) {
                $e instanceof AuthenticationException => response()->json(['message' => __('api.unauthenticated')], 401),
                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => response()->json(['message' => __('api.forbidden')], 403),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => response()->json(['message' => __('api.not_found')], 404),
                $e instanceof MethodNotAllowedHttpException => response()->json(['message' => __('api.method_not_allowed')], 405, $headers),
                $e instanceof TooManyRequestsHttpException => response()->json(array_filter([
                    'message' => __('api.too_many_requests'),
                    'retry_after' => isset($headers['Retry-After']) ? (int) $headers['Retry-After'] : null,
                ], fn ($value) => $value !== null), 429, $headers),
                $e instanceof ValidationException => response()->json([
                    'message' => __('api.validation_failed'),
                    'errors' => $e->errors(),
                ], 422),
                $e instanceof ConflictHttpException => response()->json([
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : __('api.conflict'),
                ], 409),
                $e instanceof HttpExceptionInterface => response()->json([
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : __('api.http_error'),
                ], $e->getStatusCode(), $headers),
                config('app.debug') => null,
                default => response()->json(['message' => __('api.server_error')], 500),
            };
        });
    })->create();
