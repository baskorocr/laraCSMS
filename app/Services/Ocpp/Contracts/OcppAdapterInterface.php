<?php

namespace App\Services\Ocpp\Contracts;

interface OcppAdapterInterface
{
    /**
     * Handle OCPP CALL (messageTypeId=2).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    public function handleCall(string $action, array $payload, array $context): ?array;
}

