<?php

namespace App\Helpers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
            'data' => self::formatDateTimes($data),
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
            'data' => self::formatDateTimes($paginator->items()),
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

    public static function formatDateTimes($data)
    {
        if ($data instanceof CarbonInterface || $data instanceof DateTimeInterface) {
            return Carbon::instance($data)->format('Y-m-d H:i:s');
        }

        if ($data instanceof Model) {
            $array = $data->toArray();
            $dateKeys = array_unique(array_merge($data->getDates(), array_keys(array_filter(
                $data->getCasts(),
                fn ($cast) => in_array($cast, ['date', 'datetime', 'immutable_date', 'immutable_datetime'], true)
            ))));

            foreach ($dateKeys as $key) {
                if ($data->{$key} instanceof DateTimeInterface) {
                    $array[$key] = Carbon::instance($data->{$key})->format('Y-m-d H:i:s');
                }
            }

            return self::formatDateTimes($array);
        }

        if ($data instanceof Collection) {
            return $data->map(fn ($item) => self::formatDateTimes($item))->all();
        }

        if (is_array($data)) {
            $formatted = [];
            foreach ($data as $key => $value) {
                $formatted[$key] = self::formatDateTimes($value);
            }
            return $formatted;
        }

        return $data;
    }
}
