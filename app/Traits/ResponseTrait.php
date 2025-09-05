<?php

namespace App\Traits;

trait ResponseTrait
{
    public static function success($data = null, $message = 'عملیات با موفقیت انجام شد', $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error($data = null, $message = 'خطا رخ داده است', $code = 500)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $data,
        ], $code);
    }
}
