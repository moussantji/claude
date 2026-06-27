<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    | WhatsApp number in international format WITHOUT the leading "+" or spaces,
    | e.g. 15551234567. Used to build wa.me links for customer support.
    */
    'whatsapp' => env('STREAMWAVE_WHATSAPP', '15551234567'),

    'support_email' => env('STREAMWAVE_SUPPORT_EMAIL', 'support@streamwave.test'),

    /*
    |--------------------------------------------------------------------------
    | Orange Money
    |--------------------------------------------------------------------------
    | Settings for the Orange Money payment option shown at checkout.
    */
    'orange_money' => [
        'enabled' => env('ORANGE_MONEY_ENABLED', true),
        // Merchant code customers dial / send the payment to (manual fallback).
        'merchant_code' => env('ORANGE_MONEY_MERCHANT', '#144#391*1234#'),
        'merchant_name' => env('ORANGE_MONEY_NAME', 'StreamWave SARL'),

        /*
        | Real Orange Money Web Payment API.
        | When 'api_enabled' is true AND credentials are present, checkout
        | initiates a real transaction and redirects the customer to Orange's
        | hosted payment page. Otherwise a simulated (instant) flow is used so
        | the app stays fully functional in demo/local environments.
        |
        | Get these from https://developer.orange.com (Orange Money Web Payment):
        |   OM_AUTH_HEADER  = base64("<client_id>:<client_secret>")
        |   OM_MERCHANT_KEY = your merchant key
        | Country path segment: 'dev' for sandbox, country code (e.g. 'ci',
        | 'sn', 'cm') in production. Currency: 'OUV' in sandbox, 'XOF' etc live.
        */
        'api_enabled' => env('OM_API_ENABLED', false),
        'base_url' => env('OM_BASE_URL', 'https://api.orange.com'),
        'token_path' => env('OM_TOKEN_PATH', '/oauth/v3/token'),
        'webpay_path' => env('OM_WEBPAY_PATH', '/orange-money-webpay/dev/v1/webpayment'),
        'status_path' => env('OM_STATUS_PATH', '/orange-money-webpay/dev/v1/transactionstatus'),
        'auth_header' => env('OM_AUTH_HEADER', ''),
        'merchant_key' => env('OM_MERCHANT_KEY', ''),
        'currency' => env('OM_CURRENCY', 'OUV'),
        'lang' => env('OM_LANG', 'fr'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported locales for the language switcher
    |--------------------------------------------------------------------------
    */
    'locales' => ['en', 'fr'],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('CURRENCY', 'FCFA'),

    /*
    |--------------------------------------------------------------------------
    | Background images
    |--------------------------------------------------------------------------
    | Used as decorative backgrounds behind hero / section blocks. Swap these
    | URLs for your own branded artwork. Defaults use picsum.photos (reliable).
    */
    'images' => [
        'hero' => env('IMG_HERO', 'https://picsum.photos/seed/streamwave-hero/1920/1080'),
        'channels' => env('IMG_CHANNELS', 'https://picsum.photos/seed/streamwave-channels/1920/700'),
        'pricing' => env('IMG_PRICING', 'https://picsum.photos/seed/streamwave-pricing/1920/700'),
        'cta' => env('IMG_CTA', 'https://picsum.photos/seed/streamwave-cta/1920/700'),
        'contact' => env('IMG_CONTACT', 'https://picsum.photos/seed/streamwave-contact/1920/900'),
        'features' => env('IMG_FEATURES', 'https://picsum.photos/seed/streamwave-features/1920/900'),
    ],
];
