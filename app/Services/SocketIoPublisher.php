<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocketIoPublisher
{
    public function emit(string $event, array $data): void
    {
        if (! config('services.socket_io.enabled', true)) {
            return;
        }

        $emitUrl = (string) config('services.socket_io.emit_url');
        $secret = (string) config('services.socket_io.emit_secret');

        if ($emitUrl === '') {
            return;
        }

        $body = json_encode([
            'secret' => $secret,
            'event' => $event,
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            return;
        }

        try {
            if (PHP_SAPI === 'cli') {
                $this->emitViaStream($emitUrl, $body, $event);
            } else {
                Http::timeout(2)
                    ->withBody($body, 'application/json')
                    ->post($emitUrl)
                    ->throw();
            }
        } catch (\Throwable $exception) {
            Log::warning('Socket.IO emit failed', [
                'event' => $event,
                'url' => $emitUrl,
                'message' => $exception->getMessage(),
            ]);

            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "[Socket.IO] emit failed ({$emitUrl}): {$exception->getMessage()}\n");
            }
        }
    }

    private function emitViaStream(string $url, string $body, string $event): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException('Connection refused or unreachable');
        }

        $decoded = json_decode($response, true);
        if (! is_array($decoded) || ! ($decoded['ok'] ?? false)) {
            throw new \RuntimeException('Unexpected response: '.$response);
        }

        fwrite(STDERR, "[Socket.IO] emitted \"{$event}\"\n");
    }
}
