<?php

namespace App\Services\Ocpp;

use App\Services\ChargePointRealtimePublisher;
use Illuminate\Support\Facades\DB;

class ChargingService
{
    public function __construct(
        private readonly ChargePointRealtimePublisher $realtimePublisher
    ) {
    }

    /**
     * @param array<string,mixed> $stationContext
     * @param array<string,mixed> $payload
     */
    public function handleStatusNotification(array $stationContext, array $payload): void
    {
        $status = (string) ($payload['status'] ?? 'Unavailable');
        $connectorNo = (int) ($payload['connectorId'] ?? 1);

        $connector = $this->resolveOrCreateConnector(
            companyId: (int) $stationContext['company_id'],
            chargePointPk: (int) $stationContext['charge_point_pk'],
            connectorNo: $connectorNo
        );

        DB::table('connectors')
            ->where('id', $connector->id)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);

        DB::table('charge_points')
            ->where('id', $stationContext['charge_point_pk'])
            ->update([
                'status' => $status,
                'is_online' => true,
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);

        $this->realtimePublisher->publishById((int) $stationContext['charge_point_pk']);
    }

    /**
     * @return object{id:int}
     */
    public function resolveOrCreateConnector(int $companyId, int $chargePointPk, int $connectorNo): object
    {
        $existing = DB::table('connectors')
            ->select('id')
            ->where('company_id', $companyId)
            ->where('charge_point_id', $chargePointPk)
            ->where('evse_id', 1)
            ->where('connector_id', $connectorNo)
            ->first();

        if ($existing) {
            return $existing;
        }

        $id = DB::table('connectors')->insertGetId([
            'company_id' => $companyId,
            'charge_point_id' => $chargePointPk,
            'evse_id' => 1,
            'connector_id' => $connectorNo,
            'connector_type' => 'CCS2',
            'status' => 'Available',
            'max_power_kw' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['id' => $id];
    }
}

