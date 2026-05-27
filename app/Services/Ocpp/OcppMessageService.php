<?php

namespace App\Services\Ocpp;

use App\Events\OcppIncomingMessageReceived;
use App\Jobs\ProcessMeterValuesJob;
use App\Jobs\ProcessStatusNotificationJob;
use App\Jobs\ProcessStopTransactionJob;
use Illuminate\Support\Facades\DB;

class OcppMessageService
{
    public function __construct(
        private readonly OcppAdapterManager $adapterManager,
        private readonly TransactionService $transactionService,
        private readonly OcppCommandService $commandService
    ) {
    }

    /**
     * @param array<string, mixed> $stationContext
     * @return array<string, mixed>
     */
    public function processIncoming(string $rawMessage, array $stationContext): array
    {
        $decoded = json_decode($rawMessage, true);

        if (! is_array($decoded) || ! isset($decoded[0], $decoded[1]) || ! is_int($decoded[0])) {
            return $this->callError('unknown', 'FormatViolation', 'Invalid OCPP frame.');
        }

        $messageTypeId = (int) $decoded[0];
        $messageUid = (string) ($decoded[1] ?? '');
        $action = null;
        $payload = [];

        if ($messageTypeId === 2) {
            $action = (string) ($decoded[2] ?? '');
            $payload = $decoded[3] ?? [];
        } elseif ($messageTypeId === 3) {
            $payload = $decoded[2] ?? [];
        } elseif ($messageTypeId === 4) {
            $action = 'CallError';
            $payload = [
                'errorCode' => (string) ($decoded[2] ?? ''),
                'errorDescription' => (string) ($decoded[3] ?? ''),
                'errorDetails' => $decoded[4] ?? [],
            ];
        }

        if (! is_array($payload)) {
            $payload = ['raw' => $payload];
        }

        if (in_array($messageTypeId, [2, 3, 4], true)) {
            $this->consoleLogFromChargePoint($stationContext, $messageTypeId, $action, $messageUid, $payload);
        }

        if ($messageUid !== '' && $this->isDuplicate($stationContext['charge_point_pk'], $messageUid, 'incoming')) {
            return [
                'skip' => true,
                'response' => null,
            ];
        }

        $this->logMessage(
            stationContext: $stationContext,
            direction: 'incoming',
            messageTypeId: $messageTypeId,
            action: $action,
            messageUid: $messageUid,
            payload: $payload,
        );

        event(new OcppIncomingMessageReceived([
            'charge_point_id' => (int) $stationContext['charge_point_pk'],
            'charge_point_code' => (string) $stationContext['charge_point_code'],
            'direction' => 'incoming',
            'message_type_id' => $messageTypeId,
            'message_uid' => $messageUid,
            'action' => $action,
            'payload' => $payload,
            'raw_message' => $rawMessage,
            'received_at' => now()->toDateTimeString(),
        ]));

        if ($messageTypeId === 3) {
            $this->commandService->markAcknowledged($stationContext, $messageUid, $payload);
            return [
                'skip' => true,
                'response' => null,
            ];
        }

        if ($messageTypeId === 4) {
            $this->commandService->markError(
                stationContext: $stationContext,
                messageUid: $messageUid,
                errorCode: (string) ($payload['errorCode'] ?? 'GenericError'),
                errorDescription: (string) ($payload['errorDescription'] ?? 'CallError'),
                errorDetails: is_array($payload['errorDetails'] ?? null) ? $payload['errorDetails'] : []
            );
            return [
                'skip' => true,
                'response' => null,
            ];
        }

        if ($messageTypeId !== 2) {
            return [
                'skip' => true,
                'response' => null,
            ];
        }

        if ($action === '' || $messageUid === '') {
            return $this->callError($messageUid, 'FormationViolation', 'Action and message id are required.');
        }

        $responsePayload = $this->buildResponsePayload($action, $payload, $stationContext);

        if ($responsePayload === null) {
            return $this->callError($messageUid, 'NotSupported', "Unsupported action: {$action}");
        }

        $responseFrame = [3, $messageUid, $responsePayload];

        $this->consoleLogToChargePoint($stationContext, $action, $messageUid, $responsePayload);

        $this->logMessage(
            stationContext: $stationContext,
            direction: 'outgoing',
            messageTypeId: 3,
            action: $action,
            messageUid: $messageUid,
            payload: $responsePayload,
        );

        event(new OcppIncomingMessageReceived([
            'charge_point_id' => (int) $stationContext['charge_point_pk'],
            'charge_point_code' => (string) $stationContext['charge_point_code'],
            'direction' => 'outgoing',
            'message_type_id' => 3,
            'message_uid' => $messageUid,
            'action' => $action,
            'payload' => $responsePayload,
            'raw_message' => json_encode($responseFrame),
            'received_at' => now()->toDateTimeString(),
        ]));

        return [
            'skip' => false,
            'response' => $responseFrame,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $stationContext
     * @return array<string,mixed>|null
     */
    private function buildResponsePayload(string $action, array $payload, array $stationContext): ?array
    {
        if ($action === 'StatusNotification') {
            ProcessStatusNotificationJob::dispatchSync($stationContext, $payload);

            return [];
        }

        if ($action === 'MeterValues') {
            // Process immediately so Socket.IO updates are not delayed by queue worker.
            ProcessMeterValuesJob::dispatchSync($stationContext, $payload);

            return [];
        }

        if ($action === 'StopTransaction') {
            ProcessStopTransactionJob::dispatch($stationContext, $payload);

            return ['idTagInfo' => ['status' => 'Accepted']];
        }

        if ($action === 'StartTransaction') {
            return $this->transactionService->handleStartTransaction($stationContext, $payload);
        }

        $adapter = $this->adapterManager->resolve($stationContext['ocpp_version']);

        return $adapter->handleCall($action, $payload, $stationContext);
    }

    /**
     * @param array<string, mixed> $stationContext
     * @param array<string, mixed> $payload
     */
    private function consoleLogFromChargePoint(
        array $stationContext,
        int $messageTypeId,
        ?string $action,
        string $messageUid,
        array $payload
    ): void {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        $chargePointCode = (string) ($stationContext['charge_point_code'] ?? '?');
        $typeLabel = match ($messageTypeId) {
            2 => 'CALL',
            3 => 'CALLRESULT',
            4 => 'CALLERROR',
            default => 'UNKNOWN',
        };
        $actionLabel = $action ?? '-';

        if ($messageTypeId === 2 && $actionLabel === 'Heartbeat') {
            $this->writeOcppConsole(sprintf('[OCPP] %s → Heartbeat', $chargePointCode));

            return;
        }

        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ) ?: '{}';

        $this->writeOcppConsole(sprintf(
            "[OCPP] %s → %s %s (msg=%s)\n  payload: %s",
            $chargePointCode,
            $typeLabel,
            $actionLabel,
            $messageUid !== '' ? $messageUid : '-',
            $payloadJson
        ));
    }

    /**
     * @param array<string, mixed> $stationContext
     * @param array<string, mixed> $payload
     */
    private function consoleLogToChargePoint(
        array $stationContext,
        string $action,
        string $messageUid,
        array $payload
    ): void {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        $chargePointCode = (string) ($stationContext['charge_point_code'] ?? '?');
        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ) ?: '{}';

        $this->writeOcppConsole(sprintf(
            "[OCPP] %s ← CSMS response %s (msg=%s)\n  payload: %s",
            $chargePointCode,
            $action,
            $messageUid,
            $payloadJson
        ));
    }

