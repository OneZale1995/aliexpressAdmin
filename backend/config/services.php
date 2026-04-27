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
        'fbs_mock' => env('ALIEXPRESS_FBS_MOCK', env('APP_ENV', 'production') === 'local'),
        'fbs_default_length' => env('ALIEXPRESS_FBS_DEFAULT_LENGTH', 20),
        'fbs_default_width' => env('ALIEXPRESS_FBS_DEFAULT_WIDTH', 10),
        'fbs_default_height' => env('ALIEXPRESS_FBS_DEFAULT_HEIGHT', 5),
        'fbs_default_weight' => env('ALIEXPRESS_FBS_DEFAULT_WEIGHT', 0.5),
        'transfer_sheet_create_path' => env('ALIEXPRESS_TRANSFER_SHEET_CREATE_PATH', ''),
        'transfer_sheet_print_path' => env('ALIEXPRESS_TRANSFER_SHEET_PRINT_PATH', ''),
    ],

    'chinapost' => [
        'base_url' => env('CHINAPOST_BASE_URL', 'https://211.156.197.248:443'),
        'ec_company_id' => env('CHINAPOST_EC_COMPANY_ID', ''),
        'authorization' => env('CHINAPOST_AUTHORIZATION', ''),
        'digest_key' => env('CHINAPOST_DIGEST_KEY', ''),
        'mail_type' => env('CHINAPOST_MAIL_TYPE', ''),
        'wh_code' => env('CHINAPOST_WH_CODE', ''),
        'verify_ssl' => env('CHINAPOST_VERIFY_SSL', false),
        'api_codes' => [
            'create_order' => env('CHINAPOST_API_CODE_CREATE_ORDER', '110001'),
            'allocate_barcode' => env('CHINAPOST_API_CODE_ALLOCATE_BARCODE', '110002'),
            'get_label' => env('CHINAPOST_API_CODE_GET_LABEL', '120001'),
        ],
        'paths' => [
            'open_api' => env('CHINAPOST_PATH_OPEN_API', '/amp-prod-api/f/amp/api/open'),
            'surface_download' => env('CHINAPOST_PATH_SURFACE_DOWNLOAD', '/pcpErp-web/a/pcp/surface/download'),
            'cancel_order' => env('CHINAPOST_PATH_CANCEL_ORDER', '/pcpErp-web/a/pcp/orderService/cancelOrder'),
            'order_fee' => env('CHINAPOST_PATH_ORDER_FEE', '/pcpErp-web/a/pcp/orderFeeService/getOrderFee'),
        ],
        'public' => [
            'version' => env('CHINAPOST_VERSION', '2.0'),
            'open_version' => env('CHINAPOST_OPEN_VERSION', 'V1.0.0'),
            'open_msg_type' => env('CHINAPOST_OPEN_MSG_TYPE', '0'),
            'user_code' => env('CHINAPOST_USER_CODE', ''),
            'send_product_type' => env('CHINAPOST_SEND_PRODUCT_TYPE', false),
            'send_biz_product_no' => env('CHINAPOST_SEND_BIZ_PRODUCT_NO', false),
            'product_type' => env('CHINAPOST_PRODUCT_TYPE', 'E邮宝'),
            'biz_product_no' => env('CHINAPOST_BIZ_PRODUCT_NO', '019'),
            'page_type' => env('CHINAPOST_PAGE_TYPE', 'RM'),
            'label_version' => env('CHINAPOST_LABEL_VERSION', '2'),
            'transfer_type' => env('CHINAPOST_TRANSFER_TYPE', 'HK'),
            'battery_flag' => env('CHINAPOST_BATTERY_FLAG', '0'),
            'undelivery_option' => env('CHINAPOST_UNDELIVERY_OPTION', 2),
            'back_addr' => env('CHINAPOST_BACK_ADDR', ''),
            'back_way' => env('CHINAPOST_BACK_WAY', '1'),
            'declare_source' => env('CHINAPOST_DECLARE_SOURCE', '2'),
            'declare_type' => env('CHINAPOST_DECLARE_TYPE', '1'),
            'declare_curr_code' => env('CHINAPOST_DECLARE_CURR_CODE', 'USD'),
            'forecastshut' => env('CHINAPOST_FORECASTSHUT', '0'),
            'mail_sign' => env('CHINAPOST_MAIL_SIGN', '2'),
            'mail_flag' => env('CHINAPOST_MAIL_FLAG', '0'),
            'printcode' => env('CHINAPOST_PRINTCODE', '0'),
            'valuable_flag' => env('CHINAPOST_VALUABLE_FLAG', '0'),
            'insurance_flag' => env('CHINAPOST_INSURANCE_FLAG', ''),
            'item_unit' => env('CHINAPOST_ITEM_UNIT', '个'),
            'barcode_logistics_company' => env('CHINAPOST_BARCODE_LOGISTICS_COMPANY', 'POST'),
            'barcode_face_type' => env('CHINAPOST_BARCODE_FACE_TYPE', '1'),
            'label_ak' => env('CHINAPOST_LABEL_AK', ''),
        ],
        'sender' => [
            'name' => env('CHINAPOST_SENDER_NAME', ''),
            'company' => env('CHINAPOST_SENDER_COMPANY', ''),
            'post_code' => env('CHINAPOST_SENDER_POSTCODE', ''),
            'phone' => env('CHINAPOST_SENDER_PHONE', ''),
            'mobile' => env('CHINAPOST_SENDER_MOBILE', ''),
            'email' => env('CHINAPOST_SENDER_EMAIL', ''),
            'id_type' => env('CHINAPOST_SENDER_ID_TYPE', ''),
            'id_no' => env('CHINAPOST_SENDER_ID_NO', ''),
            'nation' => 'CN',
            'province' => env('CHINAPOST_SENDER_PROVINCE', ''),
            'city' => env('CHINAPOST_SENDER_CITY', ''),
            'county' => env('CHINAPOST_SENDER_COUNTY', ''),
            'address' => env('CHINAPOST_SENDER_ADDRESS', ''),
            'gis' => env('CHINAPOST_SENDER_GIS', ''),
            'linker' => env('CHINAPOST_SENDER_LINKER', ''),
        ],
    ],

    'sz56t' => [
        'api_url' => env('SZ56T_API_URL', 'http://www.sz56t.com:8082'),
        'label_url' => env('SZ56T_LABEL_URL', 'http://www.sz56t.com:8089'),
        'cancel_api_url' => env('SZ56T_CANCEL_API_URL', 'http://139.199.207.170:8082/logistics/api'),
        'cancel_auth' => env('SZ56T_CANCEL_AUTH', ''),
        'username' => env('SZ56T_USERNAME', ''),
        'password' => env('SZ56T_PASSWORD', ''),
        'customer_id' => env('SZ56T_CUSTOMER_ID', ''),
        'customer_userid' => env('SZ56T_CUSTOMER_USERID', ''),
        'trade_type' => env('SZ56T_TRADE_TYPE', 'ZYXT'),
    ],

];
