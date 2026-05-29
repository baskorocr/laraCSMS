<?php

namespace App\Support;

class OcppWebSocketUrl
{
    /**
     * URL untuk konfigurasi charger — ws langsung ke port OCPP (default 9001).
     */
    public static function forChargePoint(string $chargePointCode): string
    {
        return self::directForChargePoint($chargePointCode);
    }

    public static function directForChargePoint(string $chargePointCode): string
    {
        $host = self::resolveHost();
        $port = (int) config('ocpp.ws.direct_port', 9001);
        $prefix = (string) config('ocpp.ws.path_prefix', '/ocpp');

        return 'ws://'.$host.':'.$port.rtrim($prefix, '/').'/'.ltrim($chargePointCode, '/');
    }

    /**
     * WSS via domain (port 443) — hanya jika Nginx mem-proxy /ocpp/ ke 9001.
     */
    public static function secureForChargePoint(string $chargePointCode): string
    {
        $prefix = (string) config('ocpp.ws.path_prefix', '/ocpp');

        return rtrim(self::secureBase(), '/').rtrim($prefix, '/').'/'.ltrim($chargePointCode, '/');
    }

    public static function secureBase(): string
    {
        $configured = config('ocpp.ws.secure_base');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        if (! app()->runningInConsole() && request()->getHost()) {
            $scheme = request()->isSecure() ? 'wss' : 'ws';

            return $scheme.'://'.request()->getHost();
        }

        $appUrl = (string) config('app.url');

        return rtrim((string) preg_replace(['#^https:#i', '#^http:#i'], ['wss:', 'ws:'], $appUrl), '/');
    }

    public static function localForChargePoint(string $chargePointCode): string
    {
        $base = (string) config('ocpp.ws.local_base', 'ws://127.0.0.1:9001');

        return rtrim($base, '/').'/ocpp/'.ltrim($chargePointCode, '/');
    }

    private static function resolveHost(): string
    {
        $configured = config('ocpp.ws.direct_host');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (! app()->runningInConsole() && request()->getHost()) {
            return request()->getHost();
        }

        $appUrl = (string) config('app.url');
        $host = parse_url($appUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }
}
