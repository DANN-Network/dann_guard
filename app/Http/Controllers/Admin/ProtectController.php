<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;

class ProtectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->id !== 1) {
            abort(403, 'Only the root administrator can access the Protect Dashboard.');
        }

        define('LARAVEL_PROTECT_MODE', true);

        ob_start();
        $result = require '/var/www/challenge/protect.php';
        $output = ob_get_clean();

        if ($result && is_string($result)) {
            return response($result)->header('Content-Type', 'application/json');
        }

        return response($output);
    }
}
