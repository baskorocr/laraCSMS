<?php

namespace App\Services\Ocpp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DiagnosticsService
{
    public function __construct(
        private readonly OcppCommandService $commandService
    ) {
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function listForChargePoint(int $chargePointPk): \Illuminate\Support\Collection
    {
        return DB::table('diagnostics_requests')
            ->where('charge_point_id', $chargePointPk)
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    /**
     * @param array{retries?:int,retry_interval?:int,start_time?:string|null,stop_time?:string|null} $options
     */
    public function requestDiagnostics(int $chargePointPk, array $options = []): object
    {
        $station = DB::table('charge_points')
            ->select('id', 'company_id', 'charge_point_id', 'ocpp_version', 'is_online')
            ->where('id', $chargePointPk)
            ->first();

        if (! $station) {
            throw new RuntimeException('Charge point tidak ditemukan.');
        }

        $chargePointCode = (string) $station->charge_point_id;
        $messageId = $chargePointCode.'_'.now()->getTimestampMs();
        $location = str_replace(
            ['{charge_point_code}', '{message_id}'],
            [$chargePointCode, $messageId],
            (string) config('ocpp.diagnostics.upload_location')
        );

        $retries = max(1, (int) ($options['retries'] ?? 3));
        $retryInterval = max(1, (int) ($options['retry_interval'] ?? 60));

        $payload = [
            'location' => $location,
            'retries' => $retries,
            'retryInterval' => $retryInterval,
        ];

        if (! empty($options['start_time'])) {
            $payload['startTime'] = $options['start_time'];
        }

        if (! empty($options['stop_time'])) {
            $payload['stopTime'] = $options['stop_time'];
        }

        $commandId = $this->commandService->enqueue([
            'charge_point_pk' => (int) $station->id,
            'company_id' => (int) $station->company_id,
            'charge_point_code' => $chargePointCode,
            'ocpp_version' => (string) ($station->ocpp_version ?: '1.6'),
        ], 'GetDiagnostics', $payload);

        $requestId = DB::table('diagnostics_requests')->insertGetId([
            'company_id' => (int) $station->company_id,
            'charge_point_id' => (int) $station->id,
            'charge_point_code' => $chargePointCode,
            'message_id' => $messageId,
            'location' => $location,
            'retries' => $retries,
            'retry_interval' => $retryInterval,
            'start_time' => $options['start_time'] ?? null,
            'stop_time' => $options['stop_time'] ?? null,
            'status' => 'Requested',
            'ocpp_command_request_id' => $commandId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $station->is_online) {
            DB::table('diagnostics_requests')->where('id', $requestId)->update([
                'status' => 'Failed',
                'updated_at' => now(),
            ]);

            throw new RuntimeException("Charge point {$chargePointCode} sedang offline.");
        }

        return DB::table('diagnostics_requests')->where('id', $requestId)->first();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function handleStatusNotification(string $chargePointCode, array $payload): void
    {
        $status = (string) ($payload['status'] ?? '');
        if ($status === '') {
            return;
        }

        $fileName = $payload['fileName'] ?? $payload['file_name'] ?? null;

        $query = DB::table('diagnostics_requests')
            ->where('charge_point_code', $chargePointCode)
            ->whereIn('status', ['Requested', 'Uploading'])
            ->orderByDesc('id');

        $update = [
            'status' => $status,
            'updated_at' => now(),
        ];

        if (is_string($fileName) && $fileName !== '') {
            $update['file_name'] = $fileName;
        }

        $query->limit(1)->update($update);
    }

    public function resolveLocalFilePath(int $requestId): ?string
    {
        $request = DB::table('diagnostics_requests')->where('id', $requestId)->first();
        if (! $request || $request->status !== 'Uploaded' || ! $request->file_name) {
            return null;
        }

        $path = storage_path('app/diagnostics/'.$request->id.'_'.$request->file_name);

        return is_file($path) ? $path : null;
    }

    public static function maskLocation(?string $location): string
    {
        if (! $location) {
            return '';
        }

        return (string) preg_replace('/(ftp|sftp):\/\/([^:]+):([^@]+)@/i', '$1://***:***@', $location);
    }
}
