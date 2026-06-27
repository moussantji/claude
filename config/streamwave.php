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
        // Merchant code customers dial / send the payment to.
        'merchant_code' => env('ORANGE_MONEY_MERCHANT', '#144#391*1234#'),
        'merchant_name' => env('ORANGE_MONEY_NAME', 'StreamWave SARL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported locales for the language switcher
    |--------------------------------------------------------------------------
    */
    'locales' => ['en', 'fr'],

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
