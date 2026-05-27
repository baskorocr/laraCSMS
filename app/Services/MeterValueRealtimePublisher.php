<?php

namespace App\Services;

use App\Events\MeterValueReceived;
use Illuminate\Support\Facades\DB;

class MeterValueRealtimePublisher
{
    public function __construct(private readonly SocketIoPublisher $socketIoPublisher)
    {
    }

    public function publishById(int $meterValueId): void
    {
        $row = DB::table('meter_values')
            ->leftJoin('charge_points', 'charge_points.id', '=', 'meter_values.charge_point_id')
            ->select(
                'meter_values.id',
                'meter_values.company_id',
                'meter_values.charge_point_id as charge_point_pk',
                'meter_values.measurand',
                'meter_values.value',
                'meter_values.unit',
                'meter_values.sampled_at',
                'charge_points.charge_point_id'
            )
            ->where('meter_values.id', $meterValueId)
            ->first();

        if (! $row) {
            return;
        }

        event(new MeterValueReceived([
            'id' => (int) $row->id,
            'company_id' => (int) $row->company_id,
            'charge_point_pk' => (int) $row->charge_point_pk,
            'charge_point_id' => $row->charge_point_id ? (string) $row->charge_point_id : '-',
            'measurand' => (string) $row->measurand,
            'value' => (float) $row->value,
            'unit' => (string) $row->unit,
            'sampled_at' => (string) $row->sampled_at,
        ]));
    }

    public function publishForChargePoint(int $chargePointPk): void
    {
        $chargePointCode = DB::table('charge_points')
            ->where('id', $chargePointPk)
            ->value('charge_point_id');

        if (! $chargePointCode) {
            return;
        }

        $bundle = $this->buildMeterBundle($chargePointPk, (string) $chargePointCode);

        $this->socketIoPublisher->emit('meter_values', $bundle);
        $this->socketIoPublisher->emit('meter-value.received', [
            'charge_point_pk' => $chargePointPk,
            'charge_point_id' => $chargePointCode,
            'measurand' => 'Energy.Active.Import.Register',
            'value' => $bundle['energy'],
            'unit' => 'Wh',
            'sampled_at' => $bundle['sampled_at'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMeterBundle(int $chargePointPk, string $chargePointCode): array
    {
        $latest = DB::table('meter_values')
            ->select('measurand', 'value', 'sampled_at')
            ->where('charge_point_id', $chargePointPk)
            ->whereIn('measurand', [
                'Energy.Active.Import.Register',
                'Power.Active.Import',
                'SoC',
            ])
            ->orderByDesc('id')
            ->get()
            ->unique('measurand')
            ->keyBy('measurand');

        $energyRow = $latest->get('Energy.Active.Import.Register');
        $powerRow = $latest->get('Power.Active.Import');
        $socRow = $latest->get('SoC');
        $sampledAt = (string) (
            $energyRow->sampled_at
            ?? $powerRow->sampled_at
            ?? $socRow->sampled_at
            ?? now()->toDateTimeString()
        );

        return [
            'chargePointId' => $chargePointCode,
            'charge_point_pk' => $chargePointPk,
            'charge_point_id' => $chargePointCode,
            'transactionId' => null,
            'connectorId' => 1,
            'energy' => (float) ($energyRow->value ?? 0),
            'power' => (float) ($powerRow->value ?? 0),
            'soc' => (float) ($socRow->value ?? 0),
            'sampled_at' => $sampledAt,
            'timestamp' => $sampledAt,
        ];
    }
}
