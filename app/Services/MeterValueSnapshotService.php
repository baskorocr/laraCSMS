<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MeterValueSnapshotService
{
    /**
     * Latest meter readings aligned to the newest energy sample (avoids stale SoC/Power).
     *
     * @return array{latest_energy: ?string, latest_power: ?string, latest_soc: ?string, last_sampled_at: ?string}
     */
    public function latestAlignedForChargePoint(int $chargePointPk, ?int $connectorNo = null): array
    {
        $energyQuery = DB::table('meter_values')
            ->select('id', 'transaction_id', 'sampled_at', 'value')
            ->where('charge_point_id', $chargePointPk)
            ->where('measurand', 'Energy.Active.Import.Register');

        if ($connectorNo !== null) {
            $connectorPk = $this->connectorPk($chargePointPk, $connectorNo);
            if ($connectorPk === null) {
                return $this->emptySnapshot();
            }
            $energyQuery->where('connector_id', $connectorPk);
        }

        $energy = $energyQuery->orderByDesc('id')->first();

        if ($energy === null) {
            return $this->emptySnapshot();
        }

        $anchor = Carbon::parse((string) $energy->sampled_at);
        $windowStart = $anchor->copy()->subSeconds(120);

        $latestMeasurand = function (string $measurand) use ($chargePointPk, $energy, $windowStart, $connectorNo): ?object {
            $query = DB::table('meter_values')
                ->select('value', 'sampled_at')
                ->where('charge_point_id', $chargePointPk)
                ->where('measurand', $measurand)
                ->where('sampled_at', '>=', $windowStart->toDateTimeString())
                ->where('sampled_at', '<=', $energy->sampled_at);

            if ($connectorNo !== null) {
                $connectorPk = $this->connectorPk($chargePointPk, $connectorNo);
                if ($connectorPk === null) {
                    return null;
                }
                $query->where('connector_id', $connectorPk);
            }

            if ($energy->transaction_id) {
                $query->where(function ($builder) use ($energy): void {
                    $builder
                        ->where('transaction_id', $energy->transaction_id)
                        ->orWhereNull('transaction_id');
                });
            }

            return $query->orderByDesc('id')->first();
        };

        $power = $latestMeasurand('Power.Active.Import');
        $soc = $latestMeasurand('SoC');

        return [
            'latest_energy' => $energy->value !== null ? (string) $energy->value : null,
            'latest_power' => $power?->value !== null ? (string) $power->value : null,
            'latest_soc' => $soc?->value !== null ? (string) $soc->value : null,
            'last_sampled_at' => (string) $energy->sampled_at,
        ];
    }

    /**
     * @return array{latest_energy: null, latest_power: null, latest_soc: null, last_sampled_at: null}
     */
    private function emptySnapshot(): array
    {
        return [
            'latest_energy' => null,
            'latest_power' => null,
            'latest_soc' => null,
            'last_sampled_at' => null,
        ];
    }

    private function connectorPk(int $chargePointPk, int $connectorNo): ?int
    {
        $id = DB::table('connectors')
            ->where('charge_point_id', $chargePointPk)
            ->where('connector_id', $connectorNo)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
