<?php

return [

    /*
    |--------------------------------------------------------------------------
    | mPDF defaults
    |--------------------------------------------------------------------------
    |
    | Read by niklasravnsborg/laravel-pdf for anything a caller does not pass
    | explicitly. App\Support\PdfDocument supplies the page size and margins per
    | document, so what is left here is what every PDF in the app shares.
    |
    */

    'mode' => 'utf-8',
    'format' => 'A4',
    'orientation' => 'P',

    'author' => '',
    'subject' => '',
    'keywords' => '',
    'creator' => 'Jewel',
    'display_mode' => 'fullpage',

    // The package's own default is base_path('../temp/') — outside the project,
    // and usually not writable. mPDF needs this for font and image caching.
    'tempDir' => storage_path('app/mpdf'),

    'pdf_a' => false,
    'pdf_a_auto' => false,
    'icc_profile_path' => '',
    'font_path' => base_path('resources/fonts/'),
    'font_data' => [
        'DejaVuSansCondensed' => [
            'R' => 'DejaVuSansCondensed.ttf',    // regular font
            'useOTL' => 0xFF,    // required for complicated langs like Persian, Arabic and Chinese
            'useKashida' => 75,  // required for complicated langs like Persian, Arabic and Chinese
        ],
    ],

];
