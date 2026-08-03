<?php

namespace App\Console\Commands;

use App\Services\ChargePointRealtimePublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkStaleChargePointsOffline extends Command
{
    protected $signature = 'ocpp:mark-stale-offline';
    protected $description = 'Mark charge points offline if no heartbeat received in the last 2 minutes';

    public function handle(ChargePointRealtimePublisher $publisher): void
    {
        $stale = DB::table('charge_points')
            ->where('is_online', true)
            ->where(function ($q) {
                $q->whereNull('last_heartbeat_at')
                  ->orWhere('last_heartbeat_at', '<', now()->subMinutes(2));
            })
            ->get(['id', 'charge_point_id']);

        foreach ($stale as $cp) {
            DB::table('charge_points')
                ->where('id', $cp->id)
                ->update(['is_online' => false, 'status' => 'Unavailable', 'updated_at' => now()]);

            DB::table('connectors')
                ->where('charge_point_id', $cp->id)
                ->update(['status' => 'Unavailable', 'updated_at' => now()]);

            DB::table('transactions')
                ->where('charge_point_id', $cp->id)
                ->where('status', 'ongoing')
                ->update([
                    'status'      => 'completed',
                    'meter_stop'  => DB::raw('meter_start'),
                    'stopped_at'  => now(),
                    'stop_reason' => 'PowerLoss',
                    'updated_at'  => now(),
                ]);

            $publisher->publishById((int) $cp->id);
            $this->line("Marked offline: {$cp->charge_point_id}");
        }

        if ($stale->isEmpty()) {
            $this->line('No stale charge points.');
        }
    }
}
