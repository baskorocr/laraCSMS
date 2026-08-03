<?php

namespace App\Http\Controllers;

use App\Services\Ocpp\DiagnosticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChargePointDiagnosticsController extends Controller
{
    public function index(Request $request, int $id, DiagnosticsService $diagnosticsService): JsonResponse
    {
        $chargePoint = $this->resolveChargePoint($request, $id);

        return response()->json([
            'data' => $diagnosticsService->listForChargePoint((int) $chargePoint->id)->map(fn ($row) => [
                'id' => (int) $row->id,
                'location' => DiagnosticsService::maskLocation((string) $row->location),
                'retries' => (int) $row->retries,
                'retry_interval' => (int) $row->retry_interval,
                'status' => (string) $row->status,
                'file_name' => $row->file_name ? (string) $row->file_name : null,
                'start_time' => $row->start_time ? (string) $row->start_time : null,
                'stop_time' => $row->stop_time ? (string) $row->stop_time : null,
                'created_at' => (string) $row->created_at,
            ]),
        ]);
    }

    public function store(Request $request, int $id, DiagnosticsService $diagnosticsService): JsonResponse
    {
        $chargePoint = $this->resolveChargePoint($request, $id);

        $data = Validator::make($request->all(), [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'retries' => ['nullable', 'integer', 'min:1', 'max:10'],
            'retry_interval' => ['nullable', 'integer', 'min:1', 'max:3600'],
        ])->validate();

        try {
            $row = $diagnosticsService->requestDiagnostics((int) $chargePoint->id, [
                'retries' => $data['retries'] ?? 3,
                'retry_interval' => $data['retry_interval'] ?? 60,
                'start_time' => ! empty($data['date_from']) ? $data['date_from'].' 00:00:00' : null,
                'stop_time' => ! empty($data['date_to']) ? $data['date_to'].' 23:59:59' : null,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan GetDiagnostics berhasil di-queue.',
            'data' => [
                'id' => (int) $row->id,
                'status' => (string) $row->status,
                'location' => DiagnosticsService::maskLocation((string) $row->location),
            ],
        ]);
    }

    public function download(Request $request, int $requestId, DiagnosticsService $diagnosticsService): BinaryFileResponse|JsonResponse
    {
        $requestRow = DB::table('diagnostics_requests')
            ->where('id', $requestId)
            ->first();

        if (! $requestRow) {
            abort(404);
        }

        if (! $request->user()->hasRole('admin') && (int) $requestRow->company_id !== (int) $request->user()->company_id) {
            abort(403);
        }

        if ($requestRow->status !== 'Uploaded') {
            return response()->json(['message' => 'File belum tersedia atau upload gagal.'], 400);
        }

        $path = $diagnosticsService->resolveLocalFilePath((int) $requestRow->id);
        if (! $path) {
            $path = app(\App\Services\Ocpp\DiagnosticsFtpDownloader::class)->download($requestRow);
        }
        if (! $path) {
            return response()->json([
                'message' => 'File diagnostics tidak ditemukan di server maupun FTP.',
            ], 404);
        }

        return response()->download($path, (string) $requestRow->file_name);
    }

    private function resolveChargePoint(Request $request, int $id): object
    {
        $chargePoint = DB::table('charge_points')
            ->select('id', 'company_id', 'charge_point_id')
            ->where('id', $id)
            ->first();

        if (! $chargePoint) {
            abort(404);
        }

        if (! $request->user()->hasRole('admin') && (int) $chargePoint->company_id !== (int) $request->user()->company_id) {
            abort(403);
        }

        return $chargePoint;
    }
}
