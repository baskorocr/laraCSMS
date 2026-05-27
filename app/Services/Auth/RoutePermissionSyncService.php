<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoutePermissionSyncService
{
    /**
     * @var array<int, string>
     */
    private array $manageablePrefixes = [
        'dashboard',
        'master.',
        'ocpp.',
        'access-control.',
        'profile.',
    ];

    /**
     * For access-control module, expose only read/index route permissions.
     *
     * @var array<int, string>
     */
    private array $allowedAccessControlRoutes = [
        'access-control.index',
        'access-control.roles.index',
        'access-control.permissions.index',
    ];

    /**
     * @param array<int, string>|null $routeNames
     * @return array{created:int,existing:int,invalid:array<int,string>}
     */
    public function sync(?array $routeNames = null): array
    {
        $allNamedRoutes = $this->manageableRouteNames();
        $targetRoutes = $routeNames === null
            ? $allNamedRoutes
            : array_values(array_unique(array_filter($routeNames, fn (string $name) => $this->isManageableRouteName($name))));

        $invalid = [];
        $created = 0;
        $existing = 0;

        foreach ($targetRoutes as $routeName) {
            if (! in_array($routeName, $allNamedRoutes, true)) {
                $invalid[] = $routeName;
                continue;
            }

            $permission = Permission::firstOrCreate([
                'name' => $routeName,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'created' => $created,
            'existing' => $existing,
            'invalid' => $invalid,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function manageableRouteNames(): array
    {
        return collect(array_keys(Route::getRoutes()->getRoutesByName()))
            ->filter(fn (string $name) => $this->isManageableRouteName($name))
            ->sort()
            ->values()
            ->all();
    }

    private function isManageableRouteName(string $routeName): bool
    {
        if (str_starts_with($routeName, 'access-control.')) {
            return in_array($routeName, $this->allowedAccessControlRoutes, true);
        }

        foreach ($this->manageablePrefixes as $prefix) {
            if ($prefix === $routeName) {
                return true;
            }

            if (str_ends_with($prefix, '.') && str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
