<?php

namespace App\Services\Ocpp;

use App\Services\ChargePointRealtimePublisher;
use App\Services\MeterValueRealtimePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        private readonly ChargingService $chargingService,
        private readonly ChargePointRealtimePublisher $realtimePublisher,
        private readonly MeterValueRealtimePublisher $meterValueRealtimePublisher
    ) {
    }

    /**
     * @param array<string,mixed> $stationContext
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function handleStartTransaction(array $stationContext, array $payload): array
    {
        $connectorNo = (int) ($payload['connectorId'] ?? 1);
        $connector = $this->chargingService->resolveOrCreateConnector(
            companyId: (int) $stationContext['company_id'],
            chargePointPk: (int) $stationContext['charge_point_pk'],
            connectorNo: $connectorNo
        );

        $id = DB::table('transactions')->insertGetId([
            'company_id' => $stationContext['company_id'],
            'user_id' => null,
            'charge_point_id' => $stationContext['charge_point_pk'],
            'connector_id' => $connector->id,
            'transaction_code' => 'TXN-'.strtoupper(Str::random(10)),
            'id_tag' => (string) ($payload['idTag'] ?? ''),
            'meter_start' => (float) ($payload['meterStart'] ?? 0),
            'meter_stop' => null,
            'started_at' => $this->resolveTimestamp($payload['timestamp'] ?? null),
            'stopped_at' => null,
            'stop_reason' => null,
            'status' => 'ongoing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('connectors')->where('id', $connector->id)->update([
            'status' => 'Charging',
            'updated_at' => now(),
        ]);

        DB::table('charge_points')->where('id', $stationContext['charge_point_pk'])->update([
            'status' => 'Charging',
            'updated_at' => now(),
        ]);

        $this->realtimePublisher->publishById((int) $stationContext['charge_point_pk']);

        return [
            'transactionId' => $id,
            'idTagInfo' => ['status' => 'Accepted'],
        ];
    }

    /**
     * @param array<string,mixed> $stationContext
     * @param array<string,mixed> $payload
     */
    public function handleStopTransaction(array $stationContext, array $payload): void
    {
        $transactionId = (int) ($payload['transactionId'] ?? 0);
        if ($transactionId <= 0) {
            return;
        }

        $transaction = DB::table('transactions')
            ->select('id', 'connector_id')
            ->where('id', $transactionId)
            ->where('company_id', $stationContext['company_id'])
            ->first();

        if (! $transaction) {
            return;
        }

        DB::table('transactions')->where('id', $transaction->id)->update([
            'meter_stop' => (float) ($payload['meterStop'] ?? 0),
            'stopped_at' => $this->resolveTimestamp($payload['timestamp'] ?? null),
            'stop_reason' => (string) ($payload['reason'] ?? null),
            'status' => 'completed',
            'updated_at' => now(),
        ]);

        if ($transaction->connector_id) {
            DB::table('connectors')->where('id', $transaction->connector_id)->update([
                'status' => 'Available',
                'updated_at' => now(),
            ]);
        }

        DB::table('charge_points')->where('id', $stationContext['charge_point_pk'])->update([
            'status' => 'Available',
            'updated_at' => now(),
        ]);

        $this->realtimePublisher->publishById((int) $stationContext['charge_point_pk']);
    }

    /**
     * @param array<string,mixed> $stationContext
     * @param array<string,mixed> $payload
     */
    public function handleMeterValues(array $stationContext, array $payload): void
    {
        $transactionId = (int) ($payload['transactionId'] ?? 0);
        $connectorNo = (int) ($payload['connectorId'] ?? 1);
        $connector = $this->chargingService->resolveOrCreateConnector(
            companyId: (int) $stationContext['company_id'],
            chargePointPk: (int) $stationContext['charge_point_pk'],
            connectorNo: $connectorNo
        );

        $meterValues = is_array($payload['meterValue'] ?? null) ? $payload['meterValue'] : [];
        $inserted = false;

        foreach ($meterValues as $meterValue) {
            $sampledAt = $this->resolveTimestamp($meterValue['timestamp'] ?? null);
            $sampledValues = is_array($meterValue['sampledValue'] ?? null) ? $meterValue['sampledValue'] : [];

            foreach ($sampledValues as $sample) {
                $meterValueId = DB::table('meter_values')->insertGetId([
                    'company_id' => $stationContext['company_id'],
                    'transaction_id' => $transactionId > 0 ? $transactionId : null,
                    'charge_point_id' => $stationContext['charge_point_pk'],
                    'connector_id' => $connector->id,
                    'sampled_at' => $sampledAt,
                    'measurand' => (string) ($sample['measurand'] ?? 'Energy.Active.Import.Register'),
                    'unit' => (string) ($sample['unit'] ?? 'Wh'),
                    'value' => (float) ($sample['value'] ?? 0),
                    'context' => isset($sample['context']) ? (string) $sample['context'] : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->meterValueRealtimePublisher->publishById((int) $meterValueId);
                $inserted = true;
            }
        }

        if ($inserted) {
            $this->meterValueRealtimePublisher->publishForChargePoint((int) $stationContext['charge_point_pk']);
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "[OCPP] meter values saved for {$stationContext['charge_point_code']}, realtime pushed\n");
            }
        }
    }

    private function resolveTimestamp(mixed $timestamp): string
    {
        if (is_string($timestamp) && trim($timestamp) !== '') {
            return date('Y-m-d H:i:s', strtotime($timestamp));
        }

        return now()->toDateTimeString();
    }
}

