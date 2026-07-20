<?php

namespace App\Services\Ocpp\Adapters;

use App\Services\ChargePointRealtimePublisher;
use App\Services\Ocpp\ChargingService;
use App\Services\Ocpp\Contracts\OcppAdapterInterface;
use App\Services\Ocpp\TransactionService;
use Illuminate\Support\Facades\DB;

class Ocpp21Adapter implements OcppAdapterInterface
{
    public function __construct(
        private readonly ChargingService $chargingService,
        private readonly ChargePointRealtimePublisher $realtimePublisher,
        private readonly TransactionService $transactionService
    ) {
    }
    public function handleCall(string $action, array $payload, array $context): ?array
    {
        return match ($action) {
            'BootNotification' => $this->handleBootNotification($payload, $context),
            'Heartbeat' => $this->handleHeartbeat($context),
            'StatusNotification' => $this->handleStatusNotification($payload, $context),
            'TransactionEvent' => $this->handleTransactionEvent($payload, $context),
            'MeterValues' => $this->handleMeterValues($payload, $context),
            'Authorize' => $this->handleAuthorize($payload),
            'NotifyReport' => [],
            default => null,
        };
    }

    private function handleBootNotification(array $payload, array $context): array
    {
        DB::table('charge_points')
            ->where('id', $context['charge_point_pk'])
            ->update([
                'is_online' => true,
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);

        $this->realtimePublisher->publishById((int) $context['charge_point_pk']);

        return [
            'status' => 'Accepted',
            'currentTime' => now()->toIso8601String(),
            'interval' => 30,
        ];
    }

    private function handleHeartbeat(array $context): array
    {
        DB::table('charge_points')
            ->where('id', $context['charge_point_pk'])
            ->update([
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'currentTime' => now()->toIso8601String(),
        ];
    }

    private function handleStatusNotification(array $payload, array $context): array
    {
        $ocppStatus = (string) ($payload['connectorStatus'] ?? 'Unavailable');

        $statusMap = [
            'Available' => 'Available',
            'Occupied' => 'Charging',
            'Charging' => 'Charging',
            'Reserved' => 'Reserved',
            'Unavailable' => 'Unavailable',
            'Faulted' => 'Faulted',
        ];

        $connectorNo = (int) ($payload['connectorId'] ?? 0);
        if ($connectorNo < 1) {
            $connectorNo = (int) ($payload['evseId'] ?? 1);
        }
        if ($connectorNo < 1) {
            $connectorNo = 1;
        }

        $this->chargingService->handleStatusNotification($context, [
            'connectorId' => $connectorNo,
            'status' => $statusMap[$ocppStatus] ?? 'Unavailable',
        ]);

        return [];
    }

    private function handleTransactionEvent(array $payload, array $context): array
    {
        $eventType = (string) ($payload['eventType'] ?? '');
        $transactionInfo = $payload['transactionInfo'] ?? [];
        $transactionId = (string) ($transactionInfo['transactionId'] ?? '');
        $connectorNo = (int) ($payload['evse']['connectorId'] ?? 0);
        if ($connectorNo < 1) {
            $connectorNo = (int) ($payload['evse']['id'] ?? 1);
        }
        if ($connectorNo < 1) {
            $connectorNo = 1;
        }

        $idToken = (string) ($payload['idToken']['idToken'] ?? 'UNKNOWN');

        $connector = $this->chargingService->resolveOrCreateConnector(
            companyId: (int) $context['company_id'],
            chargePointPk: (int) $context['charge_point_pk'],
            connectorNo: $connectorNo
        );

        if ($eventType === 'Started') {
            $existingTransaction = DB::table('transactions')
                ->where('connector_id', $connector->id)
                ->whereNull('stopped_at')
                ->first();

            if (! $existingTransaction) {
                $this->transactionService->handleStartTransaction($context, [
                    'connectorId' => $connectorNo,
                    'idTag' => $idToken,
                    'meterStart' => (int) ($payload['meterValue'][0]['sampledValue'][0]['value'] ?? 0),
                    'timestamp' => $payload['timestamp'] ?? null,
                ]);
            } else {
                DB::table('connectors')
                    ->where('id', $connector->id)
                    ->update(['status' => 'Charging', 'updated_at' => now()]);

                DB::table('charge_points')
                    ->where('id', $context['charge_point_pk'])
                    ->update(['status' => 'Charging', 'is_online' => true, 'updated_at' => now()]);
            }
        }

        if ($eventType === 'Ended') {
            $transaction = DB::table('transactions')
                ->where('connector_id', $connector->id)
                ->where('status', 'ongoing')
                ->first();

            if ($transaction) {
                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'meter_stop' => (int) ($payload['meterValue'][0]['sampledValue'][0]['value'] ?? 0),
                        'stopped_at' => now(),
                        'stop_reason' => $payload['triggerReason'] ?? 'Local',
                        'status' => 'completed',
                        'updated_at' => now(),
                    ]);
            }

            DB::table('connectors')
                ->where('id', $connector->id)
                ->update(['status' => 'Available', 'updated_at' => now()]);

            DB::table('charge_points')
                ->where('id', $context['charge_point_pk'])
                ->update(['status' => 'Available', 'updated_at' => now()]);
        }

        if ($eventType === 'Updated' && isset($payload['meterValue'])) {
            $this->processMeterValues($payload['meterValue'], $context, $connector->id);
        }

        if (in_array($eventType, ['Started', 'Ended'], true)) {
            $this->realtimePublisher->publishById((int) $context['charge_point_pk']);
        }

        return [];
    }

    private function handleMeterValues(array $payload, array $context): array
    {
        $connectorNo = (int) ($payload['connectorId'] ?? 0);
        if ($connectorNo < 1) {
            $connectorNo = (int) ($payload['evseId'] ?? 1);
        }
        if ($connectorNo < 1) {
            $connectorNo = 1;
        }

        $connector = $this->chargingService->resolveOrCreateConnector(
            companyId: (int) $context['company_id'],
            chargePointPk: (int) $context['charge_point_pk'],
            connectorNo: $connectorNo
        );

        if (isset($payload['meterValue'])) {
            $this->processMeterValues($payload['meterValue'], $context, $connector->id);
        }

        return [];
    }

    private function processMeterValues(array $meterValues, array $context, int $connectorId): void
    {
        foreach ($meterValues as $meterValue) {
            $sampledValues = $meterValue['sampledValue'] ?? [];
            $timestamp = now()->parse($meterValue['timestamp'] ?? now())->toDateTimeString();

            foreach ($sampledValues as $sample) {
                $measurand = (string) ($sample['measurand'] ?? 'Energy.Active.Import.Register');
                $value = (float) ($sample['value'] ?? 0);
                $unit = (string) ($sample['unitOfMeasure']['unit'] ?? 'Wh');

                DB::table('meter_values')->insert([
                    'company_id' => $context['company_id'],
                    'charge_point_id' => $context['charge_point_pk'],
                    'connector_id' => $connectorId,
                    'measurand' => $measurand,
                    'value' => $value,
                    'unit' => $unit,
                    'sampled_at' => $timestamp,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Broadcast meter value
                event(new \App\Events\MeterValueReceived([
                    'charge_point_pk' => $context['charge_point_pk'],
                    'connector_id' => $connectorId,
                    'measurand' => $measurand,
                    'value' => $value,
                    'unit' => $unit,
                    'sampled_at' => $timestamp,
                ]));
            }
        }
    }

    private function handleAuthorize(array $payload): array
    {
        return [
            'idTokenInfo' => [
                'status' => 'Accepted',
            ],
        ];
    }
}

