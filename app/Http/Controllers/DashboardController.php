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
        $connectorsAvailableQuery = DB::table('connectors')
            ->join('charge_points', 'charge_points.id', '=', 'connectors.charge_point_id')
            ->where('connectors.status', 'Available')
            ->where('charge_points.is_online', true);
        $connectorsFaultQuery = DB::table('connectors')
            ->join('charge_points', 'charge_points.id', '=', 'connectors.charge_point_id')
            ->where(function ($q) {
                $q->where('connectors.status', 'Faulted')
                  ->orWhere('charge_points.is_online', false);
            });

        $energyQuery = DB::table('meter_values')
            ->join('charge_points', 'charge_points.id', '=', 'meter_values.charge_point_id')
            ->where('meter_values.measurand', 'Energy.Active.Import.Register')
            ->whereDate('meter_values.sampled_at', today());

        $totalEnergyQuery = DB::table('transactions')
            ->whereNotNull('meter_stop')
            ->selectRaw('COALESCE(SUM(GREATEST(meter_stop - meter_start, 0)), 0) as total_wh');

        $totalRevenueQuery = DB::table('transactions')
            ->join('charge_points', 'charge_points.id', '=', 'transactions.charge_point_id')
            ->whereNotNull('transactions.meter_stop')
            ->whereNotNull('charge_points.price_per_kwh')
            ->selectRaw('COALESCE(SUM(GREATEST(transactions.meter_stop - transactions.meter_start, 0) / 1000 * charge_points.price_per_kwh), 0) as total_revenue');

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

        $chargePointMapQuery = DB::table('charge_points')
            ->leftJoin('companies', 'companies.id', '=', 'charge_points.company_id')
            ->select(
                'charge_points.id',
                'charge_points.charge_point_id',
                'charge_points.name',
                'charge_points.status',
                'charge_points.is_online',
                'charge_points.latitude',
                'charge_points.longitude',
                'companies.name as company_name'
            )
            ->whereNotNull('charge_points.latitude')
            ->whereNotNull('charge_points.longitude');

        if (! $isGlobalAdmin) {
            $this->scopeToCompany($chargePointsQuery, 'charge_points.company_id', $companyId);
            $this->scopeToCompany($activeSessionsQuery, 'connectors.company_id', $companyId);
            $this->scopeToCompany($companiesQuery, 'companies.id', $companyId);
            $this->scopeToCompany($connectorsAvailableQuery, 'connectors.company_id', $companyId);
            $this->scopeToCompany($connectorsFaultQuery, 'connectors.company_id', $companyId);
            $this->scopeToCompany($energyQuery, 'charge_points.company_id', $companyId);
            $this->scopeToCompany($totalEnergyQuery, 'transactions.company_id', $companyId);
            $this->scopeToCompany($totalRevenueQuery, 'transactions.company_id', $companyId);
            $this->scopeToCompany($recentTransactionsQuery, 'transactions.company_id', $companyId);
            $this->scopeToCompany($chargePointMapQuery, 'charge_points.company_id', $companyId);
        }

        $energyToday = $energyQuery->sum('meter_values.value') ?? 0;
        $totalEnergyDistributedKwh = round((float) $totalEnergyQuery->value('total_wh') / 1000, 2);
        $totalRevenue = (float) $totalRevenueQuery->value('total_revenue');

        $stats = [
            'chargePoints' => $chargePointsQuery->count(),
            'activeSessions' => $activeSessionsQuery->count(),
            'energyToday' => round($energyToday / 1000, 2),
            'companies' => $companiesQuery->count(),
            'connectorsAvailable' => $connectorsAvailableQuery->count(),
            'connectorsFault' => $connectorsFaultQuery->count(),
        ];

        $recentTransactions = $recentTransactionsQuery->get();
        $chargePointMarkers = $chargePointMapQuery->get()->map(fn ($cp) => [
            'lat'       => (float) $cp->latitude,
            'lng'       => (float) $cp->longitude,
            'name'      => $cp->name,
            'cp_id'     => $cp->charge_point_id,
            'company'   => $cp->company_name,
            'status'    => $cp->status,
            'is_online' => (bool) $cp->is_online,
        ])->values();

        return view('dashboard', compact(
            'stats',
            'recentTransactions',
            'totalEnergyDistributedKwh',
            'totalRevenue',
            'isGlobalAdmin',
            'companyName',
            'chargePointMarkers',
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
