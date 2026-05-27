<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\Auth\RoutePermissionSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $this->authorizeAccess($request);

        if (! $request->expectsJson()) {
            return redirect()->route('access-control.roles.index');
        }

        $data = $this->loadAccessData($request);

        return response()->json($data);
    }

    public function rolesPage(Request $request): View
    {
        $this->authorizeAccess($request);

        return view('access-control.roles', $this->loadAccessData($request));
    }

    public function permissionsPage(Request $request): View
    {
        $this->authorizeAccess($request);

        return view('access-control.permissions', $this->loadAccessData($request));
    }

    public function syncRoutePermissions(Request $request, RoutePermissionSyncService $service): JsonResponse|RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'route_names' => ['nullable', 'array'],
            'route_names.*' => ['string', 'max:255'],
            'route_names_text' => ['nullable', 'string'],
        ]);

        $routeNames = $validated['route_names'] ?? null;
        if ($routeNames === null && ! empty($validated['route_names_text'])) {
            $routeNames = $this->csvToList($validated['route_names_text']);
        }

        $result = $service->sync($routeNames);

        if (! $request->expectsJson()) {
            $msg = "Route permissions synced. Created: {$result['created']}, Existing: {$result['existing']}";
            if ($result['invalid'] !== []) {
                $msg .= '. Invalid: '.implode(', ', $result['invalid']);
            }

            return back()->with('status', $msg);
        }

        return response()->json($result);
    }

    public function storePermission(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! $request->expectsJson()) {
            return redirect()
                ->route('access-control.roles.index')
                ->with('status', "Permission `{$permission->name}` berhasil ditambahkan dan siap dipakai di Roles.");
        }

        return response()->json(['permission' => $permission], 201);
    }

    public function storeRole(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
            'permissions_text' => ['nullable', 'string'],
        ]);

        $teamId = $this->resolveTeamId($request, $validated['company_id'] ?? null);

        if ($validated['name'] === 'admin' && $teamId === 0) {
            return $this->unprocessableResponse($request, 'Global admin role already exists.');
        }

        $permissionNames = $validated['permissions'] ?? [];
        if ($permissionNames === [] && ! empty($validated['permissions_text'])) {
            $permissionNames = $this->csvToList($validated['permissions_text']);
        }

        $role = Role::firstOrCreate([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'company_id' => $teamId,
        ]);

        if ($permissionNames !== []) {
            $role->syncPermissions($permissionNames);
        }

        if (! $request->expectsJson()) {
            return back()->with('status', "Role `{$role->name}` berhasil disimpan.");
        }

        return response()->json(['role' => $role], 201);
    }

    public function syncRolePermissions(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeRoleScope($request, $role);

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
            'permissions_text' => ['nullable', 'string'],
        ]);

        $permissions = $validated['permissions'] ?? [];
        if ($permissions === [] && ! empty($validated['permissions_text'])) {
            $permissions = $this->csvToList($validated['permissions_text']);
        }

        $normalizedPermissions = $this->normalizePermissionNames($permissions);
        $role->syncPermissions($normalizedPermissions);

        if (! $request->expectsJson()) {
            return back()->with('status', "Permission role `{$role->name}` berhasil diupdate.");
        }

        return response()->json([
            'role' => $role->fresh(),
            'permissions' => $role->permissions()->pluck('name'),
        ]);
    }

    public function destroyRole(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeRoleScope($request, $role);

        if ($this->isProtectedGlobalAdminRole($role)) {
            return $this->unprocessableResponse($request, 'Global admin role cannot be deleted.');
        }

        $hasUsers = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->exists();

        if ($hasUsers) {
            return $this->unprocessableResponse(
                $request,
                "Role `{$role->name}` masih dipakai oleh user. Lepas assignment role dulu sebelum menghapus."
            );
        }

        $deletedRoleName = $role->name;
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! $request->expectsJson()) {
            return back()->with('status', "Role `{$deletedRoleName}` berhasil dihapus.");
        }

        return response()->json(['message' => "Role `{$deletedRoleName}` deleted."]);
    }

    private function unprocessableResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['access_control' => $message]);
    }

    public function assignUserRole(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeUserScope($request, $user);

        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

        $role = Role::query()->findOrFail($validated['role_id']);
        $this->authorizeRoleScope($request, $role);

        $userTeamId = (int) ($user->company_id ?? 0);
        $roleTeamId = (int) ($role->company_id ?? 0);
        if ($userTeamId !== $roleTeamId) {
            throw new HttpException(422, 'Role company scope must match user company scope.');
        }

        $this->runInTeamScope($userTeamId, function () use ($user, $role): void {
            $user->syncRoles([$role]);
        });

        if (! $request->expectsJson()) {
            return back()->with('status', "Role `{$role->name}` berhasil di-assign ke {$user->name}.");
        }

        return response()->json([
            'user' => $user->fresh(),
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * @return array{roles:Collection<int,Role>,permissions:Collection<int,Permission>,compactPermissions:Collection<int,array{name:string,label:string}>,users:Collection<int,User>,companies:Collection<int,Company>,isGlobalAdmin:bool}
     */
    private function loadAccessData(Request $request): array
    {
        $actor = $request->user();
        $actorCompanyId = $actor->company_id;
        $isGlobalAdmin = $this->isGlobalAdmin($actor);

        $rolesQuery = Role::query()
            ->with('permissions:id,name')
            ->orderBy('company_id')
            ->orderBy('name');
        if (! $isGlobalAdmin) {
            $rolesQuery->where('company_id', $actorCompanyId);
        }

        $usersQuery = User::query()
            ->select('id', 'company_id', 'name', 'email')
            ->orderBy('company_id')
            ->orderBy('name');

        if (! $isGlobalAdmin) {
            $usersQuery->where('company_id', $actorCompanyId);
        }

        $companiesQuery = Company::query()->select('id', 'name', 'code')->orderBy('name');
        if (! $isGlobalAdmin) {
            $companiesQuery->where('id', $actorCompanyId);
        }

        $permissions = Permission::query()
            ->select('id', 'name', 'guard_name')
            ->orderBy('name')
            ->get()
            ->filter(fn (Permission $permission): bool => $this->isAssignablePermissionName((string) $permission->name))
            ->values();

        return [
            'roles' => $rolesQuery->get(),
            'permissions' => $permissions,
            'compactPermissions' => $this->compactPermissionList($permissions),
            'users' => $usersQuery->get(),
            'companies' => $companiesQuery->get(),
            'isGlobalAdmin' => $isGlobalAdmin,
        ];
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        if ($user->hasRole('admin') || $user->can('manage_roles') || $user->can('manage_permissions')) {
            return;
        }

        throw new HttpException(403, 'You are not allowed to manage role and permission data.');
    }

    private function authorizeRoleScope(Request $request, Role $role): void
    {
        if ($this->isGlobalAdmin($request->user())) {
            return;
        }

        $actorCompanyId = (int) ($request->user()->company_id ?? 0);
        $roleCompanyId = (int) ($role->company_id ?? 0);
        if ($actorCompanyId !== $roleCompanyId) {
            throw new HttpException(403, 'You can only access roles in your company.');
        }
    }

    private function authorizeUserScope(Request $request, User $user): void
    {
        if ($this->isGlobalAdmin($request->user())) {
            return;
        }

        $actorCompanyId = (int) ($request->user()->company_id ?? 0);
        $targetCompanyId = (int) ($user->company_id ?? 0);
        if ($actorCompanyId !== $targetCompanyId) {
            throw new HttpException(403, 'You can only manage users in your company.');
        }
    }

    private function resolveTeamId(Request $request, ?int $companyId): int
    {
        if ($this->isGlobalAdmin($request->user())) {
            return (int) ($companyId ?? 0);
        }

        return (int) ($request->user()->company_id ?? 0);
    }

    private function isProtectedGlobalAdminRole(Role $role): bool
    {
        return $role->name === 'admin'
            && ($role->company_id === null || (int) $role->company_id === 0);
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

    /**
     * @return array<int, string>
     */
    private function csvToList(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values()
            ->all();
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

    /**
     * @param Collection<int, Permission> $permissions
     * @return Collection<int, array{name:string,label:string}>
     */
    private function compactPermissionList(Collection $permissions): Collection
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            $name = (string) $permission->name;
            $key = $this->permissionGroupKey($name);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $key,
                    'label' => $key,
                ];
            }
        }

        ksort($grouped);

        return collect(array_values($grouped));
    }

    private function permissionGroupKey(string $permissionName): string
    {
        $segments = explode('.', $permissionName);
        $actionSuffixes = ['create', 'store', 'edit', 'update', 'destroy', 'show', 'sync-routes', 'sync-permissions', 'assign-role'];

        $last = end($segments);
        if (in_array($last, $actionSuffixes, true) && count($segments) > 1) {
            array_pop($segments);
            return implode('.', $segments);
        }

        if ($last === 'index' && count($segments) > 1) {
            array_pop($segments);
            return implode('.', $segments);
        }

        return $permissionName;
    }

    /**
     * @param array<int, string> $selectedPermissions
     * @return array<int, string>
     */
    private function normalizePermissionNames(array $selectedPermissions): array
    {
        $allPermissions = array_values(array_filter(
            Permission::query()->pluck('name')->all(),
            fn (string $name): bool => $this->isAssignablePermissionName($name)
        ));
        $allLookup = array_fill_keys($allPermissions, true);
        $resolved = [];

        foreach ($selectedPermissions as $name) {
            if (isset($allLookup[$name])) {
                $resolved[] = $name;
                continue;
            }

            $indexName = $name.'.index';
            if (isset($allLookup[$indexName])) {
                $resolved[] = $indexName;
                continue;
            }

            $matched = array_values(array_filter(
                $allPermissions,
                fn (string $permission) => str_starts_with($permission, $name.'.')
            ));

            if ($matched !== []) {
                $resolved = array_merge($resolved, $matched);
            }
        }

        return array_values(array_unique($resolved));
    }

    private function isAssignablePermissionName(string $permissionName): bool
    {
        if (! str_starts_with($permissionName, 'access-control.')) {
            return true;
        }

        return in_array($permissionName, [
            'access-control.index',
            'access-control.roles.index',
            'access-control.permissions.index',
        ], true);
    }
}
