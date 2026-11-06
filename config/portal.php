<?php

return [
    // Alamat portal customer (aplikasi Go di folder portal\ project ini).
    'url' => env('PORTAL_URL', ''),

    // Secret bersama portal dan panel untuk menandatangani token SSO. Kosong: SSO mati.
    'sso_secret' => env('PORTAL_SSO_SECRET', ''),
];
