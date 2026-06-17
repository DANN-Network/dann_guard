<?php

namespace Pterodactyl\Http\Middleware\Api\Application;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthenticateApplicationUser
{
    /**
     * Authenticate that the currently authenticated user is an administrator
     * and should be allowed to proceed through the application API.
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        /** @var \Pterodactyl\Models\User|null $user */
        $user = $request->user();
        if (!$user || !$user->root_admin) {
            throw new AccessDeniedHttpException('Access denied');
        }

        // Restricted admins: can only create users and servers via API
        if ($user->id !== 1) {
            $method = $request->method();
            $uri = $request->path();

            // Only allow POST to /api/application/users and /api/application/servers
            if ($method === 'POST' && in_array($uri, ['api/application/users', 'api/application/servers'])) {
                return $next($request);
            }

            throw new AccessDeniedHttpException('Access denied');
        }

        return $next($request);
    }
}
