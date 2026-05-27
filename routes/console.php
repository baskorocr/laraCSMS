<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Services\Auth\RoutePermissionSyncService;
use App\Services\Ocpp\OcppWebSocketServer;
use App\Services\Ocpp\OcppCommandService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('realtime:test {chargePointId=CP-acme-001}', function (string $chargePointId) {
    $pk = DB::table('charge_points')->where('charge_point_id', $chargePointId)->value('id');
    if (! $pk) {
        $this->error("Charge point {$chargePointId} not found.");
        return;
    }

    app(\App\Services\MeterValueRealtimePublisher::class)->publishForChargePoint((int) $pk);
    $this->info("Realtime event pushed for {$chargePointId}. Check Socket.IO terminal for \"emitted meter_values\".");
})->purpose('Push test meter_values event to Socket.IO');

Artisan::command('ocpp:kill {--port=9001}', function () {
    $port = (int) $this->option('port');
    $pattern = ":{$port}";
    $output = shell_exec('netstat -ano | findstr '.$pattern.' | findstr LISTENING') ?? '';
    $pids = [];

    foreach (preg_split('/\R/', trim($output)) as $line) {
        if (preg_match('/\s+(\d+)\s*$/', trim($line), $match)) {
            $pids[] = (int) $match[1];
        }
    }

    $pids = array_values(array_unique(array_filter($pids)));

    if ($pids === []) {
        $this->info("Tidak ada proses LISTENING di port {$port}.");

        return;
    }

    foreach ($pids as $pid) {
        if ($pid <= 0) {
            continue;
        }

        shell_exec('taskkill /PID '.$pid.' /F 2>NUL');
        $this->line("Stopped PID {$pid}");
    }

    $this->info('Port '.$port.' dibersihkan. Jalankan: php artisan ocpp:serve');
})->purpose('Kill stale processes listening on OCPP port');

Artisan::command('ocpp:serve {--host=0.0.0.0} {--port=9001}', function () {
    $host = (string) $this->option('host');
    $port = (int) $this->option('port');

    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

    $listeners = shell_exec('netstat -ano | findstr :'.$port.' | findstr LISTENING') ?? '';
    if (trim($listeners) !== '') {
        $this->warn("Port {$port} sudah dipakai proses lain. Log tidak akan muncul di terminal ini.");
        $this->line($listeners);
        $this->warn('Jalankan dulu: php artisan ocpp:kill --port='.$port);
    }

    $this->components->info("Starting OCPP WS server at ws://{$host}:{$port}/ocpp/{charge_point_id}");

    app(OcppWebSocketServer::class)->serve($host, $port);
})->purpose('Run OCPP WebSocket ingress server');

