<?php

namespace App\Services\Ocpp;

use App\Services\ChargePointRealtimePublisher;
use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\DB;

class OcppWebSocketServer
{
    /** @var resource|null */
    private $server = null;

    /** @var array<int, resource> */
    private array $clients = [];

    /** @var array<int, array<string, mixed>> */
    private array $clientMeta = [];

    public function __construct(
        private readonly OcppMessageService $messageService,
        private readonly OcppCommandService $commandService,
        private readonly ChargePointRealtimePublisher $realtimePublisher
    ) {
    }

    public function serve(string $host, int $port): void
    {
        $this->server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errorString);

        if (! $this->server) {
            throw new RuntimeException("Unable to start OCPP server: {$errorString} ({$errno})");
        }

        stream_set_blocking($this->server, false);
        $this->writeConsole('[OCPP] server PID='.getmypid().' | log file: storage/logs/ocpp-live.log');
        $this->writeConsole("[OCPP] listening on ws://{$host}:{$port}/ocpp/{charge_point_id}");
        $this->writeConsole('[OCPP] menunggu charging station connect... (jalankan simulator di terminal lain)');
        $this->writeConsole('[OCPP] contoh: node CIMORINGS-master/testing/advanced-test.js CP-acme-001 ws://127.0.0.1:9001/ocpp');

