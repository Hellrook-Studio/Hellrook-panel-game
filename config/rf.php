<?php

/*
 * Fitur RF Online 4.55 di panel (tab "RF Online" untuk server dari egg dengan feature "rf455").
 */
return [
    // Host yang dipakai panel untuk menanyakan status server RF ke port launcher (port game + 1).
    // Kosong: IP allocation server, atau host.docker.internal kalau allocation-nya 0.0.0.0.
    'status_host' => env('RF_STATUS_HOST', ''),
];
