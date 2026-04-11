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

    'aliexpress' => [
        'base_url' => env('ALIEXPRESS_BASE_URL', 'https://openapi.aliexpress.ru'),
        // Windows 本地开发环境可临时关闭证书校验；生产环境建议开启
        'verify_ssl' => env('ALIEXPRESS_VERIFY_SSL', false),
    ],

    'chinapost' => [
        'base_url' => env('CHINAPOST_BASE_URL', 'https://211.156.197.248:443'),
        'ec_company_id' => env('CHINAPOST_EC_COMPANY_ID', ''),
        'digest_key' => env('CHINAPOST_DIGEST_KEY', ''),
        'mail_type' => env('CHINAPOST_MAIL_TYPE', ''),
        'wh_code' => env('CHINAPOST_WH_CODE', ''),
        'verify_ssl' => env('CHINAPOST_VERIFY_SSL', false),
        'sender' => [
            'name' => env('CHINAPOST_SENDER_NAME', ''),
            'company' => env('CHINAPOST_SENDER_COMPANY', ''),
            'post_code' => env('CHINAPOST_SENDER_POSTCODE', ''),
            'phone' => env('CHINAPOST_SENDER_PHONE', ''),
            'mobile' => env('CHINAPOST_SENDER_MOBILE', ''),
            'email' => env('CHINAPOST_SENDER_EMAIL', ''),
            'nation' => 'CN',
            'province' => env('CHINAPOST_SENDER_PROVINCE', ''),
            'city' => env('CHINAPOST_SENDER_CITY', ''),
            'county' => env('CHINAPOST_SENDER_COUNTY', ''),
            'address' => env('CHINAPOST_SENDER_ADDRESS', ''),
            'linker' => env('CHINAPOST_SENDER_LINKER', ''),
        ],
    ],

    'sz56t' => [
        'api_url' => env('SZ56T_API_URL', 'http://www.sz56t.com:8082'),
        'label_url' => env('SZ56T_LABEL_URL', 'http://www.sz56t.com:8089'),
        'username' => env('SZ56T_USERNAME', ''),
        'password' => env('SZ56T_PASSWORD', ''),
        'customer_id' => env('SZ56T_CUSTOMER_ID', ''),
        'customer_userid' => env('SZ56T_CUSTOMER_USERID', ''),
        'product_id' => env('SZ56T_PRODUCT_ID', ''),
        'trade_type' => env('SZ56T_TRADE_TYPE', 'ZYXT'),
    ],

];
