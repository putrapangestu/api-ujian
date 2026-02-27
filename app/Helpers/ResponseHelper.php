<?php

namespace App\Helpers;

/**
 * Response Helper Class
 * 
 * Class ini menyediakan fungsi-fungsi helper untuk response handling
 * 
 * @package App\Helpers
 * @author Development Team
 * @version 1.0.0
 */
class ResponseHelper
{
    /**
     * Generate success response
     * 
     * @param mixed $data Data yang akan dikirim
     * @param string $message Pesan sukses
     * @param int $code HTTP status code
     * @return array
     */
    public static function success($data, string $message = 'Success', int $code = 200): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'pagination' => null,
            'code' => $code
        ];
    }

    /**
     * Generate error response
     * 
     * @param string $message Pesan error
     * @param mixed $errors Detail error
     * @param int $code HTTP status code
     * @return array
     */
    public static function error(string $message, $errors = null, int $code = 400): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'pagination' => null,
            'code' => $code
        ];
    }

    /**
     * Generate pagination response
     * 
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @param string $message Pesan response
     * @return array
     */
    public static function paginate($paginator, string $message = 'Success', int $code = 200): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'errors' => null,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total_items' => $paginator->total(),
                'has_next_page' => $paginator->hasMorePages(),
                'has_previous_page' => $paginator->currentPage() > 1
            ],
            'code' => $code
        ];
    }
}
