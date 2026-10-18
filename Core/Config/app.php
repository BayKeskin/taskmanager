<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Uygulama Çalışma Ortamı (Environment)
    |--------------------------------------------------------------------------
    | 'development' : Geliştirme modu. Admin panelinde ACF Alan Grupları,
    |                 İçerik Türleri ve ACF Kod Rehberi menüleri görünür.
    | 'production'  : Canlı/Müşteri modu. Geliştirici ve ACF yapılandırma
    |                 menüleri gizlenir, müşteriye sade bir panel sunulur.
    */
    'env' => 'development', // 'development' veya 'production'

    /*
    |--------------------------------------------------------------------------
    | Varsayılan Uygulama Dili
    |--------------------------------------------------------------------------
    | Müşterinizin ülkesine göre burayı 'tr', 'en' veya 'de' yapmanız yeterlidir.
    */
    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Yedek (Fallback) Dil
    |--------------------------------------------------------------------------
    | Çeviri aktif dilde bulunamazsa devreye girecek varsayılan dil.
    */
    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Desteklenen Diller
    |--------------------------------------------------------------------------
    */
    'locales' => [
        'tr' => [
            'name' => 'Türkçe',
            'flag' => '🇹🇷',
            'code' => 'tr'
        ],
        'en' => [
            'name' => 'English',
            'flag' => '🇺🇸',
            'code' => 'en'
        ],
        'de' => [
            'name' => 'Deutsch',
            'flag' => '🇩🇪',
            'code' => 'de'
        ],
    ],
];
