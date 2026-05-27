<?php

namespace App\Services\Ocpp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OcppCommandService
{
    /**
     * @param array<string,mixed> $stationContext
     * @param array<string,mixed> $payload
     */
    public function enqueue(array $stationContext, string $action, array $payload): int
    {
        return (int) DB::table('ocpp_command_requests')->insertGetId([
            'company_id' => $stationContext['company_id'],
            'charge_point_id' => $stationContext['charge_point_pk'],
            'ocpp_version' => $stationContext['ocpp_version'],
            'action' => $action,
            'request_payload' => json_encode($payload),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function enqueueByChargePointCode(string $chargePointCode, string $action, array $payload): int
    {
        $station = DB::table('charge_points')
            ->select('id', 'company_id', 'charge_point_id', 'ocpp_version')
            ->where('charge_point_id', $chargePointCode)
            ->first();

        abort_unless($station !== null, 404, "Charge point {$chargePointCode} not found.");

        return $this->enqueue([
            'charge_point_pk' => (int) $station->id,
            'company_id' => (int) $station->company_id,
            'charge_point_code' => (string) $station->charge_point_id,
            'ocpp_version' => (string) ($station->ocpp_version ?: '1.6'),
        ], $action, $payload);
    }

    /**
     * @param array<string,mixed> $stationContext
     * @return array<int, array<int, mixed>>
     */
    public function reservePendingFrames(array $stationContext, int $limit = 5): array
    {
        $commands = DB::table('ocpp_command_requests')
            ->select('id', 'action', 'request_payload')
            ->where('charge_point_id', $stationContext['charge_point_pk'])
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $frames = [];

        foreach ($commands as $command) {
            $uid = (string) Str::uuid();
            $payload = json_decode((string) $command->request_payload, true);
            if (! is_array($payload)) {
                $payload = [];
            }

            DB::table('ocpp_command_requests')->where('id', $command->id)->update([
                'message_uid' => $uid,
                'status' => 'sent',
                'attempts' => DB::raw('attempts + 1'),
                'sent_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ocpp_messages_log')->insert([
                'company_id' => $stationContext['company_id'],
                'charge_point_id' => $stationContext['charge_point_pk'],
                'ocpp_version' => $stationContext['ocpp_version'],
                'direction' => 'outgoing',
                'message_type_id' => 2,
                'action' => $command->action,
                'message_uid' => $uid,
                'payload' => json_encode($payload),
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $frames[] = [2, $uid, $command->action, $payload];
        }

        return $frames;
    }

    /**
     * @param array<string,mixed> $stationContext
     * @param array<string,mixed> $payload
     */
    public function markAcknowledged(array $stationContext, string $messageUid, array $payload): void
    {
        DB::table('ocpp_command_requests')
            ->where('charge_point_id', $stationContext['charge_point_pk'])
            ->where('message_uid', $messageUid)
            ->where('status', 'sent')
            ->update([
                'status' => 'acknowledged',
                'response_payload' => json_encode($payload),
                'responded_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<string,mixed> $stationContext
     * @param array<string,mixed> $errorDetails
     */
    public function markError(
        array $stationContext,
        string $messageUid,
        string $errorCode,
        string $errorDescription,
        array $errorDetails = []
    ): void {
        DB::table('ocpp_command_requests')
            ->where('charge_point_id', $stationContext['charge_point_pk'])
            ->where('message_uid', $messageUid)
            ->where('status', 'sent')
            ->update([
                'status' => 'error',
                'error_code' => $errorCode,
                'error_description' => $errorDescription,
                'response_payload' => json_encode($errorDetails),
                'responded_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function reconcileTimeouts(int $timeoutSeconds = 30, int $maxAttempts = 3): array
    {
        $staleAt = now()->subSeconds($timeoutSeconds);
        $stale = DB::table('ocpp_command_requests')
            ->select('id', 'attempts')
            ->where('status', 'sent')
            ->where('sent_at', '<=', $staleAt)
            ->get();

        $retried = 0;
        $timedOut = 0;

        foreach ($stale as $command) {
            if ((int) $command->attempts < $maxAttempts) {
                DB::table('ocpp_command_requests')->where('id', $command->id)->update([
                    'status' => 'pending',
                    'message_uid' => null,
                    'updated_at' => now(),
                ]);
                $retried++;
            } else {
                DB::table('ocpp_command_requests')->where('id', $command->id)->update([
                    'status' => 'timeout',
                    'error_code' => 'Timeout',
                    'error_description' => "No CALLRESULT/CALLERROR in {$timeoutSeconds}s.",
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);
                $timedOut++;
            }
        }

        return [
            'retried' => $retried,
            'timed_out' => $timedOut,
        ];
    }
}

