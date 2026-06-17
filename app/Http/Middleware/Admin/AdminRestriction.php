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

        // Allow users and servers routes
        if (str_starts_with($uri, 'admin/users') || str_starts_with($uri, 'admin/servers')) {
            return $next($request);
        }

        // Allow GET to index and protect
        if ($method === 'GET') {
            $allowedGet = ['admin.index', 'admin.protect'];
            if (in_array($name, $allowedGet)) {
                return $next($request);
            }
        }

        // Block everything else with redirect
        if ($request->expectsJson()) {
            return response()->json(['error' => 'DANN-GUARD: Access denied'], 403);
        }

        return redirect()->route('admin.index')->with('error', 'ACCESS DENIED');
    }
}
