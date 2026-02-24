<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Annotations as OA;

/**
 * Base Controller untuk semua controllers dalam aplikasi
 *
 * Class ini menyediakan fungsi dasar yang dapat digunakan oleh semua controller
 * Menggunakan traits AuthorizesRequests dan ValidatesRequests dari Laravel
 *
 * @OA\Info(
 *     title="API Ujian CPNS",
 *     version="1.0.0",
 *     description="API untuk sistem manajemen ujian CPNS dengan fitur lengkap",
 *     @OA\Contact(
 *         name="Development Team",
 *         email="dev@example.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Development server"
 * )
 *
 * @OA\Server(
 *     url="https://api.ujian-cpns.com/api",
 *     description="Production server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Masukkan token JWT dalam format: Bearer {token}"
 * )
 *
 * @OA\Schema(
 *     schema="BaseResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operation successful"),
 *     @OA\Property(property="data", type="object", nullable=true),
 *     @OA\Property(property="errors", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Operation failed"),
 *     @OA\Property(property="data", type="object", nullable=true),
 *     @OA\Property(property="errors", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Validation error"),
 *     @OA\Property(property="errors", type="object", example={"field_name": ["Field is required"]})
 * )
 *
 * @package App\Http\Controllers
 * @author Development Team
 * @version 1.0.0
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Response sukses standar
     *
     * @param mixed $data Data yang akan dikirim dalam response
     * @param string $message Pesan sukses
     * @param int $code HTTP status code (default: 200)
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Response error standar
     *
     * @param string $message Pesan error
     * @param int $code HTTP status code (default: 400)
     * @param mixed $errors Detail error tambahan
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(string $message, int $code = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }
}
