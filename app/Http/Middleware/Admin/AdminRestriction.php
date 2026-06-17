<?php

namespace Pterodactyl\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;

class AdminRestriction
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || $user->id === 1) {
            return $next($request);
        }

        $route = $request->route();
        $name = $route ? $route->getName() : '';
        $method = $request->method();
        $uri = $request->path();

        // Allow all users/* and servers/* routes (list, create, view, edit)
        if (str_starts_with($uri, 'admin/users') || str_starts_with($uri, 'admin/servers')) {
            return $next($request);
        }

        // Allow API key management via POST/DELETE
        if ($method === 'POST' && $uri === 'admin/api/new') {
            return $next($request);
        }

        if ($method === 'DELETE' && str_starts_with($uri, 'admin/api/revoke')) {
            return $next($request);
        }

        // Block all other POST/PUT/PATCH/DELETE
        if ($method !== 'GET') {
            abort(403, 'DANN-GUARD: Action not permitted.');
        }

        return $next($request);
    }
}
