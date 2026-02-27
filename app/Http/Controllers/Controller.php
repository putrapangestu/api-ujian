<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
// use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA;

/**
 * Base Controller for all controllers in the application
 *
 * This class provides basic functions that can be used by all controllers
 * Uses AuthorizesRequests and ValidatesRequests traits from Laravel
 *
 *
 * @author Development Team
 *
 * @version 1.0.0
 */
#[OA\Info(
    title: 'API Ujian CPNS',
    version: '1.0.0',
    description: 'API untuk sistem manajemen ujian CPNS'
)]
#[OA\Server(
    url: '/api',
    description: 'Default Server'
)]
#[OA\OpenApi(
    security: [
        [
            'bearerAuth' => [],
        ],
    ]
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Masukkan token dalam format: Bearer <token>'
)]

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Response sukses standar
     *
     * @param  mixed  $data  Data yang akan dikirim dalam response
     * @param  string  $message  Pesan sukses
     * @param  int  $code  HTTP status code (default: 200)
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'pagination' => null,
            'code' => $code,
        ], $code);
    }

    /**
     * Response error standar
     *
     * @param  string  $message  Pesan error
     * @param  int  $code  HTTP status code (default: 400)
     * @param  mixed  $errors  Detail error tambahan
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(string $message, int $code = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'pagination' => null,
            'code' => $code,
        ], $code);
    }
}
