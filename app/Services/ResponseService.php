<?php

namespace App\Services;

class ResponseService
{
    /**
     * استانداردسازی خروجی موفق
     */
    public static function success($data = null, $message = 'عملیات با موفقیت انجام شد', $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * استانداردسازی خروجی خطا
     */
    public static function error($message = 'خطا رخ داده است', $code = 500, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
