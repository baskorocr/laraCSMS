<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Ocpp\OcppCommandService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MasterDataController extends Controller
{
    public function companies(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $query = DB::table('companies')
            ->select('id', 'code', 'name', 'timezone', 'is_active', 'api_token', 'created_at')
            ->orderBy('name');

        if (! $request->user()->hasRole('admin')) {
            $query->where('id', $request->user()->company_id);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('timezone', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('is_active', $status === 'active');
        }

        return view('master.entities', [
            'entity' => 'companies',
            'title' => 'Companies',
            'subtitle' => 'Master data tenant perusahaan.',
            'rows' => $query->get(),
            'filters' => [
                'search' => $search,
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : '',
            ],
            'canManage' => $request->user()->hasRole('admin'),
            'companyOptions' => [],
            'timezoneOptions' => [],
            'isGlobalAdmin' => false,
        ]);
    }

    public function users(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $companyId = $request->query('company_id');
        $isGlobalAdmin = $this->isGlobalAdmin($request->user());

        $query = DB::table('users')
            ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
            ->select('users.id', 'users.company_id', 'users.name', 'users.email', 'companies.name as company_name', 'users.created_at')
            ->selectSub(
                DB::table('model_has_roles as mhr')
                    ->join('roles', 'roles.id', '=', 'mhr.role_id')
                    ->selectRaw("GROUP_CONCAT(DISTINCT roles.name ORDER BY roles.name SEPARATOR ', ')")
                    ->where('mhr.model_type', User::class)
                    ->whereColumn('mhr.model_id', 'users.id'),
                'role_names'
            )
            ->selectSub(
                DB::table('model_has_roles as mhr')
                    ->select('mhr.role_id')
                    ->where('mhr.model_type', User::class)
                    ->whereColumn('mhr.model_id', 'users.id')
                    ->orderBy('mhr.role_id')
                    ->limit(1),
                'role_id'
            )
            ->orderBy('users.name');

        $this->scopeByCompany($request, $query, 'users.company_id');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('companies.name', 'like', "%{$search}%");
            });
        }

        if ($request->user()->hasRole('admin') && $companyId !== null && $companyId !== '') {
            $query->where('users.company_id', (int) $companyId);
        }

        $companyOptions = DB::table('companies')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        if (! $request->user()->hasRole('admin')) {
            $companyOptions = $companyOptions->where('id', $request->user()->company_id)->values();
        }

        $roleOptionsQuery = Role::query()
            ->select('id', 'name', 'company_id')
            ->orderBy('company_id')
            ->orderBy('name');

        if (! $isGlobalAdmin) {
            $roleOptionsQuery->where('company_id', (int) $request->user()->company_id);
        }

        return view('master.entities', [
            'entity' => 'users',
            'title' => 'Users',
            'subtitle' => 'Daftar user pada tenant yang bisa diakses.',
            'rows' => $query->get(),
            'filters' => [
                'search' => $search,
                'company_id' => $companyId !== null ? (string) $companyId : '',
            ],
            'canManage' => $request->user()->hasRole('admin'),
            'companyOptions' => $companyOptions,
            'roleOptions' => $roleOptionsQuery->get(),
            'timezoneOptions' => [],
            'isGlobalAdmin' => $isGlobalAdmin,
        ]);
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('companies', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        DB::table('companies')->insert([
            'code' => strtolower($data['code']),
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'is_active' => (bool) $request->boolean('is_active'),
            'api_token' => $this->generateCompanyToken(strtolower($data['code'])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Company berhasil ditambahkan.');
    }

    public function updateCompany(Request $request, int $id): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('companies', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        DB::table('companies')->where('id', $id)->update([
            'code' => strtolower($data['code']),
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'is_active' => (bool) $request->boolean('is_active'),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Company berhasil diperbarui.');
    }

    public function regenerateCompanyToken(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $company = DB::table('companies')->select('code')->where('id', $id)->first();
        abort_unless((bool) $company, 404);

        $newToken = $this->generateCompanyToken((string) $company->code);

        DB::table('companies')->where('id', $id)->update([
            'api_token' => $newToken,
            'updated_at' => now(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['token' => $newToken]);
        }

        return back()->with('status', 'API token berhasil di-regenerate.');
    }

    public function destroyCompany(Request $request, int $id): RedirectResponse
    {
        if ((int) $request->user()->company_id === $id) {
            return back()->withErrors(['company' => 'Company milik akun Anda tidak bisa dihapus.']);
        }

        DB::table('companies')->where('id', $id)->delete();

        return back()->with('status', 'Company berhasil dihapus.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $isGlobalAdmin = $this->isGlobalAdmin($request->user());
        $data = Validator::make($request->all(), [
            'company_id' => $isGlobalAdmin
                ? ['nullable', 'integer', 'exists:companies,id']
                : ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ])->validate();

        $role = Role::query()->findOrFail((int) $data['role_id']);
        $userCompanyId = $this->normalizeCompanyScope($data['company_id'] ?? null);
        $roleCompanyId = $this->normalizeCompanyScope($role->company_id ?? null);

        if ($userCompanyId !== $roleCompanyId) {
            return back()->withErrors(['role_id' => 'Role harus berasal dari company yang sama.'])->withInput();
        }

        $userId = DB::table('users')->insertGetId([
            'company_id' => $data['company_id'] ?? null,
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->findOrFail($userId);
        $this->runInTeamScope($userCompanyId, function () use ($user, $role): void {
            $user->syncRoles([$role]);
        });

        return back()->with('status', 'User berhasil ditambahkan.');
    }

    public function updateUser(Request $request, int $id): RedirectResponse
    {
        $isGlobalAdmin = $this->isGlobalAdmin($request->user());
        $data = Validator::make($request->all(), [
            'company_id' => $isGlobalAdmin
                ? ['nullable', 'integer', 'exists:companies,id']
                : ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ])->validate();

        $role = Role::query()->findOrFail((int) $data['role_id']);
        $userCompanyId = $this->normalizeCompanyScope($data['company_id'] ?? null);
        $roleCompanyId = $this->normalizeCompanyScope($role->company_id ?? null);

        if ($userCompanyId !== $roleCompanyId) {
            return back()->withErrors(['role_id' => 'Role harus berasal dari company yang sama.'])->withInput();
        }

        $payload = [
            'company_id' => $data['company_id'] ?? null,
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'updated_at' => now(),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        DB::table('users')->where('id', $id)->update($payload);

        $user = User::query()->findOrFail($id);
        $this->runInTeamScope($userCompanyId, function () use ($user, $role): void {
            $user->syncRoles([$role]);
        });

        return back()->with('status', 'User berhasil diperbarui.');
    }

    public function destroyUser(Request $request, int $id): RedirectResponse
    {
        if ((int) $request->user()->id === $id) {
            return back()->withErrors(['user' => 'Akun yang sedang login tidak bisa dihapus.']);
        }

        DB::table('users')->where('id', $id)->delete();

        return back()->with('status', 'User berhasil dihapus.');
    }

    public function timezoneOptions(): JsonResponse
    {
        return response()->json([
            'data' => timezone_identifiers_list(),
        ]);
    }

    public function chargePoints(Request $request): View|JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $companyId = trim((string) $request->query('company_id', ''));

        $query = DB::table('charge_points')
            ->leftJoin('companies', 'companies.id', '=', 'charge_points.company_id')
            ->leftJoin('connectors', 'connectors.charge_point_id', '=', 'charge_points.id')
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
                'charge_points.price_per_kwh',
                'charge_points.latitude',
                'charge_points.longitude',
                'charge_points.created_at',
                'charge_points.updated_at',
                DB::raw('COUNT(DISTINCT connectors.id) as connector_count'),
                DB::raw('GROUP_CONCAT(DISTINCT CONCAT(connectors.connector_id, ":", connectors.status) ORDER BY connectors.connector_id SEPARATOR "|") as connector_statuses')
            )
            ->groupBy(
                'charge_points.id',
                'charge_points.company_id',
                'charge_points.charge_point_id',
                'charge_points.name',
                'companies.name',
                'companies.code',
                'charge_points.ocpp_version',
                'charge_points.status',
                'charge_points.is_online',
                'charge_points.price_per_kwh',
                'charge_points.latitude',
                'charge_points.longitude',
                'charge_points.created_at',
                'charge_points.updated_at'
            )
            ->orderBy('charge_points.charge_point_id');

        $this->scopeByCompany($request, $query, 'charge_points.company_id');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('charge_points.charge_point_id', 'like', "%{$search}%")
                    ->orWhere('charge_points.name', 'like', "%{$search}%")
                    ->orWhere('companies.name', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $query->where('charge_points.status', $status);
        }

        if ($request->user()->hasRole('admin') && $companyId !== '') {
            $query->where('charge_points.company_id', (int) $companyId);
        }

        $rows = $query->get();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['data' => $rows]);
        }

        $companyOptions = DB::table('companies')->select('id', 'name')->orderBy('name')->get();
        if (! $request->user()->hasRole('admin')) {
            $companyOptions = $companyOptions->where('id', $request->user()->company_id)->values();
        }

        return view('master.entities', [
            'entity' => 'charge_points',
            'title' => 'Charge Points',
            'subtitle' => 'Master charge point — salin WS OCPP URL ke konfigurasi charger, lalu pantau payload realtime.',
            'rows' => $rows,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'company_id' => $companyId,
            ],
            'canManage' => $request->user()->hasRole('admin'),
            'companyOptions' => $companyOptions,
            'roleOptions' => [],
            'timezoneOptions' => [],
            'ocppVersionOptions' => ['1.6', '2.1'],
            'chargePointStatusOptions' => [
                'Available',
                'Preparing',
                'Charging',
                'SuspendedEV',
                'SuspendedEVSE',
                'Finishing',
                'Reserved',
                'Occupied',
                'Unavailable',
                'Faulted',
            ],
            'isGlobalAdmin' => false,
        ]);
    }

    public function storeChargePoint(Request $request): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'charge_point_id' => ['required', 'string', 'max:100', Rule::unique('charge_points', 'charge_point_id')],
            'name' => ['required', 'string', 'max:255'],
            'ocpp_version' => ['required', 'string', 'max:10'],
            'status' => ['required', 'string', 'max:50'],
            'is_online' => ['nullable', 'boolean'],
            'price_per_kwh' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ])->validate();

        DB::table('charge_points')->insert([
            'company_id' => (int) $data['company_id'],
            'charge_point_id' => $data['charge_point_id'],
            'name' => $data['name'],
            'ocpp_version' => $data['ocpp_version'],
            'status' => $data['status'],
            'is_online' => (bool) $request->boolean('is_online'),
            'price_per_kwh' => isset($data['price_per_kwh']) ? (float) $data['price_per_kwh'] : null,
            'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Charge point berhasil ditambahkan.');
    }

    public function updateChargePoint(Request $request, int $id): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'charge_point_id' => ['required', 'string', 'max:100', Rule::unique('charge_points', 'charge_point_id')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'ocpp_version' => ['required', 'string', 'max:10'],
            'status' => ['required', 'string', 'max:50'],
            'is_online' => ['nullable', 'boolean'],
            'price_per_kwh' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ])->validate();

        DB::table('charge_points')->where('id', $id)->update([
            'company_id' => (int) $data['company_id'],
            'charge_point_id' => $data['charge_point_id'],
            'name' => $data['name'],
            'ocpp_version' => $data['ocpp_version'],
            'status' => $data['status'],
            'is_online' => (bool) $request->boolean('is_online'),
            'price_per_kwh' => isset($data['price_per_kwh']) ? (float) $data['price_per_kwh'] : null,
            'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Charge point berhasil diperbarui.');
    }

    public function destroyChargePoint(int $id): RedirectResponse
    {
        DB::table('charge_points')->where('id', $id)->delete();

        return back()->with('status', 'Charge point berhasil dihapus.');
    }

    public function transactions(Request $request): View
    {
        $isAdmin = $request->user()->hasRole('admin');
        $filters = $this->transactionFiltersFromRequest($request);

        $rows = $this->transactionsQuery($request, $filters)
            ->limit(500)
            ->get();

        $companyOptions = DB::table('companies')->select('id', 'name')->orderBy('name')->get();
        if (! $isAdmin) {
            $companyOptions = $companyOptions->where('id', $request->user()->company_id)->values();
        }

        return view('master.transactions', [
            'title' => 'Transactions',
            'subtitle' => 'Riwayat transaksi charging — filter tanggal, export Excel, dan detail pengecasan.',
            'rows' => $rows,
            'filters' => $filters,
            'isAdmin' => $isAdmin,
            'companyOptions' => $companyOptions,
        ]);
    }

    public function transactionDetail(Request $request, int $id): JsonResponse
    {
        $transaction = $this->transactionsQuery($request, $this->transactionFiltersFromRequest($request))
            ->where('transactions.id', $id)
            ->first();

        if (! $transaction) {
            abort(404);
        }

        $meterValues = DB::table('meter_values')
            ->where('transaction_id', $id)
            ->orderByDesc('sampled_at')
            ->limit(100)
            ->get();

        if ($meterValues->isEmpty() && $transaction->started_at) {
            $meterQuery = DB::table('meter_values')
                ->where('charge_point_id', $transaction->charge_point_pk)
                ->where('sampled_at', '>=', $transaction->started_at);

            if ($transaction->stopped_at) {
                $meterQuery->where('sampled_at', '<=', $transaction->stopped_at);
            }

            $meterValues = $meterQuery->orderByDesc('sampled_at')->limit(100)->get();
        }

        $energyKwh = $this->transactionEnergyKwh($transaction);
        $duration = $this->transactionDurationLabel($transaction->started_at, $transaction->stopped_at);

        $latestEnergy = $meterValues->firstWhere('measurand', 'Energy.Active.Import.Register');
        $latestPower = $meterValues->firstWhere('measurand', 'Power.Active.Import');
        $latestSoc = $meterValues->firstWhere('measurand', 'SoC');

        return response()->json([
            'transaction' => [
                'id' => (int) $transaction->id,
                'transaction_code' => (string) $transaction->transaction_code,
                'charge_point_id' => (string) ($transaction->cp_code ?? '-'),
                'connector_id' => $transaction->connector_no !== null ? (int) $transaction->connector_no : null,
                'id_tag' => (string) ($transaction->id_tag ?? '-'),
                'status' => (string) $transaction->status,
                'meter_start' => $transaction->meter_start,
                'meter_stop' => $transaction->meter_stop,
                'started_at' => (string) $transaction->started_at,
                'stopped_at' => $transaction->stopped_at ? (string) $transaction->stopped_at : null,
                'stop_reason' => $transaction->stop_reason ? (string) $transaction->stop_reason : null,
                'company_name' => $transaction->company_name ?? null,
            ],
            'summary' => [
                'energy_kwh' => $energyKwh,
                'duration' => $duration,
                'latest_energy_wh' => $latestEnergy?->value,
                'latest_power_kw' => $latestPower?->value,
                'latest_soc_percent' => $latestSoc?->value,
            ],
            'meter_values' => $meterValues->map(fn ($row) => [
                'sampled_at' => (string) $row->sampled_at,
                'measurand' => (string) $row->measurand,
                'value' => (float) $row->value,
                'unit' => (string) $row->unit,
            ])->values(),
        ]);
    }

    public function transactionsExport(Request $request): StreamedResponse
    {
        $filters = $this->transactionFiltersFromRequest($request);
        $ids = array_filter(array_map('intval', (array) $request->query('ids', [])));

        $query = $this->transactionsQuery($request, $filters)
            ->selectSub(
                DB::table('meter_values')
                    ->select('value')
                    ->whereColumn('meter_values.transaction_id', 'transactions.id')
                    ->where('measurand', 'SoC')
                    ->orderByDesc('id')
                    ->limit(1),
                'latest_soc'
            )
            ->orderByDesc('transactions.id');

        if ($ids !== []) {
            $query->whereIn('transactions.id', $ids);
        }

        $rows = $query->limit(5000)->get();
        $filename = 'transactions-'.now()->format('Y-m-d_His').'.xls';
        $includeCompany = $request->user()->hasRole('admin');

        return response()->streamDownload(function () use ($rows, $includeCompany): void {
            echo "\xEF\xBB\xBF";
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
            echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>';
            echo '<table border="1" cellspacing="0" cellpadding="4">';

            $headers = [
                'ID', 'Code', 'Charge Point', 'Connector', 'Id Tag', 'Status',
                'Meter Start (Wh)', 'Meter Stop (Wh)', 'Energy (kWh)', 'Biaya (Rp)', 'SoC (%)',
                'Started At', 'Stopped At', 'Duration', 'Stop Reason',
            ];
            if ($includeCompany) {
                $headers[] = 'Company';
            }

            echo '<tr style="background:#f3f4f6;font-weight:bold;">';
            foreach ($headers as $header) {
                echo '<th>'.htmlspecialchars($header, ENT_QUOTES, 'UTF-8').'</th>';
            }
            echo '</tr>';

            foreach ($rows as $row) {
                $energyKwh = $this->transactionEnergyKwh($row);
                $biaya = ($energyKwh !== null && isset($row->price_per_kwh) && $row->price_per_kwh !== null)
                    ? $energyKwh * (float) $row->price_per_kwh
                    : null;
                $cells = [
                    (string) $row->id,
                    (string) $row->transaction_code,
                    (string) ($row->cp_code ?? '-'),
                    $row->connector_no !== null ? '#'.$row->connector_no : '-',
                    (string) ($row->id_tag ?? '-'),
                    (string) $row->status,
                    $this->formatExportDecimal($row->meter_start),
                    $this->formatExportDecimal($row->meter_stop),
                    $energyKwh !== null ? $this->formatExportDecimal($energyKwh, 3) : '-',
                    $biaya !== null ? number_format($biaya, 0, '.', '') : '-',
                    $this->formatExportDecimal($row->latest_soc ?? null),
                    $this->formatExportDateTime($row->started_at),
                    $this->formatExportDateTime($row->stopped_at),
                    $this->transactionDurationLabel($row->started_at, $row->stopped_at) ?? '-',
                    (string) ($row->stop_reason ?? '-'),
                ];

                if ($includeCompany) {
                    $cells[] = (string) ($row->company_name ?? '-');
                }

                echo '<tr>';
                foreach ($cells as $cell) {
                    echo '<td style="mso-number-format:\@;">'.htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8').'</td>';
                }
                echo '</tr>';
            }

            echo '</table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function meterValues(Request $request): View|JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $companyId = trim((string) $request->query('company_id', ''));
        $isAdmin = $request->user()->hasRole('admin');

        $rows = $this->sessionsChargePointRows($request, $search, $companyId);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['data' => $rows]);
        }

        $companyOptions = DB::table('companies')->select('id', 'name')->orderBy('name')->get();
        if (! $isAdmin) {
            $companyOptions = $companyOptions->where('id', $request->user()->company_id)->values();
        }

        return view('master.sessions', [
            'title' => 'Sessions / Meter Values',
            'subtitle' => 'Status connector via Pusher (realtime). Buka Monitor untuk meter values.',
            'rows' => $rows,
            'filters' => [
                'search' => $search,
                'company_id' => $companyId,
            ],
            'isAdmin' => $isAdmin,
            'companyOptions' => $companyOptions,
        ]);
    }

    public function sessionsLive(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $companyId = trim((string) $request->query('company_id', ''));
        $chargePointCode = trim((string) $request->query('charge_point_id', ''));

        $rows = $this->sessionsMeterRows(
            $request,
            $search,
            $companyId,
            $chargePointCode !== '' ? $chargePointCode : null
        );

        return response()->json([
            'data' => $rows,
            'synced_at' => now()->toDateTimeString(),
        ]);
    }

    public function stopSession(Request $request, OcppCommandService $commandService): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'charge_point_id' => ['required', 'integer'],
            'connector_id' => ['nullable', 'integer'],
        ])->validate();

        $chargePoint = DB::table('charge_points')
            ->select('id', 'company_id', 'charge_point_id')
            ->where('id', (int) $data['charge_point_id'])
            ->first();

        if (! $chargePoint) {
            return back()->withErrors(['sessions' => 'Charge point tidak ditemukan.']);
        }

        if (! $request->user()->hasRole('admin') && (int) $chargePoint->company_id !== (int) $request->user()->company_id) {
            abort(403);
        }

        $transactionQuery = DB::table('transactions')
            ->select('id')
            ->where('charge_point_id', (int) $chargePoint->id)
            ->where('status', 'ongoing')
            ->orderByDesc('id');

        if (! empty($data['connector_id'])) {
            $connector = DB::table('connectors')
                ->select('id')
                ->where('charge_point_id', (int) $chargePoint->id)
                ->where('connector_id', (int) $data['connector_id'])
                ->first();

            if ($connector) {
                $transactionQuery->where('connector_id', (int) $connector->id);
            }
        }

        $transaction = $transactionQuery->first();
        if (! $transaction) {
            return back()->withErrors(['sessions' => 'Tidak ada transaksi aktif untuk dihentikan.']);
        }

        $commandService->enqueueByChargePointCode(
            chargePointCode: (string) $chargePoint->charge_point_id,
            action: 'RemoteStopTransaction',
            payload: [
                'transactionId' => (int) $transaction->id,
            ]
        );

        return back()->with('status', 'Perintah stop transaction berhasil di-queue.');
    }

    public function startChargeTest(Request $request, OcppCommandService $commandService): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'charge_point_id' => ['required', 'integer'],
            'connector_id' => ['required', 'integer', 'min:1'],
            'id_tag' => ['nullable', 'string', 'max:36'],
        ])->validate();

        $chargePoint = DB::table('charge_points')
            ->select('id', 'company_id', 'charge_point_id', 'ocpp_version', 'is_online')
            ->where('id', (int) $data['charge_point_id'])
            ->first();

        if (! $chargePoint) {
            return back()->withErrors(['charge_points' => 'Charge point tidak ditemukan.']);
        }

        if (! $request->user()->hasRole('admin') && (int) $chargePoint->company_id !== (int) $request->user()->company_id) {
            abort(403);
        }

        $connector = DB::table('connectors')
            ->select('id', 'status')
            ->where('charge_point_id', (int) $chargePoint->id)
            ->where('connector_id', (int) $data['connector_id'])
            ->first();

        if (! $connector) {
            return back()->withErrors(['charge_points' => 'Connector tidak ditemukan pada charge point ini.']);
        }

        if (in_array((string) $connector->status, ['Charging', 'Occupied'], true)) {
            return back()->withErrors(['charge_points' => "Connector #{$data['connector_id']} sedang charging. Stop dulu sebelum test ulang."]);
        }

        if (! $chargePoint->is_online) {
            return back()->withErrors(['charge_points' => 'Charge point offline — tidak bisa start transaction.']);
        }

        $idTag = trim((string) ($data['id_tag'] ?? ''));
        if ($idTag === '') {
            $idTag = 'TEST_TAG_001';
        }

        $start = $commandService->buildRemoteStartPayload(
            (string) ($chargePoint->ocpp_version ?: '1.6'),
            (int) $data['connector_id'],
            $idTag
        );

        $commandService->enqueueByChargePointCode(
            chargePointCode: (string) $chargePoint->charge_point_id,
            action: $start['action'],
            payload: $start['payload']
        );

        $onlineHint = $chargePoint->is_online
            ? 'Perintah dikirim ke charge point.'
            : 'Charge point offline — perintah menunggu koneksi WebSocket.';

        return back()->with(
            'status',
            "Start test connector #{$data['connector_id']} ({$start['action']}, idTag: {$idTag}) berhasil di-queue. {$onlineHint}"
        );
    }

    public function chargePointsOcppLive(Request $request): JsonResponse
    {
        $chargePointCode = trim((string) $request->query('charge_point_id', ''));
        if ($chargePointCode === '') {
            return response()->json(['data' => []]);
        }

        $chargePoint = DB::table('charge_points')
            ->select('id', 'company_id', 'charge_point_id')
            ->where('charge_point_id', $chargePointCode)
            ->first();

        if (! $chargePoint) {
            return response()->json(['data' => []]);
        }

        if (! $request->user()->hasRole('admin') && (int) $chargePoint->company_id !== (int) $request->user()->company_id) {
            abort(403);
        }

        $rows = DB::table('ocpp_messages_log')
            ->select(
                'ocpp_messages_log.id',
                'ocpp_messages_log.charge_point_id',
                'ocpp_messages_log.direction',
                'ocpp_messages_log.message_type_id',
                'ocpp_messages_log.action',
                'ocpp_messages_log.message_uid',
                'ocpp_messages_log.payload',
                'ocpp_messages_log.received_at'
            )
            ->where('ocpp_messages_log.charge_point_id', (int) $chargePoint->id)
            ->orderByDesc('ocpp_messages_log.id')
            ->limit(1)
            ->get()
            ->map(function ($row) use ($chargePoint) {
                $payload = json_decode((string) $row->payload, true);

                return [
                    'id' => (int) $row->id,
                    'charge_point_id' => (int) $row->charge_point_id,
                    'charge_point_code' => (string) $chargePoint->charge_point_id,
                    'direction' => (string) $row->direction,
                    'message_type_id' => (int) $row->message_type_id,
                    'message_uid' => $row->message_uid ? (string) $row->message_uid : null,
                    'action' => $row->action ? (string) $row->action : null,
                    'payload' => is_array($payload) ? $payload : ['raw' => $row->payload],
                    'received_at' => (string) $row->received_at,
                ];
            })
            ->values();

        return response()->json([
            'data' => $rows,
            'synced_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function sessionsChargePointRows(
        Request $request,
        string $search,
        string $companyId
    ) {
        $isAdmin = $request->user()->hasRole('admin');

        $query = DB::table('charge_points')
            ->leftJoin('companies', 'companies.id', '=', 'charge_points.company_id')
            ->leftJoin('connectors', 'connectors.charge_point_id', '=', 'charge_points.id')
            ->select(
                'charge_points.id',
                'charge_points.charge_point_id',
                'charge_points.name',
                'charge_points.status',
                'charge_points.is_online',
                'companies.name as company_name',
                DB::raw('COUNT(DISTINCT connectors.id) as connector_count'),
                DB::raw('GROUP_CONCAT(DISTINCT CONCAT(connectors.connector_id, ":", connectors.status) ORDER BY connectors.connector_id SEPARATOR "|") as connector_statuses')
            )
            ->groupBy(
                'charge_points.id',
                'charge_points.charge_point_id',
                'charge_points.name',
                'charge_points.status',
                'charge_points.is_online',
                'companies.name'
            )
            ->orderBy('charge_points.charge_point_id');

        $this->scopeByCompany($request, $query, 'charge_points.company_id');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('charge_points.charge_point_id', 'like', "%{$search}%")
                    ->orWhere('charge_points.name', 'like', "%{$search}%")
                    ->orWhere('companies.name', 'like', "%{$search}%");
            });
        }

        if ($isAdmin && $companyId !== '') {
            $query->where('charge_points.company_id', (int) $companyId);
        }

        return $query->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function sessionsMeterRows(
        Request $request,
        string $search,
        string $companyId,
        ?string $chargePointCode
    ) {
        $isAdmin = $request->user()->hasRole('admin');

        $query = DB::table('charge_points')
            ->leftJoin('companies', 'companies.id', '=', 'charge_points.company_id')
            ->select(
                'charge_points.id',
                'charge_points.company_id',
                'charge_points.charge_point_id',
                'charge_points.name',
                'charge_points.status',
                'charge_points.is_online',
                'charge_points.last_heartbeat_at',
                'companies.name as company_name'
            )
            ->selectRaw('IF(charge_points.is_online, (SELECT MAX(mv.sampled_at) FROM meter_values mv WHERE mv.charge_point_id = charge_points.id), NULL) as last_sampled_at')
            ->selectRaw('(SELECT COUNT(*) FROM transactions t WHERE t.charge_point_id = charge_points.id AND t.status = ?) as active_transaction_count', ['ongoing'])
            ->selectRaw('IF(charge_points.is_online AND EXISTS(SELECT 1 FROM transactions t WHERE t.charge_point_id = charge_points.id AND t.status = ?), (SELECT mv.value FROM meter_values mv WHERE mv.charge_point_id = charge_points.id AND mv.measurand = ? ORDER BY mv.id DESC LIMIT 1), NULL) as latest_energy', ['ongoing', 'Energy.Active.Import.Register'])
            ->selectRaw('IF(charge_points.is_online AND EXISTS(SELECT 1 FROM transactions t WHERE t.charge_point_id = charge_points.id AND t.status = ?), (SELECT mv.value FROM meter_values mv WHERE mv.charge_point_id = charge_points.id AND mv.measurand = ? ORDER BY mv.id DESC LIMIT 1), NULL) as latest_power', ['ongoing', 'Power.Active.Import'])
            ->selectRaw('IF(charge_points.is_online AND EXISTS(SELECT 1 FROM transactions t WHERE t.charge_point_id = charge_points.id AND t.status = ?), (SELECT mv.value FROM meter_values mv WHERE mv.charge_point_id = charge_points.id AND mv.measurand = ? ORDER BY mv.id DESC LIMIT 1), NULL) as latest_soc', ['ongoing', 'SoC'])
            ->orderBy('charge_points.charge_point_id');

        $this->scopeByCompany($request, $query, 'charge_points.company_id');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('charge_points.charge_point_id', 'like', "%{$search}%")
                    ->orWhere('charge_points.name', 'like', "%{$search}%")
                    ->orWhere('companies.name', 'like', "%{$search}%");
            });
        }

        if ($isAdmin && $companyId !== '') {
            $query->where('charge_points.company_id', (int) $companyId);
        }

        if ($chargePointCode !== null) {
            $query->where('charge_points.charge_point_id', $chargePointCode);
        }

        return $query->get();
    }

    public function connectorTypes(): View
    {
        return $this->renderCatalog('connector_types');
    }

    public function stopReasons(): View
    {
        return $this->renderCatalog('stop_reasons');
    }

    public function ocppVersions(): View
    {
        return $this->renderCatalog('ocpp_versions');
    }

    public function connectorStatuses(): View
    {
        return $this->renderCatalog('connector_statuses');
    }

    public function transactionStatuses(): View
    {
        return $this->renderCatalog('transaction_statuses');
    }

    public function ocppActions(): View
    {
        return $this->renderCatalog('ocpp_actions');
    }

    public function meterMeasurands(): View
    {
        return $this->renderCatalog('meter_measurands');
    }

    public function reservationStatuses(): View
    {
        return $this->renderCatalog('reservation_statuses');
    }

    public function diagnosticsStatuses(): View
    {
        return $this->renderCatalog('diagnostics_statuses');
    }

    public function storeCatalog(Request $request, string $catalog): RedirectResponse
    {
        $config = $this->catalogConfig($catalog);
        $data = $this->validatedCatalogData($request, $config);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        if (isset($data['supported_versions'])) {
            $data['supported_versions'] = $this->normalizeJsonArray($data['supported_versions']);
        }

        DB::table($config['table'])->insert($data);

        return back()->with('status', 'Master data berhasil ditambahkan.');
    }

    public function updateCatalog(Request $request, string $catalog, int $id): RedirectResponse
    {
        $config = $this->catalogConfig($catalog);
        $data = $this->validatedCatalogData($request, $config, $id);
        $data['updated_at'] = now();

        if (isset($data['supported_versions'])) {
            $data['supported_versions'] = $this->normalizeJsonArray($data['supported_versions']);
        }

        DB::table($config['table'])->where('id', $id)->update($data);

        return back()->with('status', 'Master data berhasil diperbarui.');
    }

    public function destroyCatalog(string $catalog, int $id): RedirectResponse
    {
        $config = $this->catalogConfig($catalog);
        DB::table($config['table'])->where('id', $id)->delete();

        return back()->with('status', 'Master data berhasil dihapus.');
    }

    /**
     * @return array{search:string,date_from:string,date_to:string,status:string,company_id:string}
     */
    private function transactionFiltersFromRequest(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'status' => trim((string) $request->query('status', '')),
            'company_id' => trim((string) $request->query('company_id', '')),
        ];
    }

    /**
     * @param array{search:string,date_from:string,date_to:string,status:string,company_id:string} $filters
     */
    private function transactionsQuery(Request $request, array $filters): Builder
    {
        $query = DB::table('transactions')
            ->leftJoin('charge_points', 'charge_points.id', '=', 'transactions.charge_point_id')
            ->leftJoin('companies', 'companies.id', '=', 'transactions.company_id')
            ->leftJoin('connectors', 'connectors.id', '=', 'transactions.connector_id')
            ->select(
                'transactions.id',
                'transactions.company_id',
                'transactions.charge_point_id as charge_point_pk',
                'transactions.transaction_code',
                'transactions.id_tag',
                'transactions.meter_start',
                'transactions.meter_stop',
                'transactions.started_at',
                'transactions.stopped_at',
                'transactions.stop_reason',
                'transactions.status',
                'charge_points.charge_point_id as cp_code',
                'charge_points.price_per_kwh',
                'companies.name as company_name',
                'connectors.connector_id as connector_no'
            )
            ->orderByDesc('transactions.id');

        $this->scopeByCompany($request, $query, 'transactions.company_id');

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('transactions.transaction_code', 'like', "%{$search}%")
                    ->orWhere('transactions.id_tag', 'like', "%{$search}%")
                    ->orWhere('charge_points.charge_point_id', 'like', "%{$search}%");
            });
        }

        if ($filters['status'] !== '') {
            $query->where('transactions.status', $filters['status']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('transactions.started_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('transactions.started_at', '<=', $filters['date_to']);
        }

        if ($request->user()->hasRole('admin') && $filters['company_id'] !== '') {
            $query->where('transactions.company_id', (int) $filters['company_id']);
        }

        return $query;
    }

    private function transactionEnergyKwh(object $transaction): ?float
    {
        if ($transaction->meter_stop === null) {
            return null;
        }

        $delta = (float) $transaction->meter_stop - (float) $transaction->meter_start;

        return $delta > 0 ? round($delta / 1000, 3) : 0.0;
    }

    private function transactionDurationLabel(?string $startedAt, ?string $stoppedAt): ?string
    {
        if (! $startedAt || ! $stoppedAt) {
            return null;
        }

        $seconds = Carbon::parse($startedAt)->diffInSeconds(Carbon::parse($stoppedAt));
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    private function formatExportDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
    }

    private function formatExportDecimal(mixed $value, int $decimals = 3): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, $decimals, '.', '');
    }

    private function scopeByCompany(Request $request, Builder $query, string $column): void
    {
        if (! $request->user()->hasRole('admin')) {
            $query->where($column, $request->user()->company_id);
        }
    }

    /**
     * @param array<string, string>|null $realtimeConfig
     */
    private function renderList(string $title, string $subtitle, array $columns, array $rows, ?array $realtimeConfig = null): View
    {
        return view('master.list', compact('title', 'subtitle', 'columns', 'rows', 'realtimeConfig'));
    }

    private function renderCatalog(string $catalog): View
    {
        $config = $this->catalogConfig($catalog);
        $rows = DB::table($config['table'])->orderBy($config['order_by'])->get();

        return view('master.catalog', [
            'catalog' => $catalog,
            'config' => $config,
            'rows' => $rows,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogConfig(string $catalog): array
    {
        $configs = [
            'connector_types' => [
                'table' => 'master_connector_types',
                'title' => 'Master Connector Types',
                'subtitle' => 'Referensi jenis konektor charging.',
                'order_by' => 'code',
                'fields' => ['code', 'name', 'max_current_ampere', 'max_voltage', 'is_active'],
                'rules' => [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:150'],
                    'max_current_ampere' => ['nullable', 'integer', 'min:0'],
                    'max_voltage' => ['nullable', 'integer', 'min:0'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'stop_reasons' => [
                'table' => 'master_transaction_stop_reasons',
                'title' => 'Master Stop Reasons',
                'subtitle' => 'Referensi alasan berhenti transaksi OCPP.',
                'order_by' => 'code',
                'fields' => ['code', 'name', 'is_active'],
                'rules' => [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:150'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'ocpp_versions' => [
                'table' => 'master_ocpp_versions',
                'title' => 'Master OCPP Versions',
                'subtitle' => 'Daftar versi protokol OCPP yang didukung.',
                'order_by' => 'code',
                'fields' => ['code', 'name', 'is_active'],
                'rules' => [
                    'code' => ['required', 'string', 'max:20'],
                    'name' => ['required', 'string', 'max:150'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'connector_statuses' => [
                'table' => 'master_connector_statuses',
                'title' => 'Master Connector Statuses',
                'subtitle' => 'Referensi status connector/station dari OCPP.',
                'order_by' => 'sort_order',
                'fields' => ['code', 'name', 'sort_order', 'is_active'],
                'rules' => [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:150'],
                    'sort_order' => ['nullable', 'integer', 'min:0'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'transaction_statuses' => [
                'table' => 'master_transaction_statuses',
                'title' => 'Master Transaction Statuses',
                'subtitle' => 'Referensi status transaksi charging.',
                'order_by' => 'sort_order',
                'fields' => ['code', 'name', 'sort_order', 'is_active'],
                'rules' => [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:150'],
                    'sort_order' => ['nullable', 'integer', 'min:0'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'ocpp_actions' => [
                'table' => 'master_ocpp_actions',
                'title' => 'Master OCPP Actions',
                'subtitle' => 'Action OCPP lintas versi 1.6 dan 2.1.',
                'order_by' => 'code',
                'fields' => ['code', 'name', 'profile', 'supported_versions', 'is_active'],
                'rules' => [
                    'code' => ['required', 'string', 'max:80'],
                    'name' => ['required', 'string', 'max:150'],
                    'profile' => ['nullable', 'string', 'max:100'],
                    'supported_versions' => ['nullable', 'string', 'max:100'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'meter_measurands' => [
                'table' => 'master_meter_measurands',
                'title' => 'Master Meter Measurands',
                'subtitle' => 'Referensi measurand untuk payload MeterValues.',
                'order_by' => 'code',
                'fields' => ['code', 'name', 'default_unit', 'is_active'],
                'rules' => [
                    'code' => ['required', 'string', 'max:120'],
                    'name' => ['required', 'string', 'max:150'],
                    'default_unit' => ['nullable', 'string', 'max:20'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'reservation_statuses' => [
                'table' => 'master_reservation_statuses',
                'title' => 'Master Reservation Statuses',
                'subtitle' => 'Referensi status reservasi connector.',
                'order_by' => 'sort_order',
                'fields' => ['code', 'name', 'sort_order', 'is_active'],
                'rules' => [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:150'],
                    'sort_order' => ['nullable', 'integer', 'min:0'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'diagnostics_statuses' => [
                'table' => 'master_diagnostics_statuses',
                'title' => 'Master Diagnostics Statuses',
                'subtitle' => 'Referensi status proses diagnostics/upload log.',
                'order_by' => 'sort_order',
                'fields' => ['code', 'name', 'sort_order', 'is_active'],
                'rules' => [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:150'],
                    'sort_order' => ['nullable', 'integer', 'min:0'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
        ];

        abort_unless(isset($configs[$catalog]), 404);

        return $configs[$catalog];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function validatedCatalogData(Request $request, array $config, ?int $id = null): array
    {
        $rules = $config['rules'];
        $rules['code'][] = Rule::unique($config['table'], 'code')->ignore($id);

        $validator = Validator::make($request->all(), $rules);
        $data = $validator->validate();
        $data['is_active'] = (bool) ($request->boolean('is_active'));

        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            $data['sort_order'] = 0;
        }

        return $data;
    }

    private function normalizeJsonArray(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return json_encode([]);
        }

        $items = collect(explode(',', $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();

        return json_encode($items);
    }

    private function runInTeamScope(int $teamId, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($teamId);
        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }

    private function isGlobalAdmin(User $user): bool
    {
        return DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->where('roles.name', 'admin')
            ->where('roles.company_id', 0)
            ->exists();
    }

    private function normalizeCompanyScope(mixed $value): int
    {
        if ($value === null || $value === '' || (int) $value === 0) {
            return 0;
        }

        return (int) $value;
    }

    private function generateCompanyToken(string $code): string
    {
        $header    = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload   = rtrim(strtr(base64_encode(json_encode([
            'sub'  => $code,
            'iat'  => now()->timestamp,
            'jti'  => \Illuminate\Support\Str::random(16),
        ])), '+/', '-_'), '=');
        $secret    = config('app.key');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), '+/', '-_'), '=');

        return "{$header}.{$payload}.{$signature}";
    }

}

