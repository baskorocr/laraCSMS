<?php

namespace App\Http\Middleware;

use App\Support\RoutePermissionNames;
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

        if ($user->isGlobalAdmin()) {
            return $next($request);
        }

        $candidates = RoutePermissionNames::candidates($routeName);

        $hasPermissionRule = Permission::query()
            ->whereIn('name', $candidates)
            ->where('guard_name', 'web')
            ->exists();

        if (! $hasPermissionRule || $user->canAccessRoute($routeName)) {
            return $next($request);
        }

        abort(403, "Permission `{$routeName}` is required.");
    }
}
