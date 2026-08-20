<?php

declare(strict_types=1);

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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
    ],

    'aqayepardakht' => [
        'pin' => env('AQAYEPARDAKHT_PIN'),
    ],

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY'),
        'sender' => env('KAVENEGAR_SENDER', '10004346'),
    ],

    'sms' => [
        'owner_phone' => env('OWNER_PHONE', ''),
    ],

    'platform_ai' => [
        'api_key' => env('PLATFORM_AI_API_KEY'),
        'base_url' => env('PLATFORM_AI_BASE_URL'),
        'model' => env('PLATFORM_AI_MODEL', 'gpt-4o-mini'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY', ''),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY', ''),
        'proxy' => env('OPENROUTER_HTTP_PROXY', ''),
    ],

    'gapgpt' => [
        'endpoint' => env('GAPGPT_ENDPOINT', 'https://api.gapgpt.app/v1/chat/completions'),
    ],

];
