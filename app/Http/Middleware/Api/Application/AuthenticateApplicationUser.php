<?php

namespace Pterodactyl\Http\Middleware\Api\Application;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthenticateApplicationUser
{
    public function handle(Request $request, \Closure $next): mixed
    {
        /** @var \Pterodactyl\Models\User|null $user */
        $user = $request->user();
        if (!$user || !$user->root_admin) {
            throw new AccessDeniedHttpException('Access denied');
        }

        if ($user->id !== 1) {
            $method = $request->method();
            $uri = $request->path();

            // Allow POST to create users and servers
            if ($method === 'POST' && in_array($uri, ['api/application/users', 'api/application/servers'])) {
                return $next($request);
            }

            // Allow GET/PATCH for egg management
            if (preg_match('#^api/application/nests/\d+/eggs#', $uri)) {
                return $next($request);
            }
            if (preg_match('#^api/application/nests/\d+$#', $uri) && $method === 'GET') {
                return $next($request);
            }
            if ($uri === 'api/application/nests' && $method === 'GET') {
                return $next($request);
            }

            throw new AccessDeniedHttpException('DANN-GUARD: Access denied');
        }

        return $next($request);
    }
}