        while (true) {
            $read = array_merge([$this->server], $this->clients);
            $write = null;
            $except = null;

            if (@stream_select($read, $write, $except, 1) === false) {
                continue;
            }

            foreach ($read as $socket) {
                if ($socket === $this->server) {
                    $this->acceptConnection();
                    continue;
                }

                $this->handleClientData($socket);
            }
        }
    }

    private function acceptConnection(): void
    {
        $client = @stream_socket_accept($this->server, 0);
        if (! $client) {
            return;
        }

        stream_set_blocking($client, false);
        $request = '';
        $start = microtime(true);

        while (! str_contains($request, "\r\n\r\n") && (microtime(true) - $start) < 2) {
            $chunk = fread($client, 2048);
            if ($chunk === false || $chunk === '') {
                usleep(10000);
                continue;
            }
            $request .= $chunk;
        }

        [$path, $headers] = $this->parseHttpRequest($request);
        $station = $this->resolveStationContext($path);

        if (! $station || ! isset($headers['sec-websocket-key'])) {
            $this->writeConsole('[OCPP] koneksi ditolak path='.$path.' (charge point tidak dikenali atau bukan WebSocket)');
            fwrite($client, "HTTP/1.1 404 Not Found\r\nConnection: close\r\n\r\n");
            fclose($client);

            return;
        }

        $acceptKey = base64_encode(
            sha1($headers['sec-websocket-key'].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
        );

        fwrite($client, implode("\r\n", [
            'HTTP/1.1 101 Switching Protocols',
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Accept: '.$acceptKey,
            "\r\n",
        ]));

        $id = (int) $client;
        $this->clients[$id] = $client;
        $this->clientMeta[$id] = $station;

        DB::table('charge_points')
            ->where('id', (int) $station['charge_point_pk'])
            ->update([
                'is_online' => true,
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);
        $this->realtimePublisher->publishById((int) $station['charge_point_pk']);

        $this->writeConsole("[OCPP] connected {$station['charge_point_code']} — siap terima payload");
    }

    /**
     * @param resource $socket
     */
    private function handleClientData($socket): void
    {
        $id = (int) $socket;
        $data = @fread($socket, 8192);

        if ($data === false || $data === '') {
            if (feof($socket)) {
                $this->closeClient($socket);
            }

            return;
        }

        $message = $this->decodeFrame($data);
        if ($message === null) {
            return;
        }

        $chargePointCode = (string) ($this->clientMeta[$id]['charge_point_code'] ?? '?');

        try {
            $result = $this->messageService->processIncoming($message, $this->clientMeta[$id]);
            if (! ($result['skip'] ?? false) && isset($result['response'])) {
                $encoded = json_encode($result['response'], JSON_UNESCAPED_SLASHES);
                @fwrite($socket, $this->encodeFrame($encoded ?: '[]'));
            }

            foreach ($this->commandService->reservePendingFrames($this->clientMeta[$id]) as $frame) {
                $encodedFrame = json_encode($frame, JSON_UNESCAPED_SLASHES);
                @fwrite($socket, $this->encodeFrame($encodedFrame ?: '[]'));
            }
        } catch (Throwable $e) {
            $this->writeConsole("[OCPP] {$chargePointCode} error: ".$e->getMessage());
            $fallback = [4, uniqid('msg_', true), 'InternalError', $e->getMessage(), (object) []];
            @fwrite($socket, $this->encodeFrame(json_encode($fallback, JSON_UNESCAPED_SLASHES) ?: '[]'));
        }
    }

    /**
     * @param resource $socket
     */
    private function closeClient($socket): void
    {
        $id = (int) $socket;
        if (isset($this->clientMeta[$id])) {
            $chargePointPk = (int) $this->clientMeta[$id]['charge_point_pk'];
            DB::table('connectors')
                ->where('charge_point_id', $chargePointPk)
                ->whereIn('status', ['Charging', 'Occupied'])
                ->update([
                    'status' => 'Available',
                    'updated_at' => now(),
                ]);

            DB::table('charge_points')
                ->where('id', $chargePointPk)
                ->update([
                    'is_online' => false,
                    'status' => 'Available',
                    'updated_at' => now(),
                ]);

            $this->realtimePublisher->publishById($chargePointPk);
            $this->writeConsole("[OCPP] disconnected {$this->clientMeta[$id]['charge_point_code']}");
        }

        @fclose($socket);
        unset($this->clients[$id], $this->clientMeta[$id]);
    }

    private function writeConsole(string $message): void
    {
        $line = '['.date('H:i:s').'] '.rtrim($message)."\n";
        fwrite(STDOUT, $line);

        if (function_exists('flush')) {
            flush();
        }

        @file_put_contents(storage_path('logs/ocpp-live.log'), $line, FILE_APPEND);
    }

    /**
     * @return array{0:string,1:array<string,string>}
     */
    private function parseHttpRequest(string $request): array
    {
        $lines = preg_split('/\r\n/', $request) ?: [];
        $path = '/';
        $headers = [];

        if (isset($lines[0]) && preg_match('#^\w+\s+([^\s]+)\s+HTTP/[\d.]+$#', $lines[0], $m)) {
            $path = $m[1];
        }

        foreach (array_slice($lines, 1) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $headers[strtolower($name)] = $value;
        }

        return [$path, $headers];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStationContext(string $path): ?array
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        if (! preg_match('#^/ocpp/([A-Za-z0-9\-_\.]+)$#', $path, $match)) {
            return null;
        }

        $chargePointCode = $match[1];

        $station = DB::table('charge_points')
            ->select('id', 'company_id', 'charge_point_id', 'ocpp_version')
            ->where('charge_point_id', $chargePointCode)
            ->first();

        if (! $station) {
            return null;
        }

        return [
            'charge_point_pk' => (int) $station->id,
            'company_id' => (int) $station->company_id,
            'charge_point_code' => (string) $station->charge_point_id,
            'ocpp_version' => (string) ($station->ocpp_version ?: '1.6'),
        ];
    }

    private function decodeFrame(string $data): ?string
    {
        $length = ord($data[1] ?? "\x00") & 127;
        $offset = 2;

        if ($length === 126) {
            if (strlen($data) < 4) {
                return null;
            }
            $length = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            if (strlen($data) < 10) {
                return null;
            }
            $parts = unpack('N2', substr($data, 2, 8));
            $length = ($parts[1] << 32) + $parts[2];
            $offset = 10;
        }

        $mask = substr($data, $offset, 4);
        $offset += 4;
        $payload = substr($data, $offset, $length);

        if ($mask === '' || $payload === '') {
            return null;
        }

        $text = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $text .= $payload[$i] ^ $mask[$i % 4];
        }

        return $text;
    }

    private function encodeFrame(string $payload): string
    {
        $frame = chr(0x81);
        $length = strlen($payload);

        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126).pack('n', $length);
        } else {
            $frame .= chr(127).pack('NN', 0, $length);
        }

        return $frame.$payload;
    }
}

