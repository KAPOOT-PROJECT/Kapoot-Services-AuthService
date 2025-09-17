<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sms' => [
        'url' => env('SMS_SERVICE_URL', 'https://panel.asanak.com/webservice/v1rest/sendsms'),
        'username' => env('SMS_SERVICE_USERNAME', 'KouroshKhalili'),
        'password' => env('SMS_SERVICE_PASSWORD', 'kourosh1386'),
        'source' => env('SMS_SERVICE_SOURCE_NUMBER', '9821021000'),
        'template' => env('SMS_TEMPLATE', 'کاربر گرامی NAME عزیز با شماره موبایل MOBILE ورود شما را به سایت کویزر خوش آمد میگویم کد ورود شما به سایت : CODE است '),
        'teacherTemplate' => env('SMS_TEMPLATE_TEACHER', 'استاد گرامی NAME گرامی با شماره موبایل MOBILE ورود شما به سایت کویزر را مفتخر هستیم کد ورود شما به سایت CODE است '),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'rabbitmq' => [
        'host' => 'localhost',
        'port' => 5673,
        'user' => 'guest',
        'password' => 'guest',
        'exchange' => 'kapoot_events',
        'exchange_type' => 'direct'
    ],
];
