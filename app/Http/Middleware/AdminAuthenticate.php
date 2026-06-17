<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminAuthenticate
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $user = $request->user();
        if (!$user || !$user->root_admin) {
            throw new HttpException(403, 'This area requires admin access.');
        }

        // User ID 1 (main root) bypasses all restrictions
        if ((int) $user->id === 1) {
            return $next($request);
        }

        // All other admins are restricted by protect_permissions
        $perms = null;
        if ($user->protect_permissions) {
            $perms = json_decode($user->protect_permissions, true);
        }

        // If no permissions explicitly set, restrict everything
        $path = $request->path();
        $method = $request->method();

        // Block all server view/management access for restricted admins
        if (preg_match('#^admin/servers/view/\d+#', $path)) {
            return self::blocked('SERVER_ACCESS');
        }

        // Block password changes on user update
        if ($method === 'PATCH' && preg_match('#^admin/users/view/\d+#', $path)) {
            if ($request->has('password') && !empty($request->input('password'))) {
                return self::blocked('USER_PASSWORD');
            }
        }

        // Block user account deletion
        if ($method === 'DELETE' && preg_match('#^admin/users/view/\d+#', $path)) {
            return self::blocked('USER_DELETE');
        }

        return $next($request);
    }

    public static function blocked(string $reason = 'ACCESS_DENIED'): never
    {
        $messages = [
            'SERVER_ACCESS' => 'You are not authorized to view server details.',
            'USER_PASSWORD' => 'You are not authorized to change user passwords.',
            'USER_DELETE' => 'You are not authorized to delete users.',
            'ACCESS_DENIED' => 'Your account does not have permission to access this area.',
        ];
        $msg = $messages[$reason] ?? $messages['ACCESS_DENIED'];

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>BLOCKED - DANN GUARD</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
  min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:#0a0a1a;color:#e0e0f0;padding:20px;
  background-image:radial-gradient(ellipse at center,rgba(124,58,237,0.08) 0%,transparent 70%);
}
.card{
  background:#1a1a30;border:1px solid #2a2a50;border-radius:12px;
  padding:48px 40px;max-width:480px;width:100%;text-align:center;
  box-shadow:0 0 60px rgba(124,58,237,0.1);
}
.shield{font-size:56px;margin-bottom:16px}
h1{font-size:22px;font-weight:700;color:#a78bfa;margin-bottom:8px;letter-spacing:1px;text-transform:uppercase}
h1 span{color:#7c3aed}
.sep{width:40px;height:2px;background:#7c3aed;margin:16px auto;border-radius:1px}
p{font-size:14px;color:#9a9ab0;line-height:1.6;margin-bottom:20px}
.footer{font-size:11px;color:#4a4a6a;margin-top:24px;letter-spacing:0.5px}
.footer span{color:#7c3aed}
.btn{
  display:inline-block;padding:10px 24px;background:#7c3aed;color:#fff;
  border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;
  text-decoration:none;transition:background .2s;
}
.btn:hover{background:#6d28d9}
</style>
</head>
<body>
<div class="card">
  <div class="shield">🛡️</div>
  <h1>BLOCKED <span>PROTECTION</span></h1>
  <div class="sep"></div>
  <p>DANN-GUARD</p>
  <p style="font-size:13px;color:#7a7a9a">{$msg}</p>
  <a href="/admin" class="btn">Back to Panel</a>
  <div class="footer">DANN <span>NETWORK</span></div>
</div>
</body>
</html>
HTML;
        throw new HttpException(403, $msg, null, [], $html);
    }
}

