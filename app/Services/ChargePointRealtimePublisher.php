<?php

namespace App\Services;

use App\Events\ChargePointStatusUpdated;
use Illuminate\Support\Facades\DB;

class ChargePointRealtimePublisher
{
    public function __construct(private readonly SocketIoPublisher $socketIoPublisher)
    {
    }

    public function publishById(int $chargePointId): void
    {
        $row = DB::table('charge_points')
            ->leftJoin('companies', 'companies.id', '=', 'charge_points.company_id')
            ->select(
                'charge_points.id',
                'charge_points.company_id',
                'charge_points.charge_point_id',
                'charge_points.name',
                'companies.name as company_name',
                'companies.code as company_code',
                'charge_points.ocpp_version',
                'charge_points.status',
                'charge_points.is_online',
                'charge_points.created_at',
                'charge_points.updated_at'
            )
            ->where('charge_points.id', $chargePointId)
            ->first();

        if (! $row) {
            return;
        }

        $payload = [
            'id' => (int) $row->id,
            'company_id' => (int) $row->company_id,
            'charge_point_id' => (string) $row->charge_point_id,
            'name' => (string) $row->name,
            'company_name' => $row->company_name ? (string) $row->company_name : null,
            'company_code' => $row->company_code ? (string) $row->company_code : null,
            'ocpp_version' => (string) $row->ocpp_version,
            'status' => (string) $row->status,
            'is_online' => (bool) $row->is_online,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
        ];

        event(new ChargePointStatusUpdated($payload));

        $this->socketIoPublisher->emit('charge-point.status.updated', $payload);
        $this->socketIoPublisher->emit('charge_point_status_updated', $payload);
    }
}

