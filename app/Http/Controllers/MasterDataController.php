<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->select('id', 'code', 'name', 'timezone', 'is_active', 'created_at')
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

    public function chargePoints(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $companyId = trim((string) $request->query('company_id', ''));

        $query = DB::table('charge_points')
            ->leftJoin('companies', 'companies.id', '=', 'charge_points.company_id')
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

        $companyOptions = DB::table('companies')->select('id', 'name')->orderBy('name')->get();
        if (! $request->user()->hasRole('admin')) {
            $companyOptions = $companyOptions->where('id', $request->user()->company_id)->values();
        }

        return view('master.entities', [
            'entity' => 'charge_points',
            'title' => 'Charge Points',
            'subtitle' => 'Master charge point — klik Lihat Payload untuk stream OCPP WS (port 9001).',
            'rows' => $query->get(),
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
        ])->validate();

        DB::table('charge_points')->insert([
            'company_id' => (int) $data['company_id'],
            'charge_point_id' => $data['charge_point_id'],
            'name' => $data['name'],
            'ocpp_version' => $data['ocpp_version'],
            'status' => $data['status'],
            'is_online' => (bool) $request->boolean('is_online'),
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
        ])->validate();

        DB::table('charge_points')->where('id', $id)->update([
            'company_id' => (int) $data['company_id'],
            'charge_point_id' => $data['charge_point_id'],
            'name' => $data['name'],
            'ocpp_version' => $data['ocpp_version'],
            'status' => $data['status'],
            'is_online' => (bool) $request->boolean('is_online'),
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
        $query = DB::table('transactions')
            ->leftJoin('charge_points', 'charge_points.id', '=', 'transactions.charge_point_id')
            ->select(
                'transactions.id',
                'transactions.transaction_code',
                'charge_points.charge_point_id',
                'transactions.status',
                'transactions.started_at',
                'transactions.stopped_at'
            )
            ->orderByDesc('transactions.id');

        $this->scopeByCompany($request, $query, 'transactions.company_id');

        return $this->renderList(
            title: 'Transactions',
            subtitle: 'Data transaksi charging.',
            columns: ['ID', 'Code', 'Charge Point', 'Status', 'Started', 'Stopped'],
            rows: $query->limit(200)->get()->map(fn ($row) => [
                $row->id,
                $row->transaction_code,
                $row->charge_point_id ?? '-',
                $row->status,
                (string) $row->started_at,
                (string) ($row->stopped_at ?? '-'),
            ])->all(),
        );
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
            'subtitle' => 'Pilih charge point lalu buka Monitor untuk realtime meter values.',
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
            ->select(
                'charge_points.id',
                'charge_points.charge_point_id',
                'charge_points.name',
                'companies.name as company_name'
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
            ->selectSub(
                DB::table('meter_values')
                    ->selectRaw('MAX(sampled_at)')
                    ->whereColumn('meter_values.charge_point_id', 'charge_points.id'),
                'last_sampled_at'
            )
            ->selectSub(
                DB::table('meter_values')
                    ->select('value')
                    ->whereColumn('meter_values.charge_point_id', 'charge_points.id')
                    ->where('measurand', 'Energy.Active.Import.Register')
                    ->orderByDesc('id')
                    ->limit(1),
                'latest_energy'
            )
            ->selectSub(
                DB::table('meter_values')
                    ->select('value')
                    ->whereColumn('meter_values.charge_point_id', 'charge_points.id')
                    ->where('measurand', 'Power.Active.Import')
                    ->orderByDesc('id')
                    ->limit(1),
                'latest_power'
            )
            ->selectSub(
                DB::table('meter_values')
                    ->select('value')
                    ->whereColumn('meter_values.charge_point_id', 'charge_points.id')
                    ->where('measurand', 'SoC')
                    ->orderByDesc('id')
                    ->limit(1),
                'latest_soc'
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

}

