<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($request->user()->permissionTeamId());
        }

        return $next($request);
    }
}

