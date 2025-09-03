<?php

namespace App\Services;

use App\Mail\sendMail;


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


class verificationService
{
    public static function convertToIranFormat($mobileNumber) {
        $mobileNumber = preg_replace('/\D/', '', $mobileNumber);

        if (substr($mobileNumber, 0, 2) === '09') {
            return '98' . substr($mobileNumber, 1);
        }
        elseif (substr($mobileNumber, 0, 3) === '989') {
            return $mobileNumber;
        }
        elseif (substr($mobileNumber, 0, 4) === '+989') {
            return substr($mobileNumber, 1);
        }
        else {
            return false;
        }
    }

    public static function sendCode($dest, $type)
    {
        $code = verificationCodeService::generteCode();
        verificationCodeService::set($dest, $code);
        if ($type == 'email')
        {
           self::SendMail($dest , $code);
            Log::info($code);
        }
        elseif ($type == 'mobile')
        {
            $sms = self::sendSms($dest , $code);
            Log::info($code .'         '. $sms);
        }

    }



    public static function sendMail($dest, $mail)
    {
        Mail::to($dest)->send(new sendMail($mail));

    }



        public static function sendSms($dest, $code )
            {
    $template = config('services.sms.template');
    $search = ['CODE'];
    $replace = [$code];
    $msg = str_replace($search , $replace , $template);
    $response = Http::withoutVerifying()->withHeaders([
        'Content-Type' => 'application/x-www-form-urlencoded',
    ])->asForm()->post(config('services.sms.url'), [
        'username' => config('services.sms.username'),
        'password' => config('services.sms.password'),
        'Source' => config('services.sms.source'),
        'Message' => $msg,
        'destination' => self::convertToIranFormat($dest)
    ]);
    Log::info('SMS response', ['body' => $response->body(), 'status' => $response->status(), 'to' => $dest]);
           return $response->body();

}

 

}