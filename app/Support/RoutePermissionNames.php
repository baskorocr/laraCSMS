<?php

namespace App\Support;

class RoutePermissionNames
{
    /**
     * Route names that grant access when assigned (e.g. master.sessions → master.sessions.live).
     *
     * @return array<int, string>
     */
    public static function candidates(string $routeName): array
    {
        $candidates = [$routeName];
        $segments = explode('.', $routeName);

        $actionSuffixes = [
            'index', 'create', 'store', 'edit', 'update', 'destroy', 'show',
            'live', 'export', 'detail', 'stop', 'start', 'download', 'ocpp-live',
            'sync-routes', 'sync-permissions', 'assign-role',
        ];

        $last = end($segments);
        if (in_array($last, $actionSuffixes, true) && count($segments) > 1) {
            array_pop($segments);
            $prefix = implode('.', $segments);
            $candidates[] = $prefix;
            $candidates[] = $prefix.'.index';
        }

        if (count($segments) >= 3 && ! in_array($last, $actionSuffixes, true)) {
            $parent = implode('.', array_slice($segments, 0, -1));
            $candidates[] = $parent;
        }

        return array_values(array_unique($candidates));
    }
}
