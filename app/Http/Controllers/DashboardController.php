<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isGlobalAdmin = $user->isGlobalAdmin();
        $companyId = (int) ($user->company_id ?? 0);

        $companyName = null;
        if (! $isGlobalAdmin && $companyId > 0) {
            $companyName = DB::table('companies')->where('id', $companyId)->value('name');
        }

        $chargePointsQuery = DB::table('charge_points');
        $activeSessionsQuery = DB::table('connectors')->where('status', 'Charging');
        $companiesQuery = DB::table('companies')->where('is_active', true);
        $connectorsAvailableQuery = DB::table('connectors')->whereIn('status', ['Available', 'Online']);
        $connectorsFaultQuery = DB::table('connectors')->whereIn('status', ['Faulted', 'Offline']);

        $energyQuery = DB::table('meter_values')
            ->join('charge_points', 'charge_points.id', '=', 'meter_values.charge_point_id')
            ->where('meter_values.measurand', 'Energy.Active.Import.Register')
            ->whereDate('meter_values.sampled_at', today());

        $totalEnergyQuery = DB::table('transactions')
            ->whereNotNull('meter_stop')
            ->selectRaw('COALESCE(SUM(GREATEST(meter_stop - meter_start, 0)), 0) as total_wh');

        $recentTransactionsQuery = DB::table('transactions')
            ->leftJoin('charge_points', 'charge_points.id', '=', 'transactions.charge_point_id')
            ->leftJoin('companies', 'companies.id', '=', 'transactions.company_id')
            ->leftJoin('connectors', 'connectors.id', '=', 'transactions.connector_id')
            ->select(
                'transactions.id',
                'transactions.transaction_code',
                'transactions.meter_start',
                'transactions.meter_stop',
                'transactions.started_at',
                'transactions.status',
                'charge_points.charge_point_id as cp_code',
                'companies.name as company_name',
                'connectors.connector_id as connector_no'
            )
            ->orderByDesc('transactions.started_at')
            ->limit(10);

        if (! $isGlobalAdmin) {
            $this->scopeToCompany($chargePointsQuery, 'charge_points.company_id', $companyId);
            $this->scopeToCompany($activeSessionsQuery, 'connectors.company_id', $companyId);
            $this->scopeToCompany($companiesQuery, 'companies.id', $companyId);
            $this->scopeToCompany($connectorsAvailableQuery, 'connectors.company_id', $companyId);
            $this->scopeToCompany($connectorsFaultQuery, 'connectors.company_id', $companyId);
            $this->scopeToCompany($energyQuery, 'charge_points.company_id', $companyId);
            $this->scopeToCompany($totalEnergyQuery, 'transactions.company_id', $companyId);
            $this->scopeToCompany($recentTransactionsQuery, 'transactions.company_id', $companyId);
        }

        $energyToday = $energyQuery->sum('meter_values.value') ?? 0;
        $totalEnergyDistributedKwh = round((float) $totalEnergyQuery->value('total_wh') / 1000, 2);

        $stats = [
            'chargePoints' => $chargePointsQuery->count(),
            'activeSessions' => $activeSessionsQuery->count(),
            'energyToday' => round($energyToday / 1000, 2),
            'companies' => $companiesQuery->count(),
            'connectorsAvailable' => $connectorsAvailableQuery->count(),
            'connectorsFault' => $connectorsFaultQuery->count(),
        ];

        $recentTransactions = $recentTransactionsQuery->get();

        return view('dashboard', compact(
            'stats',
            'recentTransactions',
            'totalEnergyDistributedKwh',
            'isGlobalAdmin',
            'companyName',
        ));
    }

    private function scopeToCompany(Builder $query, string $column, int $companyId): void
    {
        if ($companyId > 0) {
            $query->where($column, $companyId);
        } else {
            $query->whereRaw('1 = 0');
        }
    }
}
