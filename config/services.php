<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
     * WhatsApp Cloud API.
     *
     * The token is a Meta System User token, so it lives here and never in the
     * database — the same rule AppSetting::MEDIA_DISKS states for S3. What the
     * shop chooses (which template, in which language, and whether to send at
     * all) is in whatsapp_templates; what authenticates is in .env.
     */
    'whatsapp' => [
        'base_url' => env('WA_BASE_URL', 'https://graph.facebook.com'),
        'api_version' => env('WA_API_VERSION', 'v23.0'),
        'phone_number_id' => env('WA_PHONE_ID'),
        'token' => env('WA_TOKEN'),
        // Numbers are typed bare at the counter; this is what completes them.
        'country_code' => env('WA_COUNTRY_CODE', '91'),
        'timeout' => (int) env('WA_TIMEOUT', 10),
    ],

];
