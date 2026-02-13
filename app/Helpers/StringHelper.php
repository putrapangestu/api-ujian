<?php

namespace App\Helpers;

/**
 * String Helper Class
 * 
 * Class ini menyediakan fungsi-fungsi helper untuk manipulasi string
 * 
 * @package App\Helpers
 * @author Development Team
 * @version 1.0.0
 */
class StringHelper
{
    /**
     * Generate random string
     * 
     * @param int $length Panjang string yang ingin dibuat
     * @param string $characters Karakter yang digunakan
     * @return string
     */
    public static function random(int $length = 10, string $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'): string
    {
        $result = '';
        $maxIndex = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[random_int(0, $maxIndex)];
        }
        
        return $result;
    }

    /**
     * Generate unique code with prefix
     * 
     * @param string $prefix Prefix untuk kode
     * @param int $length Panjang kode unik
     * @return string
     */
    public static function generateCode(string $prefix = '', int $length = 8): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $prefix . $code;
    }

    /**
     * Slugify string
     * 
     * @param string $text Teks yang akan diubah menjadi slug
     * @param string $separator Pemisah kata
     * @return string
     */
    public static function slug(string $text, string $separator = '-'): string
    {
        // Replace non letter or digits by separator
        $text = preg_replace('~[^\pL\d]+~u', $separator, $text);
        
        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        
        // Trim
        $text = trim($text, $separator);
        
        // Remove duplicate separator
        $text = preg_replace('~-+~', $separator, $text);
        
        // Lowercase
        $text = strtolower($text);
        
        if (empty($text)) {
            return 'n-a';
        }
        
        return $text;
    }

    /**
     * Truncate string to specified length
     * 
     * @param string $text Teks yang akan dipotong
     * @param int $length Panjang maksimal
     * @param string $suffix Suffix untuk teks yang dipotong
     * @return string
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length - strlen($suffix)) . $suffix;
    }

    /**
     * Mask sensitive string (for emails, phones, etc)
     * 
     * @param string $text Teks yang akan dimask
     * @param int $visibleChars Jumlah karakter yang terlihat di awal dan akhir
     * @param string $maskChar Karakter untuk masking
     * @return string
     */
    public static function mask(string $text, int $visibleChars = 3, string $maskChar = '*'): string
    {
        $length = strlen($text);
        
        if ($length <= $visibleChars * 2) {
            return str_repeat($maskChar, $length);
        }
        
        $start = substr($text, 0, $visibleChars);
        $end = substr($text, -$visibleChars);
        $maskLength = $length - ($visibleChars * 2);
        
        return $start . str_repeat($maskChar, $maskLength) . $end;
    }
}