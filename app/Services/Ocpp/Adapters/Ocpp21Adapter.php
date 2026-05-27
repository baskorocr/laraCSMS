<?php

namespace App\Services\Ocpp\Adapters;

use App\Services\Ocpp\Contracts\OcppAdapterInterface;

class Ocpp21Adapter implements OcppAdapterInterface
{
    public function handleCall(string $action, array $payload, array $context): ?array
    {
        return match ($action) {
            'BootNotification' => [
                'status' => 'Accepted',
                'currentTime' => now()->toIso8601String(),
                'interval' => 30,
            ],
            'Heartbeat' => [
                'currentTime' => now()->toIso8601String(),
            ],
            'TransactionEvent', 'StatusNotification', 'MeterValues' => [],
            default => null,
        };
    }
}

