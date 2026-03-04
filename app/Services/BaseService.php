<?php

namespace App\Services;

use App\Helpers\ResponseHelper;

/**
 * Base Service Class
 * 
 * Class ini menyediakan fungsi-fungsi dasar untuk service layer
 * dan dapat di-extend oleh service-service lainnya
 * 
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
abstract class BaseService
{
    /**
     * Format response sukses
     * 
     * @param mixed $data Data yang akan dikirim
     * @param string $message Pesan sukses
     * @return array
     */
    protected function successResponse($data, string $message = 'Success', int $code = 200): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => ResponseHelper::formatDateTimes($data),
            'errors' => null,
            'pagination' => null,
            'code' => $code
        ];
    }

    /**
     * Format response error
     * 
     * @param string $message Pesan error
     * @param mixed $errors Detail error tambahan
     * @return array
     */
    protected function errorResponse(string $message, $errors = null, int $code = 400): array
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
     * Validasi input data
     * 
     * @param array $data Data yang akan divalidasi
     * @param array $rules Rules validasi
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateData(array $data, array $rules): array
    {
        return validator($data, $rules)->validate();
    }

    /**
     * Generate unique code
     * 
     * @param string $prefix Prefix untuk kode
     * @param int $length Panjang kode unik
     * @return string
     */
    protected function generateUniqueCode(string $prefix = '', int $length = 8): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $prefix . $code;
    }

    /**
     * Format tanggal ke format Indonesia
     * 
     * @param string $date Tanggal dalam format Y-m-d
     * @return string Tanggal dalam format Indonesia
     */
    protected function formatIndonesianDate(string $date): string
    {
        $bulan = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];
        
        $dateParts = explode('-', $date);
        return $dateParts[2] . ' ' . $bulan[$dateParts[1]] . ' ' . $dateParts[0];
    }
}