Artisan::command('ocpp:simulate {chargePointId=CP-acme-001} {--host=127.0.0.1} {--port=9001}', function (string $chargePointId) {
    $host = (string) $this->option('host');
    $port = (int) $this->option('port');
    $socket = stream_socket_client("tcp://{$host}:{$port}", $errno, $error, 3);

    if (! $socket) {
        $this->error("Unable connect to server: {$error} ({$errno})");
        return;
    }

    stream_set_timeout($socket, 2);
    $key = base64_encode(random_bytes(16));
    $request = implode("\r\n", [
        "GET /ocpp/{$chargePointId} HTTP/1.1",
        "Host: {$host}:{$port}",
        'Upgrade: websocket',
        'Connection: Upgrade',
        "Sec-WebSocket-Key: {$key}",
        'Sec-WebSocket-Version: 13',
        "\r\n",
    ]);
    fwrite($socket, $request);
    $response = fread($socket, 2048);

    if (! str_contains((string) $response, '101 Switching Protocols')) {
        $this->error('Handshake failed.');
        fclose($socket);
        return;
    }

    $this->info("Connected simulator for {$chargePointId}");

    $sendFrame = function (array $frame) use ($socket): void {
        $payload = json_encode($frame, JSON_UNESCAPED_SLASHES) ?: '[]';
        $length = strlen($payload);
        $header = chr(0x81);
        $mask = random_bytes(4);

        if ($length <= 125) {
            $header .= chr(0x80 | $length);
        } elseif ($length <= 65535) {
            $header .= chr(0x80 | 126).pack('n', $length);
        } else {
            $header .= chr(0x80 | 127).pack('NN', 0, $length);
        }

        $masked = '';
        for ($i = 0; $i < $length; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        fwrite($socket, $header.$mask.$masked);
    };

    $readFrame = function () use ($socket): ?string {
        $data = fread($socket, 8192);
        if (! $data || strlen($data) < 2) {
            return null;
        }

        $length = ord($data[1]) & 127;
        $offset = 2;
        if ($length === 126) {
            $length = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            $parts = unpack('N2', substr($data, 2, 8));
            $length = ($parts[1] << 32) + $parts[2];
            $offset = 10;
        }

        return substr($data, $offset, $length);
    };

    $sendAndPrint = function (array $frame) use ($sendFrame, $readFrame): void {
        $sendFrame($frame);
        $raw = $readFrame();
        if ($raw) {
            $this->line(">> ".json_encode($frame));
            $this->line("<< ".$raw);
        }
    };

    $sendAndPrint([2, uniqid('msg_', true), 'BootNotification', [
        'chargePointVendor' => 'SimulatorVendor',
        'chargePointModel' => 'SimulatorModel',
    ]]);

    $sendAndPrint([2, uniqid('msg_', true), 'Heartbeat', []]);
    $sendAndPrint([2, uniqid('msg_', true), 'StatusNotification', [
        'connectorId' => 1,
        'errorCode' => 'NoError',
        'status' => 'Available',
        'timestamp' => now()->toIso8601String(),
    ]]);

    fclose($socket);
    $this->info('Simulation finished.');
})->purpose('Run simple OCPP 1.6 simulator');

Artisan::command(
    'ocpp:command {chargePointId} {action} {payload?}',
    function (string $chargePointId, string $action, ?string $payload = null) {
        $station = DB::table('charge_points')
            ->select('id', 'company_id', 'charge_point_id', 'ocpp_version')
            ->where('charge_point_id', $chargePointId)
            ->first();

        if (! $station) {
            $this->error("Charge point {$chargePointId} not found.");
            return;
        }

        $decodedPayload = [];
        if ($payload !== null) {
            $parsed = json_decode($payload, true);
            if (! is_array($parsed)) {
                $this->error('Payload must be JSON object.');
                return;
            }
            $decodedPayload = $parsed;
        }

        $id = app(OcppCommandService::class)->enqueue(
            stationContext: [
                'charge_point_pk' => (int) $station->id,
                'company_id' => (int) $station->company_id,
                'charge_point_code' => (string) $station->charge_point_id,
                'ocpp_version' => (string) ($station->ocpp_version ?: '1.6'),
            ],
            action: $action,
            payload: $decodedPayload
        );

        $this->info("Command queued with id {$id}.");
    }
)->purpose('Queue outbound OCPP CALL to charge point');

Artisan::command(
    'ocpp:commands:reconcile {--timeout=30} {--max-attempts=3}',
    function () {
        $timeout = (int) $this->option('timeout');
        $maxAttempts = (int) $this->option('max-attempts');

        $result = app(OcppCommandService::class)->reconcileTimeouts($timeout, $maxAttempts);

        $this->info("Retried: {$result['retried']}, Timed out: {$result['timed_out']}");
    }
)->purpose('Retry/timed-out reconciliation for sent OCPP commands');

Artisan::command('permissions:sync-routes {routeName?*}', function () {
    $routeNames = $this->argument('routeName');
    $names = is_array($routeNames) && $routeNames !== [] ? $routeNames : null;

    $result = app(RoutePermissionSyncService::class)->sync($names);

    $this->info("Created: {$result['created']}, Existing: {$result['existing']}");

    if ($result['invalid'] !== []) {
        $this->warn('Invalid route names: '.implode(', ', $result['invalid']));
    }
})->purpose('Create permissions from Laravel route names');
