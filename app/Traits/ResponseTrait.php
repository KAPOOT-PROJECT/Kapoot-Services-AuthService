<?php

namespace App\Traits;

trait ResponseTrait
{
    public static function success($data = null, $message = 'عملیات با موفقیت انجام شد' , $errors = null, $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $code);
    }

    public static function error($data = null, $message = 'خطا رخ داده است', $errors = null, $code = 500)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $code);
    }
}
