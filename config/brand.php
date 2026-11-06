<?php

/*
 * Branding panel. Nama panel diambil dari APP_NAME (atau Admin, Settings, Company Name).
 * Logo dan favicon adalah file di public/brand/ (logo.svg, favicon.svg).
 */
return [
    // Link footer, mis. landing page. Kosong: footer tanpa link.
    'url' => env('BRAND_URL', ''),

    // Teks footer. Kosong: "© <tahun> <nama panel>".
    'footer' => env('BRAND_FOOTER', ''),
];
