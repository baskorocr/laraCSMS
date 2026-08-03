<?php

return [
    'ws' => [
        'path_prefix' => '/ocpp',

        /*
         * URL langsung ke php artisan ocpp:serve (port terbuka, tanpa TLS).
         * Ini yang dipakai charger jika belum ada proxy Nginx WSS di :443.
         */
        'direct_host' => env('OCPP_WS_DIRECT_HOST'),
        'direct_port' => (int) env('OCPP_WS_DIRECT_PORT', 9001),

        /*
         * WSS via HTTPS / Nginx (port 443) — butuh location /ocpp/ di Nginx.
         */
        'secure_base' => env('OCPP_WS_SECURE_URL'),

        /** Testing dari server yang sama */
        'local_base' => env('OCPP_WS_LOCAL_URL', 'ws://127.0.0.1:9001'),
    ],

    'diagnostics' => [
        /*
         * Upload URL template for GetDiagnostics OCPP command.
         * Placeholders: {charge_point_code}, {message_id}
         */
        'upload_location' => env(
            'OCPP_DIAGNOSTICS_UPLOAD_URL',
            'ftp://ftpadmin:password@127.0.0.1:2121/diagnostics/{charge_point_code}/{message_id}'
        ),

        'ftp' => [
            'host'        => env('OCPP_DIAGNOSTICS_FTP_HOST', '127.0.0.1'),
            'port'        => (int) env('OCPP_DIAGNOSTICS_FTP_PORT', 2121),
            'username'    => env('OCPP_DIAGNOSTICS_FTP_USER', 'ftpadmin'),
            'password'    => env('OCPP_DIAGNOSTICS_FTP_PASSWORD', ''),
            'remote_path' => env('OCPP_DIAGNOSTICS_FTP_REMOTE_PATH', '/diagnostics/{charge_point_code}/{message_id}'),
        ],
    ],
];
