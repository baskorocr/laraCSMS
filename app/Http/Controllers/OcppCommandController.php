<?php

namespace App\Http\Controllers;

use App\Services\Ocpp\OcppCommandService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OcppCommandController extends Controller
{
    public function index(Request $request): View
    {
        $query = DB::table('ocpp_command_requests')
            ->leftJoin('charge_points', 'charge_points.id', '=', 'ocpp_command_requests.charge_point_id')
            ->select(
                'ocpp_command_requests.id',
                'ocpp_command_requests.action',
                'ocpp_command_requests.status',
                'ocpp_command_requests.attempts',
                'ocpp_command_requests.message_uid',
                'ocpp_command_requests.error_code',
                'ocpp_command_requests.error_description',
                'ocpp_command_requests.sent_at',
                'ocpp_command_requests.responded_at',
                'ocpp_command_requests.created_at',
                'charge_points.charge_point_id as cp_code'
            )
            ->orderByDesc('ocpp_command_requests.id');

        if (! $request->user()->hasRole('admin')) {
            $query->where('ocpp_command_requests.company_id', $request->user()->company_id);
        }

        $chargePoints = DB::table('charge_points')
            ->select('charge_point_id')
            ->when(! $request->user()->hasRole('admin'), fn ($q) => $q->where('company_id', $request->user()->company_id))
            ->orderBy('charge_point_id')
            ->pluck('charge_point_id');

        return view('ocpp.commands', [
            'rows' => $query->limit(300)->get(),
            'chargePoints' => $chargePoints,
            'presets' => $this->presets(),
        ]);
    }

    public function store(Request $request, OcppCommandService $commandService): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'charge_point_id' => ['required', 'string', 'max:120'],
            'action' => ['required', 'string', 'max:120'],
            'payload' => ['nullable', 'string'],
        ])->validate();

        $payload = [];
        if (! empty($data['payload'])) {
            $decoded = json_decode($data['payload'], true);
            if (! is_array($decoded)) {
                return back()->withErrors(['payload' => 'Payload harus JSON object valid.'])->withInput();
            }
            $payload = $decoded;
        }

        $commandService->enqueueByChargePointCode(
            chargePointCode: $data['charge_point_id'],
            action: $data['action'],
            payload: $payload
        );

        return back()->with('status', 'Command berhasil di-queue.');
    }

    /**
     * @return array<int, array{action:string,payload:string}>
     */
    private function presets(): array
    {
        return [
            ['action' => 'RemoteStartTransaction', 'payload' => '{"idTag":"TEST_TAG_001","connectorId":1}'],
            ['action' => 'RemoteStopTransaction', 'payload' => '{"transactionId":1}'],
            ['action' => 'Reset', 'payload' => '{"type":"Soft"}'],
            ['action' => 'UnlockConnector', 'payload' => '{"connectorId":1}'],
            ['action' => 'ReserveNow', 'payload' => '{"connectorId":1,"idTag":"RESERVE_001","expiryDate":"2026-12-31T23:59:59Z","reservationId":1001}'],
            ['action' => 'GetDiagnostics', 'payload' => '{"location":"https://example.com/upload","retries":1,"retryInterval":60}'],
        ];
    }
}

