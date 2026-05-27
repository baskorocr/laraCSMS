<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoutePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (! $routeName) {
            return $next($request);
        }

        $hasPermissionRule = Permission::query()
            ->whereIn('name', $this->permissionCandidates($routeName))
            ->where('guard_name', 'web')
            ->exists();

        if (! $hasPermissionRule || $user->canAccessRoute($routeName)) {
            return $next($request);
        }

        abort(403, "Permission `{$routeName}` is required.");
    }

    /**
     * @return array<int, string>
     */
    private function permissionCandidates(string $routeName): array
    {
        $segments = explode('.', $routeName);
        $candidates = [$routeName];
        $actionSuffixes = ['create', 'store', 'edit', 'update', 'destroy', 'show', 'sync-routes', 'sync-permissions', 'assign-role'];

        $last = end($segments);
        if (in_array($last, $actionSuffixes, true) && count($segments) > 1) {
            array_pop($segments);
            $prefix = implode('.', $segments);
            $candidates[] = $prefix;
            $candidates[] = $prefix.'.index';
        }

        return array_values(array_unique($candidates));
    }
}
