<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\InternalApiKeyMiddleware;

/**
 * Bootstrap Application Configuration
 *
 * File ini berisi konfigurasi aplikasi Laravel
 *
 * @package Bootstrap
 * @author Development Team
 * @version 1.0.0
 */

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom middleware
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'internal.api.key' => InternalApiKeyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(\App\Helpers\ResponseHelper::error(
                    'Data yang Anda masukkan tidak valid. Silakan periksa kembali.',
                    $e->errors(),
                    422
                ), 422);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(\App\Helpers\ResponseHelper::error(
                    'Sesi Anda telah berakhir. Silakan login kembali.',
                    null,
                    401
                ), 401);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $httpMessage = match ($e->getStatusCode()) {
                    403 => 'Anda tidak memiliki izin untuk mengakses halaman ini.',
                    404 => 'Halaman atau data yang Anda cari tidak ditemukan.',
                    429 => 'Terlalu banyak permintaan. Silakan coba beberapa saat lagi.',
                    default => 'Terjadi kesalahan. Silakan coba lagi.',
                };
                return response()->json(\App\Helpers\ResponseHelper::error(
                    $httpMessage,
                    null,
                    $e->getStatusCode()
                ), $e->getStatusCode());
            }
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $code = 500;
                if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                    $code = $e->getResponse()->getStatusCode();
                }

                $errors = config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace()
                ] : null;

                return response()->json(\App\Helpers\ResponseHelper::error(
                    config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.',
                    $errors,
                    $code
                ), $code);
            }
        });
    })->create();