    private function writeOcppConsole(string $message): void
    {
        $line = '['.date('H:i:s').'] '.rtrim($message)."\n";
        fwrite(STDOUT, $line);

        if (function_exists('flush')) {
            flush();
        }

        @file_put_contents(storage_path('logs/ocpp-live.log'), $line, FILE_APPEND);
    }

    /**
     * @param array<string, mixed> $stationContext
     * @param array<string, mixed> $payload
     */
    private function logMessage(
        array $stationContext,
        string $direction,
        int $messageTypeId,
        ?string $action,
        string $messageUid,
        array $payload
    ): void {
        DB::table('ocpp_messages_log')->insert([
            'company_id' => $stationContext['company_id'],
            'charge_point_id' => $stationContext['charge_point_pk'],
            'ocpp_version' => $stationContext['ocpp_version'],
            'direction' => $direction,
            'message_type_id' => $messageTypeId,
            'action' => $action,
            'message_uid' => $messageUid !== '' ? $messageUid : null,
            'payload' => json_encode($payload),
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function isDuplicate(int $chargePointPk, string $messageUid, string $direction): bool
    {
        return DB::table('ocpp_messages_log')
            ->where('charge_point_id', $chargePointPk)
            ->where('direction', $direction)
            ->where('message_uid', $messageUid)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function callError(string $messageUid, string $errorCode, string $errorDescription): array
    {
        return [
            'skip' => false,
            'response' => [4, $messageUid !== '' ? $messageUid : uniqid('msg_', true), $errorCode, $errorDescription, (object) []],
        ];
    }
}

