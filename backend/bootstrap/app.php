<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Enforce JSON responses for all API endpoints
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // 422 Validation Error
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data failed validation.',
                        'details' => $e->errors(),
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'path' => $request->path(),
                    ],
                ], 422);
            }
        });

        // 401 Unauthenticated
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Authentication credentials were not provided or are invalid.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'path' => $request->path(),
                    ],
                ], 401);
            }
        });

        // 403 Forbidden
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => $e->getMessage() ?: 'You do not have permission to perform this action.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'path' => $request->path(),
                    ],
                ], 403);
            }
        });

        // 404 Not Found
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'RESOURCE_NOT_FOUND',
                        'message' => $e->getMessage() ?: 'The requested resource was not found.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'path' => $request->path(),
                    ],
                ], 404);
            }
        });

        // Custom Financial Ledger Unbalanced Exception
        $exceptions->render(function (\App\Domains\Financial\Exceptions\LedgerUnbalancedException $e, Request $request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LEDGER_UNBALANCED_ERROR',
                    'message' => $e->getMessage(),
                    'details' => [
                        'total_debit' => $e->getTotalDebit(),
                        'total_credit' => $e->getTotalCredit(),
                        'imbalance' => $e->getImbalance(),
                    ],
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'path' => $request->path(),
                ],
            ], 422);
        });

        // Custom Booking State Transition Exception
        $exceptions->render(function (\App\Domains\Booking\Exceptions\BookingStateTransitionException $e, Request $request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATE_TRANSITION',
                    'message' => $e->getMessage(),
                    'details' => [
                        'current_status' => $e->getCurrentStatus(),
                        'target_status' => $e->getTargetStatus(),
                        'actor_role' => $e->getActorRole(),
                    ],
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'path' => $request->path(),
                ],
            ], 422);
        });

        // Generic 500
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $isProduction = app()->environment('production');

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SERVER_ERROR',
                        'message' => $isProduction ? 'An unexpected internal error occurred.' : $e->getMessage(),
                        'trace' => $isProduction ? null : array_slice($e->getTrace(), 0, 5),
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'path' => $request->path(),
                    ],
                ], $status >= 400 && $status < 600 ? $status : 500);
            }
        });
    })->create();
