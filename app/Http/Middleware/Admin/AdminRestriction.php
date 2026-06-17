<?php

namespace Pterodactyl\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;

class AdminRestriction
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || $user->id === 1 || !$user->restricted_admin) {
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
            return $this->deny('Access denied');
        }

        $allowed = [
            'admin.index',
            'admin.protect',
            'admin.api.index',
            'admin.api.new',
            'admin.api.delete',
        ];

        if (!in_array($name, $allowed)) {
            return $this->deny('Access denied');
        }

        return $next($request);
    }

    private function deny(string $message)
    {
        if (request()->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }
        return redirect()->route('admin.index')->with('error', $message);
    }
}
