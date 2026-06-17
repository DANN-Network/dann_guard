<?php
session_start();

function run($cmd) { return trim(@shell_exec("$cmd 2>/dev/null") ?? ''); }
function isActive($svc) { return trim(@shell_exec("systemctl is-active $svc 2>/dev/null") ?? '') === 'active'; }

function loadConfig() {
    return @json_decode(@file_get_contents('/root/dann_guard/config.json'), true) ?? [];
}

function saveConfig($cfg) {
    return file_put_contents('/root/dann_guard/config.json', json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function dbConn() {
    $cfg = loadConfig();
    $db = $cfg['database'] ?? [];
    try {
        return new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8", $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        return null;
    }
}

$protect_pass = loadConfig()['protect']['password'] ?? 'protect123';
$error = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/protect');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $protect_pass) {
        $_SESSION['protect_logged_in'] = true;
        header('Location: /admin/protect');
        exit;
    } else {
        $error = 'Invalid password';
    }
}

$logged_in = isset($_SESSION['protect_logged_in']) && $_SESSION['protect_logged_in'] === true;

if (!$logged_in):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Protect - Login</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #1a1a2e;
  padding: 20px;
}
.login-card {
  background: #242440;
  border: 1px solid #32325a;
  border-radius: 8px;
  padding: 48px 40px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
  text-align: center;
}
.login-card .logo { font-size: 40px; margin-bottom: 16px; }
.login-card h1 { font-size: 20px; color: #e8e8f0; font-weight: 500; margin-bottom: 4px; }
.login-card .sub { font-size: 13px; color: #7a7a9a; margin-bottom: 32px; }
.form-group { margin-bottom: 16px; text-align: left; }
.form-group label {
  display: block; font-size: 12px; color: #9a9ac0;
  text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;
}
.form-group input {
  width: 100%; padding: 10px 14px;
  background: #1a1a30;
  border: 1px solid #32325a;
  border-radius: 6px; color: #e0e0f0; font-size: 14px; outline: none; transition: border .2s;
}
.form-group input:focus { border-color: #7c3aed; }
.login-btn {
  width: 100%; padding: 12px;
  background: #7c3aed; border: none; border-radius: 6px;
  color: #fff; font-size: 14px; font-weight: 600;
  cursor: pointer; transition: background .2s;
}
.login-btn:hover { background: #6d28d9; }
.error {
  background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3);
  color: #fca5a5; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px;
}
.powered { font-size: 11px; color: #555577; margin-top: 24px; }
.powered span { color: #7c3aed; }
</style>
</head>
<body>
<div class="login-card">
  <div class="logo"><i class="fa fa-shield"></i></div>
  <h1>Protect</h1>
  <p class="sub">Authorized access only</p>
  <?php if ($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <form method="post">
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Enter password" autofocus>
    </div>
    <button type="submit" class="login-btn">Sign In</button>
  </form>
  <p class="powered">DANN Network</p>
</div>
</body>
</html>
<?php
exit;
endif;

$tab = $_GET['tab'] ?? 'overview';
$cfg = loadConfig();
$msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Quick actions
    if (isset($_POST['action'])) {
        $act = $_POST['action'];
        if ($act === 'reload_nginx') {
            exec('nginx -t 2>&1 && systemctl reload nginx', $out, $rc);
            $msg = $rc === 0 ? '<div class="alert alert-success">Nginx reloaded successfully</div>' : '<div class="alert alert-error">Nginx error: '.implode(' ', $out).'</div>';
        } elseif ($act === 'restart_dann') {
            exec('systemctl restart dann_guard 2>&1', $out, $rc);
            $msg = $rc === 0 ? '<div class="alert alert-success">Dann Guard restarted</div>' : '<div class="alert alert-error">Error: '.implode(' ', $out).'</div>';
        } elseif ($act === 'restart_php') {
            $php_ver = $cfg['php_version'] ?? '8.2';
            exec("systemctl restart php{$php_ver}-fpm 2>&1", $out, $rc);
            $msg = $rc === 0 ? '<div class="alert alert-success">PHP-FPM restarted</div>' : '<div class="alert alert-error">Error: '.implode(' ', $out).'</div>';
        } elseif ($act === 'apply_nginx_limits') {
            $panel_r = intval($_POST['panel_rate'] ?? 30);
            $api_r = intval($_POST['api_rate'] ?? 60);
            $login_r = intval($_POST['login_rate'] ?? 5);
            $cfg['limits']['panel_rate'] = $panel_r;
            $cfg['limits']['api_rate'] = $api_r;
            $cfg['limits']['login_rate'] = $login_r;
            saveConfig($cfg);
            $conf = "# Rate limiting zones - generated by Protect Dashboard\n";
            $conf .= "limit_req_zone \$binary_remote_addr zone=panel_limit:10m rate={$panel_r}r/s;\n";
            $conf .= "limit_req_zone \$binary_remote_addr zone=api_limit:10m rate={$api_r}r/s;\n";
            $conf .= "limit_req_zone \$binary_remote_addr zone=login_limit:10m rate={$login_r}r/s;\n";
            $conf .= "limit_conn_zone \$binary_remote_addr zone=conn_limit:10m;\n\n";
            $conf .= file_get_contents('/root/dann_guard/nginx/anti-ddos-map.conf') ?: '';
            file_put_contents('/etc/nginx/conf.d/anti-ddos.conf', $conf);
            exec('nginx -t 2>&1 && systemctl reload nginx', $out2, $rc2);
            $msg = $rc2 === 0 ? '<div class="alert alert-success">Rate limits applied, nginx reloaded</div>' : '<div class="alert alert-error">Nginx error: '.implode(' ', $out2).'</div>';
        } elseif ($act === 'start_rce_watch') {
            exec('nohup bash /root/dann_guard/rce_watch.sh start > /dev/null 2>&1 &', $out, $rc);
            sleep(1);
            $pid = trim(@file_get_contents('/var/run/rce_watch.pid') ?? '');
            $running = $pid !== '' && trim(@shell_exec("kill -0 $pid 2>/dev/null && echo 1") ?? '') === '1';
            $msg = $running ? '<div class="alert alert-success">RCE Watch daemon started (PID ' . $pid . ')</div>' : '<div class="alert alert-error">Failed to start RCE Watch</div>';
        } elseif ($act === 'stop_rce_watch') {
            exec('bash /root/dann_guard/rce_watch.sh stop 2>&1', $out, $rc);
            $msg = '<div class="alert alert-warning">RCE Watch daemon stopped</div>';
        } elseif ($act === 'add_rce_pattern') {
            $pattern = trim($_POST['new_pattern'] ?? '');
            if ($pattern && !in_array($pattern, $cfg['rce']['patterns'] ?? [])) {
                $cfg['rce']['patterns'][] = $pattern;
                saveConfig($cfg);
                $msg = '<div class="alert alert-success">Pattern added: ' . htmlspecialchars($pattern) . '</div>';
            }
        } elseif ($act === 'remove_rce_pattern') {
            $pattern = $_POST['remove_pattern'] ?? '';
            $cfg['rce']['patterns'] = array_values(array_filter($cfg['rce']['patterns'] ?? [], function($p) use ($pattern) { return $p !== $pattern; }));
            saveConfig($cfg);
            $msg = '<div class="alert alert-success">Pattern removed: ' . htmlspecialchars($pattern) . '</div>';
        } elseif ($act === 'add_rce_extension') {
            $ext = trim($_POST['new_extension'] ?? '');
            if ($ext && !in_array($ext, $cfg['rce']['extensions'] ?? [])) {
                $cfg['rce']['extensions'][] = $ext;
                saveConfig($cfg);
                $msg = '<div class="alert alert-success">Extension added: .' . htmlspecialchars($ext) . '</div>';
            }
        } elseif ($act === 'remove_rce_extension') {
            $ext = $_POST['remove_extension'] ?? '';
            $cfg['rce']['extensions'] = array_values(array_filter($cfg['rce']['extensions'] ?? [], function($e) use ($ext) { return $e !== $ext; }));
            saveConfig($cfg);
            $msg = '<div class="alert alert-success">Extension removed: .' . htmlspecialchars($ext) . '</div>';
        }
    }

    // Settings save
    if (isset($_POST['save_settings'])) {
        $cfg['telegram']['token'] = $_POST['telegram_token'] ?? $cfg['telegram']['token'] ?? '';
        $cfg['telegram']['chat_id'] = $_POST['telegram_chat_id'] ?? $cfg['telegram']['chat_id'] ?? '';
        $cfg['telegram']['channel'] = $_POST['channel'] ?? '';
        $cfg['telegram']['report_channel'] = $_POST['report_channel'] ?? '';
        $cfg['telegram']['creator'] = $_POST['creator'] ?? '';
        $cfg['rce']['enabled'] = isset($_POST['rce_enabled']);
        $cfg['rce']['patterns'] = array_filter(array_map('trim', explode("\n", $_POST['rce_patterns'] ?? '')));
        $cfg['rce']['extensions'] = array_filter(array_map('trim', explode("\n", $_POST['rce_extensions'] ?? '')));
        $cfg['quarantine']['path'] = $_POST['quarantine_path'] ?? '/var/lib/pterodactyl/quarantine';
        $cfg['quarantine']['auto_clean_days'] = intval($_POST['auto_clean_days'] ?? 30);
        $cfg['protect']['password'] = $_POST['protect_password'] ?? $cfg['protect']['password'] ?? 'protect123';
        $cfg['php_version'] = $_POST['php_version'] ?? $cfg['php_version'] ?? '8.2';
        $cfg['limits']['panel_rate'] = intval($_POST['panel_rate'] ?? ($cfg['limits']['panel_rate'] ?? 30));
        $cfg['limits']['api_rate'] = intval($_POST['api_rate'] ?? ($cfg['limits']['api_rate'] ?? 60));
        $cfg['limits']['login_rate'] = intval($_POST['login_rate'] ?? ($cfg['limits']['login_rate'] ?? 5));
        if (saveConfig($cfg)) {
            $msg = '<div class="alert alert-success">Settings saved</div>';
        } else {
            $msg = '<div class="alert alert-error">Failed to save settings</div>';
        }
    }

    // Break-glass
    if (isset($_POST['break_glass'])) {
        $duration = intval($_POST['duration'] ?? 5);
        $_SESSION['break_glass'] = time() + ($duration * 60);
        $msg = '<div class="alert alert-warning">Break-glass activated for ' . $duration . ' minutes</div>';
        $glass_active = true;
    }
    if (isset($_POST['revoke_break'])) {
        unset($_SESSION['break_glass']);
        $msg = '<div class="alert alert-success">Break-glass access revoked</div>';
    }

    // Broadcast
    if (isset($_POST['send_broadcast'])) {
        $message = $_POST['message'] ?? '';
        $channel = $_POST['broadcast_channel'] ?? 'channel';
        $cfg2 = loadConfig();
        $token = $cfg2['telegram']['token'] ?? '';
        $chat = '';
        if ($channel === 'channel') $chat = $cfg2['telegram']['channel'] ?? '';
        elseif ($channel === 'report') $chat = $cfg2['telegram']['report_channel'] ?? '';
        if ($token && $chat && $message) {
            $out = run("curl -s -X POST 'https://api.telegram.org/bot{$token}/sendMessage' -d 'chat_id={$chat}&text=" . urlencode("[BROADCAST]\n\n{$message}") . "'");
            $msg = '<div class="alert alert-success">Broadcast sent</div>';
        } else {
            $msg = '<div class="alert alert-error">Missing token, chat, or message</div>';
        }
    }

    // Quarantine
    if (isset($_POST['quarantine_action'])) {
        $action = $_POST['quarantine_action'];
        $id = intval($_POST['id'] ?? 0);
        $db = dbConn();
        if ($db && $id) {
            $st = $db->prepare("SELECT * FROM quarantine WHERE id = ?");
            $st->execute([$id]);
            $item = $st->fetch();
            if ($item) {
                if ($action === 'release') {
                    $dir = dirname($item['original_path']);
                    if (!is_dir($dir)) @mkdir($dir, 0755, true);
                    if (@rename($item['file_path'], $item['original_path'])) {
                        $db->prepare("UPDATE quarantine SET status='released' WHERE id=?")->execute([$id]);
                        $msg = '<div class="alert alert-success">File released</div>';
                    } else {
                        $msg = '<div class="alert alert-error">Failed to release file</div>';
                    }
                } elseif ($action === 'delete') {
                    @unlink($item['file_path']);
                    $db->prepare("UPDATE quarantine SET status='deleted' WHERE id=?")->execute([$id]);
                    $msg = '<div class="alert alert-success">File deleted permanently</div>';
                }
            }
        }
    }
}

// System stats
$nginx_active = isActive('nginx');
$php_active = isActive('php8.2-fpm');
$dann_active = isActive('dann_guard');
$wings_active = isActive('wings');
$redis_active = isActive('redis-server');
$mariadb_active = isActive('mariadb');
$load = sys_getloadavg();
$mem = explode("\n", file_get_contents('/proc/meminfo'));
$mem_total = round(intval(str_replace('kB','',explode(':',$mem[0])[1]))/1024/1024,1);
$mem_free = round(intval(str_replace('kB','',explode(':',$mem[2])[1]))/1024/1024,1);
$mem_used = round($mem_total - $mem_free, 1);
$mem_pct = round(($mem_used/$mem_total)*100, 0);
$disk_total = round(disk_total_space('/var/lib/pterodactyl')/1024/1024/1024, 1);
$disk_free = round(disk_free_space('/var/lib/pterodactyl')/1024/1024/1024, 1);
$disk_used = round($disk_total - $disk_free, 1);
$disk_pct = round(($disk_used/$disk_total)*100, 0);
$uptime = run('uptime -p');
$hostname = run('hostname');
$challenge_on = (run("grep -c '^    limit_req zone=panel_limit' /etc/nginx/sites-available/pterodactyl") > 0);
$challenge_log = run('tail -10 /var/log/nginx/pterodactyl.app-access.log | grep -c "__ddos_challenge"');
$rce_enabled = $cfg['rce']['enabled'] ?? true;

// DB data
$db = dbConn();
$violation_count = 0;
$illegal_count = 0;
$q_count = 0;
$q_list = [];
$violations_list = [];
if ($db) {
    $violation_count = $db->query("SELECT COUNT(*) FROM user_violations")->fetchColumn();
    $illegal_count = $db->query("SELECT COUNT(*) FROM illegal_files")->fetchColumn();
    $q_count = $db->query("SELECT COUNT(*) FROM quarantine WHERE status='quarantined'")->fetchColumn();
    
    if ($tab === 'quarantine') {
        $status_filter = $_GET['status'] ?? 'quarantined';
        $st = $db->prepare("SELECT * FROM quarantine WHERE status=? ORDER BY detected_at DESC LIMIT 100");
        $st->execute([$status_filter]);
        $q_list = $st->fetchAll();
    }
    if ($tab === 'security') {
        $st = $db->query("SELECT * FROM user_violations ORDER BY created_at DESC LIMIT 100");
        $violations_list = $st->fetchAll();
    }
    if ($tab === 'rce') {
        $rce_total = $db->query("SELECT COUNT(*) FROM illegal_files")->fetchColumn();
        $rce_today = $db->query("SELECT COUNT(*) FROM illegal_files WHERE DATE(first_seen)=CURDATE()")->fetchColumn();
        $rce_servers = $db->query("SELECT COUNT(DISTINCT server_uuid) FROM illegal_files")->fetchColumn();
        $rce_recent = $db->query("SELECT * FROM illegal_files ORDER BY first_seen DESC LIMIT 100")->fetchAll();
        $rce_watch_pid = trim(@file_get_contents('/var/run/rce_watch.pid') ?? '');
        $rce_watch_active = $rce_watch_pid !== '' && trim(@shell_exec("kill -0 $rce_watch_pid 2>/dev/null && echo 1") ?? '') === '1';
    }
}
$disk_vol_total = round(disk_total_space('/')/1024/1024/1024, 1);
$disk_vol_free = round(disk_free_space('/')/1024/1024/1024, 1);
$disk_vol_used = round($disk_vol_total - $disk_vol_free, 1);
$disk_vol_pct = round(($disk_vol_used/$disk_vol_total)*100, 0);

$break_until = $_SESSION['break_glass'] ?? 0;
$glass_active = $break_until > time();

// JSON stats endpoint for real-time polling
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    $data = [
        'load' => $load,
        'mem_used' => $mem_used,
        'mem_total' => $mem_total,
        'mem_pct' => $mem_pct,
        'disk_used' => $disk_used,
        'disk_total' => $disk_total,
        'disk_pct' => $disk_pct,
        'disk_vol_used' => $disk_vol_used,
        'disk_vol_total' => $disk_vol_total,
        'disk_vol_pct' => $disk_vol_pct,
        'nginx_active' => $nginx_active,
        'php_active' => $php_active,
        'redis_active' => $redis_active,
        'mariadb_active' => $mariadb_active,
        'wings_active' => $wings_active,
        'dann_active' => $dann_active,
        'uptime' => $uptime,
        'violation_count' => $violation_count,
        'illegal_count' => $illegal_count,
        'q_count' => $q_count,
        'glass_active' => $glass_active,
        'rce_total' => intval($db ? $db->query("SELECT COUNT(*) FROM illegal_files")->fetchColumn() : 0),
        'rce_today' => intval($db ? $db->query("SELECT COUNT(*) FROM illegal_files WHERE DATE(first_seen)=CURDATE()")->fetchColumn() : 0),
        'rce_watch_active' => (function(){
            $pid = trim(@file_get_contents('/var/run/rce_watch.pid') ?? '');
            return $pid !== '' && trim(@shell_exec("kill -0 $pid 2>/dev/null && echo 1") ?? '') === '1';
        })(),
    ];
    echo json_encode($data);
    exit;
}

// Handle admin permission save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_admin_perms'])) {
    $db = dbConn();
    if ($db) {
        $user_id = intval($_POST['user_id'] ?? 0);
        $perms = [];
        $all_keys = ['overview','settings','api','databases','locations','nodes','servers','users','protect','mounts','nests'];
        foreach ($all_keys as $k) {
            if (isset($_POST['perm_' . $k])) $perms[$k] = true;
        }
        $json = json_encode($perms);
        $st = $db->prepare("UPDATE users SET protect_permissions = ? WHERE id = ?");
        $st->execute([$json, $user_id]);
        $msg = '<div class="alert alert-success">Permissions saved for user #' . $user_id . '</div>';
    } else {
        $msg = '<div class="alert alert-error">Database connection failed</div>';
    }
}

$tabs = [
    'overview' => 'Overview',
    'breach' => 'Break-Glass',
    'quarantine' => 'Quarantine',
    'rce' => 'RCE Control',
    'broadcast' => 'Broadcast',
    'notifications' => 'Notifications',
    'health' => 'RUM / Health',
    'security' => 'Security Events',
    'challenge' => 'Attack & Challenge',
    'admins' => 'Administrators',
    'ads' => 'ADS',
];

function tabUrl($t) { return '/admin/protect?tab=' . $t; }

// Fetch admin users list
$admin_users = [];
if ($db && $tab === 'admins') {
    $st = $db->query("SELECT id, username, email, name_first, name_last, root_admin, protect_permissions FROM users WHERE root_admin = 1 OR protect_permissions IS NOT NULL ORDER BY id");
    $admin_users = $st->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Protect - <?=ucfirst($tab)?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  background: #141425;
  color: #c8c8d8;
  min-height: 100vh;
}

/* Header */
.header {
  background: #1c1c36;
  border-bottom: 1px solid #2a2a50;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 52px;
}
.header-left { display: flex; align-items: center; gap: 12px; }
.header-left .logo { font-size: 20px; }
.header-left .brand { font-size: 15px; font-weight: 600; color: #e0e0f0; letter-spacing: 0.3px; }
.header-left .brand span { color: #7c3aed; }
.header-right { display: flex; align-items: center; gap: 16px; font-size: 13px; }
.header-right .user { color: #7a7a9a; }
.header-right a { color: #a78bfa; text-decoration: none; }
.header-right a:hover { color: #c4b5fd; }
.header-right .logout { color: #f87171; }

/* Tab nav */
.tab-bar {
  background: #1c1c36;
  border-bottom: 1px solid #2a2a50;
  display: flex;
  overflow-x: auto;
  padding: 0 16px;
  gap: 0;
}
.tab-bar::-webkit-scrollbar { height: 3px; }
.tab-bar::-webkit-scrollbar-thumb { background: #3a3a5a; border-radius: 2px; }
.tab-item {
  padding: 11px 16px;
  font-size: 13px;
  color: #7a7a9a;
  text-decoration: none;
  white-space: nowrap;
  border-bottom: 2px solid transparent;
  transition: all .2s;
  display: flex;
  align-items: center;
  gap: 6px;
}
.tab-item:hover { color: #c4b5fd; background: rgba(124,58,237,0.06); }
.tab-item.active { color: #a78bfa; border-bottom-color: #7c3aed; }
.tab-item .badge {
  background: #7c3aed;
  color: #fff;
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 8px;
  font-weight: 600;
}

/* Content */
.content { padding: 24px; max-width: 1400px; }

/* Cards */
.row { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px; }
.card {
  background: #1e1e38;
  border: 1px solid #2a2a50;
  border-radius: 6px;
  padding: 20px;
}
.card-header { font-size: 12px; color: #7a7a9a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; font-weight: 600; }
.stat { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid #252545; }
.stat:last-child { border-bottom: none; }
.stat-label { color: #9a9ab0; font-size: 13px; }
.stat-value { font-weight: 500; font-size: 14px; }

/* Badges */
.badge {
  display: inline-block; padding: 2px 8px; border-radius: 3px;
  font-size: 11px; font-weight: 600;
}
.badge-green { background: rgba(74,222,128,0.12); color: #4ade80; }
.badge-red { background: rgba(248,113,113,0.12); color: #f87171; }
.badge-yellow { background: rgba(250,204,21,0.12); color: #facc15; }
.badge-purple { background: rgba(124,58,237,0.15); color: #a78bfa; }

/* Progress bars */
.progress { height: 4px; background: #252545; border-radius: 2px; margin-top: 6px; overflow: hidden; }
.progress-bar { height: 100%; border-radius: 2px; background: #7c3aed; }
.progress-bar.green { background: #22c55e; }
.progress-bar.yellow { background: #eab308; }
.progress-bar.red { background: #ef4444; }

/* Buttons */
.btn {
  padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;
  font-size: 13px; font-weight: 500; transition: all .15s;
  text-decoration: none; display: inline-block;
}
.btn:active { transform: scale(0.97); }
.btn-primary { background: #7c3aed; color: #fff; }
.btn-primary:hover { background: #6d28d9; }
.btn-danger { background: #ef4444; color: #fff; }
.btn-danger:hover { background: #dc2626; }
.btn-secondary { background: #2a2a50; color: #c8c8d8; }
.btn-secondary:hover { background: #3a3a5a; }
.btn-success { background: #22c55e; color: #fff; }
.btn-success:hover { background: #16a34a; }
.btn-warning { background: #eab308; color: #000; }
.btn-warning:hover { background: #ca8a04; }
.btn-sm { padding: 5px 12px; font-size: 12px; }
.actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }

/* Alerts */
.alert {
  padding: 10px 14px; border-radius: 4px; margin-bottom: 16px;
  font-size: 13px; border-left: 3px solid transparent;
}
.alert-success { background: rgba(74,222,128,0.1); color: #86efac; border-color: #22c55e; }
.alert-error { background: rgba(248,113,113,0.1); color: #fca5a5; border-color: #ef4444; }
.alert-warning { background: rgba(250,204,21,0.1); color: #fde68a; border-color: #eab308; }
.alert-info { background: rgba(96,165,250,0.1); color: #93c5fd; border-color: #3b82f6; }

/* Form */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12px; color: #9a9ac0; font-weight: 600; margin-bottom: 4px; }
.form-group input, .form-group textarea, .form-group select {
  width: 100%; padding: 8px 12px;
  background: #141428;
  border: 1px solid #32325a;
  border-radius: 4px; color: #d0d0e0; font-size: 13px; outline: none; transition: border .2s;
}
.form-group input:focus, .form-group textarea:focus { border-color: #7c3aed; }
.form-group textarea { min-height: 80px; font-family: monospace; }
.form-group .hint { font-size: 11px; color: #5a5a7a; margin-top: 3px; }
.form-row { display: flex; gap: 16px; }
.form-row .form-group { flex: 1; }
.form-card { max-width: 720px; }
.checkbox-group { display: flex; align-items: center; gap: 8px; }
.checkbox-group input[type="checkbox"] { width: auto; accent-color: #7c3aed; }
.checkbox-group label { margin-bottom: 0; cursor: pointer; }

/* Table */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
table th {
  text-align: left; padding: 8px 10px;
  color: #7a7a9a; font-size: 11px; text-transform: uppercase;
  letter-spacing: 0.5px; border-bottom: 1px solid #2a2a50; font-weight: 600;
}
table td { padding: 8px 10px; border-bottom: 1px solid #222240; }
table tr:hover td { background: rgba(124,58,237,0.04); }
.table-empty { text-align: center; padding: 40px; color: #5a5a7a; font-size: 13px; }

/* Filter bar */
.filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 16px; }
.filter-bar select {
  background: #141428; border: 1px solid #32325a;
  border-radius: 4px; color: #d0d0e0; padding: 6px 10px; font-size: 12px; outline: none;
}

/* Page title */
.page-title { font-size: 18px; font-weight: 600; color: #e0e0f0; margin-bottom: 20px; }
.page-title span { color: #7c3aed; }

/* Grid variants */
.row-2 { grid-template-columns: 1fr 1fr; }
.row-3 { grid-template-columns: 1fr 1fr 1fr; }
.row-4 { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }

/* Glass bar */
.glass-bar { display: flex; align-items: center; gap: 12px; }

/* Notif list */
.notif-item { padding: 10px 0; border-bottom: 1px solid #252545; font-size: 13px; }
.notif-item:last-child { border-bottom: none; }
.notif-time { font-size: 11px; color: #5a5a7a; }
.notif-text { margin-top: 2px; }

/* RUM */
.rum-stat { text-align: center; padding: 16px; }
.rum-stat .value { font-size: 28px; font-weight: 700; color: #e0e0f0; }
.rum-stat .label { font-size: 12px; color: #7a7a9a; margin-top: 4px; }

@media(max-width:768px) {
  .row-2, .row-3, .row-4 { grid-template-columns: 1fr; }
  .content { padding: 16px; }
  .form-row { flex-direction: column; gap: 0; }
  .tab-item { padding: 11px 12px; font-size: 12px; }
}
</style>
</head>
<body>

<div class="header">
  <div class="header-left">
    <span class="logo"><i class="fa fa-shield"></i></span>
    <span class="brand">PROTECT <span>DASHBOARD</span></span>
  </div>
  <div class="header-right">
    <span class="user"><?=$hostname?></span>
    <a href="/admin">Panel</a>
    <a href="?logout=1" class="logout">Logout</a>
  </div>
</div>

<div class="tab-bar">
  <?php foreach ($tabs as $k => $v):
    $active = $tab === $k;
    $badge = '';
    if ($k === 'quarantine' && $q_count > 0) $badge = $q_count;
  ?>
  <a class="tab-item <?=$active?'active':''?>" href="<?=tabUrl($k)?>">
    <?=$v?> <?php if ($badge !== ''): ?><span class="badge"><?=$badge?></span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="content">
  <?=$msg?>

  <?php if ($tab === 'overview'): ?>
  <div class="page-title"><span><i class="fa fa-dashboard"></i></span> Overview</div>

  <div class="row row-4" id="stats-cards">
    <div class="card">
      <div class="card-header">CPU Load</div>
      <div class="rum-stat">
        <div class="value" id="cpu-val"><?=round($load[0],2)?></div>
        <div class="label">1 min / 5 min / 15 min</div>
        <div style="font-size:11px;color:#5a5a7a;margin-top:4px" id="cpu-detail"><?=round($load[1],2)?> / <?=round($load[2],2)?></div>
      </div>
      <svg id="cpu-graph" width="100%" height="30" viewBox="0 0 60 30" preserveAspectRatio="none" style="display:block;margin-top:4px"></svg>
    </div>
    <div class="card">
      <div class="card-header">Memory</div>
      <div class="rum-stat">
        <div class="value" id="mem-val"><?=$mem_used?> <span style="font-size:16px;color:#7a7a9a">GB</span></div>
        <div class="label" id="mem-label">of <?=$mem_total?> GB used</div>
      </div>
      <div class="progress"><div class="progress-bar" id="mem-bar" style="width:<?=$mem_pct?>%"></div></div>
      <svg id="mem-graph" width="100%" height="30" viewBox="0 0 60 30" preserveAspectRatio="none" style="display:block;margin-top:4px"></svg>
    </div>
    <div class="card">
      <div class="card-header">Disk (Volumes)</div>
      <div class="rum-stat">
        <div class="value" id="disk-val"><?=$disk_used?> <span style="font-size:16px;color:#7a7a9a">GB</span></div>
        <div class="label" id="disk-label">of <?=$disk_total?> GB used</div>
      </div>
      <div class="progress"><div class="progress-bar" id="disk-bar" style="width:<?=$disk_pct?>%"></div></div>
      <svg id="disk-graph" width="100%" height="30" viewBox="0 0 60 30" preserveAspectRatio="none" style="display:block;margin-top:4px"></svg>
    </div>
    <div class="card">
      <div class="card-header">Uptime</div>
      <div class="rum-stat">
        <div class="value" style="font-size:22px" id="uptime-val"><?=str_replace('up ','',$uptime)?></div>
        <div class="label">System uptime</div>
      </div>
    </div>
  </div>

  <div class="row row-3">
    <div class="card">
      <div class="card-header">Services</div>
      <div class="stat"><span class="stat-label">Nginx</span><span class="badge <?=$nginx_active?'badge-green':'badge-red'?>"><?=$nginx_active?'Running':'Stopped'?></span></div>
      <div class="stat"><span class="stat-label">PHP-FPM</span><span class="badge <?=$php_active?'badge-green':'badge-red'?>"><?=$php_active?'Running':'Stopped'?></span></div>
      <div class="stat"><span class="stat-label">Redis</span><span class="badge <?=$redis_active?'badge-green':'badge-red'?>"><?=$redis_active?'Running':'Stopped'?></span></div>
      <div class="stat"><span class="stat-label">MariaDB</span><span class="badge <?=$mariadb_active?'badge-green':'badge-red'?>"><?=$mariadb_active?'Running':'Stopped'?></span></div>
      <div class="stat"><span class="stat-label">Wings</span><span class="badge <?=$wings_active?'badge-green':'badge-red'?>"><?=$wings_active?'Running':'Stopped'?></span></div>
      <div class="stat"><span class="stat-label">Dann Guard</span><span class="badge <?=$dann_active?'badge-green':'badge-red'?>"><?=$dann_active?'Running':'Stopped'?></span></div>
    </div>
    <div class="card">
      <div class="card-header">Security Overview</div>
      <div class="stat"><span class="stat-label">Challenge System</span><span class="badge <?=$challenge_on?'badge-green':'badge-red'?>"><?=$challenge_on?'Active':'Disabled'?></span></div>
      <div class="stat"><span class="stat-label">RCE Protection</span><span class="badge <?=$rce_enabled?'badge-green':'badge-red'?>"><?=$rce_enabled?'Enabled':'Disabled'?></span></div>
      <div class="stat"><span class="stat-label">RCE Watch</span><span id="rce-watch-status"><span class="badge badge-yellow">...</span></span></div>
      <div class="stat"><span class="stat-label">RCE Blocked</span><span class="stat-value" id="rce-total-count"><?=$illegal_count?></span></div>
      <div class="stat"><span class="stat-label">RCE Today</span><span class="stat-value" id="rce-today-count">—</span></div>
      <div class="stat"><span class="stat-label">Quarantined</span><span class="stat-value"><a href="<?=tabUrl('quarantine')?>" style="color:#a78bfa;text-decoration:none"><?=$q_count?></a></span></div>
    </div>
    <div class="card">
      <div class="card-header">Quick Actions</div>
      <div class="actions">
        <form method="post" style="display:inline"><button type="submit" name="action" value="reload_nginx" class="btn btn-primary btn-sm">Reload Nginx</button></form>
        <form method="post" style="display:inline"><button type="submit" name="action" value="restart_php" class="btn btn-secondary btn-sm">Restart PHP-FPM</button></form>
        <form method="post" style="display:inline"><button type="submit" name="action" value="restart_dann" class="btn btn-danger btn-sm">Restart Dann Guard</button></form>
        <form method="post" style="display:inline">
          <input type="hidden" name="panel_rate" value="<?=intval($cfg['limits']['panel_rate'] ?? 30)?>">
          <input type="hidden" name="api_rate" value="<?=intval($cfg['limits']['api_rate'] ?? 60)?>">
          <input type="hidden" name="login_rate" value="<?=intval($cfg['limits']['login_rate'] ?? 5)?>">
          <button type="submit" name="action" value="apply_nginx_limits" class="btn btn-warning btn-sm">Apply Rate Limits</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="card-header">Protection Rules</div>
    <div class="row row-4" style="margin:0;gap:8px">
      <div class="stat"><span class="stat-label">Panel Rate Limit</span><span class="stat-value">30 req/s</span></div>
      <div class="stat"><span class="stat-label">Login Rate Limit</span><span class="stat-value">5 req/s</span></div>
      <div class="stat"><span class="stat-label">API Rate Limit</span><span class="stat-value">60 req/s</span></div>
      <div class="stat"><span class="stat-label">Bad Bot Block</span><span class="badge badge-green">Enabled</span></div>
      <div class="stat"><span class="stat-label">Clearance Method</span><span class="stat-value">Device ID + IP</span></div>
      <div class="stat"><span class="stat-label">Clearance TTL</span><span class="stat-value">1 hour</span></div>
    </div>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="card-header" style="cursor:pointer" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'">⚙️ Configuration <span style="font-weight:400;text-transform:none;color:#5a5a7a">(click to expand)</span></div>
    <div style="display:none;padding-top:16px">
      <form method="post">
        <div class="card-header" style="margin-bottom:12px">Telegram</div>
        <div class="form-row">
          <div class="form-group"><label>Bot Token</label><input type="text" name="telegram_token" value="<?=htmlspecialchars($cfg['telegram']['token'] ?? '')?>" placeholder="123456:ABCdef..."></div>
          <div class="form-group"><label>Chat ID</label><input type="text" name="telegram_chat_id" value="<?=htmlspecialchars($cfg['telegram']['chat_id'] ?? '')?>" placeholder="-1001234567890"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Channel</label><input type="text" name="channel" value="<?=htmlspecialchars($cfg['telegram']['channel'] ?? '')?>" placeholder="@channel"></div>
          <div class="form-group"><label>Report Channel</label><input type="text" name="report_channel" value="<?=htmlspecialchars($cfg['telegram']['report_channel'] ?? '')?>" placeholder="@reportchannel"></div>
        </div>
        <div class="form-group"><label>Creator</label><input type="text" name="creator" value="<?=htmlspecialchars($cfg['telegram']['creator'] ?? '')?>" placeholder="@username"></div>

        <div class="card-header" style="margin:16px 0 12px">RCE Detection</div>
        <div class="checkbox-group" style="margin-bottom:12px">
          <input type="checkbox" id="rce_enabled" name="rce_enabled" value="1" <?=$rce_enabled?'checked':''?>>
          <label for="rce_enabled">Enable RCE scanning</label>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Patterns (one per line)</label><textarea name="rce_patterns" rows="5"><?=htmlspecialchars(implode("\n", $cfg['rce']['patterns'] ?? []))?></textarea></div>
          <div class="form-group"><label>Extensions (one per line)</label><textarea name="rce_extensions" rows="5"><?=htmlspecialchars(implode("\n", $cfg['rce']['extensions'] ?? []))?></textarea></div>
        </div>

        <div class="card-header" style="margin:16px 0 12px">Quarantine</div>
        <div class="form-row">
          <div class="form-group"><label>Path</label><input type="text" name="quarantine_path" value="<?=htmlspecialchars($cfg['quarantine']['path'] ?? '/var/lib/pterodactyl/quarantine')?>"></div>
          <div class="form-group"><label>Auto-clean (days)</label><input type="number" name="auto_clean_days" value="<?=intval($cfg['quarantine']['auto_clean_days'] ?? 30)?>" min="0"></div>
        </div>

        <div class="card-header" style="margin:16px 0 12px">Server</div>
        <div class="form-row">
          <div class="form-group"><label>PHP Version</label><input type="text" name="php_version" value="<?=htmlspecialchars($cfg['php_version'] ?? '8.2')?>" placeholder="8.2"></div>
        </div>

        <div class="card-header" style="margin:16px 0 12px">Rate Limits</div>
        <div class="form-row">
          <div class="form-group"><label>Panel (req/s)</label><input type="number" name="panel_rate" value="<?=intval($cfg['limits']['panel_rate'] ?? 30)?>" min="1"></div>
          <div class="form-group"><label>API (req/s)</label><input type="number" name="api_rate" value="<?=intval($cfg['limits']['api_rate'] ?? 60)?>" min="1"></div>
          <div class="form-group"><label>Login (req/s)</label><input type="number" name="login_rate" value="<?=intval($cfg['limits']['login_rate'] ?? 5)?>" min="1"></div>
        </div>

        <div class="card-header" style="margin:16px 0 12px">Access</div>
        <div class="form-group"><label>Protect Password</label><input type="text" name="protect_password" value="<?=htmlspecialchars($cfg['protect']['password'] ?? 'protect123')?>"></div>

        <div style="margin-top:16px"><button type="submit" name="save_settings" value="1" class="btn btn-primary">Save Configuration</button></div>
      </form>
    </div>
  </div>

  <?php elseif ($tab === 'breach'): ?>
  <div class="page-title"><span><i class="fa fa-unlock-alt"></i></span> Break-Glass</div>

  <div class="row row-2">
    <div class="card form-card">
      <div class="card-header">Emergency Access</div>
      <p style="font-size:13px;color:#9a9ab0;margin-bottom:16px">Temporarily bypass security challenges and rate limits for emergency administration.</p>
      <?php if ($glass_active): ?>
      <div class="alert alert-warning">Break-glass is active. Expires in <?=max(0, ceil(($break_until - time())/60))?> minutes.</div>
      <form method="post">
        <button type="submit" name="revoke_break" value="1" class="btn btn-danger">Revoke Break-Glass</button>
      </form>
      <?php else: ?>
      <form method="post">
        <div class="form-row">
          <div class="form-group">
            <label>Duration (minutes)</label>
            <input type="number" name="duration" value="5" min="1" max="60">
          </div>
        </div>
        <button type="submit" name="break_glass" value="1" class="btn btn-warning">Activate Break-Glass</button>
      </form>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-header">Access Log</div>
      <div class="stat"><span class="stat-label">Status</span><span class="badge <?=$glass_active?'badge-yellow':'badge-green'?>"><?=$glass_active?'ACTIVE':'INACTIVE'?></span></div>
      <div class="stat"><span class="stat-label">Last Activation</span><span class="stat-value">—</span></div>
      <div class="stat"><span class="stat-label">Active Sessions</span><span class="stat-value">1</span></div>
    </div>
  </div>

  <?php elseif ($tab === 'quarantine'): ?>
  <div class="page-title"><span><i class="fa fa-cube"></i></span> Quarantine</div>

  <div class="card">
    <div class="filter-bar">
      <span style="color:#7a7a9a;font-size:12px">Status:</span>
      <select onchange="window.location='<?=tabUrl('quarantine')?>&status='+this.value">
        <option value="quarantined" <?=($status_filter??'quarantined')==='quarantined'?'selected':''?>>Quarantined</option>
        <option value="released" <?=($status_filter??'')==='released'?'selected':''?>>Released</option>
        <option value="deleted" <?=($status_filter??'')==='deleted'?'selected':''?>>Deleted</option>
        <option value="" <?=($status_filter??'')===''?'selected':''?>>All</option>
      </select>
    </div>
    <?php if (empty($q_list)): ?>
    <div class="table-empty">No items in this view</div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>File</th><th>Server</th><th>Reason</th><th>Size</th><th>Detected</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
          <?php foreach ($q_list as $item): ?>
          <tr>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($item['original_path'])?>"><?=htmlspecialchars(basename($item['original_path']))?></td>
            <td style="font-family:monospace;font-size:12px"><?=htmlspecialchars(substr($item['server_uuid']??'',0,8))?></td>
            <td><?=htmlspecialchars($item['reason'])?></td>
            <td><?=$item['file_size'] > 1048576 ? round($item['file_size']/1048576,1).' MB' : round($item['file_size']/1024,1).' KB'?></td>
            <td style="font-size:12px;color:#7a7a9a"><?=$item['detected_at']?></td>
            <td><span class="badge <?=$item['status']==='quarantined'?'badge-yellow':($item['status']==='released'?'badge-green':'badge-red')?>"><?=strtoupper($item['status'])?></span></td>
            <td>
              <?php if ($item['status'] === 'quarantined'): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="id" value="<?=$item['id']?>">
                <button type="submit" name="quarantine_action" value="release" class="btn btn-success btn-sm" onclick="return confirm('Release this file?')">Release</button>
              </form>
              <form method="post" style="display:inline">
                <input type="hidden" name="id" value="<?=$item['id']?>">
                <button type="submit" name="quarantine_action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Delete permanently?')">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif ($tab === 'rce'): ?>
  <div class="page-title"><span><i class="fa fa-bolt"></i></span> RCE Control</div>

  <div class="row row-4" id="rce-stats">
    <div class="card">
      <div class="card-header">Total Detected</div>
      <div class="rum-stat">
        <div class="value"><?=intval($rce_total ?? 0)?></div>
        <div class="label">All time RCE attempts</div>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Today</div>
      <div class="rum-stat">
        <div class="value"><?=intval($rce_today ?? 0)?></div>
        <div class="label">Blocked in last 24h</div>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Servers Affected</div>
      <div class="rum-stat">
        <div class="value"><?=intval($rce_servers ?? 0)?></div>
        <div class="label">Unique servers with RCE</div>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Watch Daemon</div>
      <div class="rum-stat">
        <div class="value"><span class="badge <?=($rce_watch_active??false)?'badge-green':'badge-red'?>" style="font-size:14px"><?=($rce_watch_active??false)?'RUNNING':'STOPPED'?></span></div>
        <div class="label">Real-time file monitor</div>
        <div class="actions" style="margin-top:8px;justify-content:center">
          <form method="post" style="display:inline">
            <button type="submit" name="action" value="<?=($rce_watch_active??false)?'stop_rce_watch':'start_rce_watch'?>" class="btn btn-sm <?=($rce_watch_active??false)?'btn-danger':'btn-success'?>">
              <?=($rce_watch_active??false)?'Stop':'Start'?>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row row-2">
    <div class="card">
      <div class="card-header">Scan Patterns</div>
      <form method="post" style="margin-bottom:12px">
        <div class="form-group">
          <label>Add Pattern</label>
          <div style="display:flex;gap:8px">
            <input type="text" name="new_pattern" placeholder="e.g. system" style="flex:1">
            <button type="submit" name="action" value="add_rce_pattern" class="btn btn-primary btn-sm">Add</button>
          </div>
        </div>
      </form>
      <div style="max-height:200px;overflow-y:auto">
        <?php foreach ($cfg['rce']['patterns'] ?? [] as $p): ?>
        <form method="post" style="display:inline-block;margin:2px">
          <input type="hidden" name="remove_pattern" value="<?=htmlspecialchars($p)?>">
          <button type="submit" name="action" value="remove_rce_pattern" class="btn btn-sm btn-secondary" style="padding:2px 8px;font-size:11px;margin:1px">
            <?=htmlspecialchars($p)?> ✕
          </button>
        </form>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Watch Extensions</div>
      <form method="post" style="margin-bottom:12px">
        <div class="form-group">
          <label>Add Extension</label>
          <div style="display:flex;gap:8px">
            <input type="text" name="new_extension" value="php" style="flex:1">
            <button type="submit" name="action" value="add_rce_extension" class="btn btn-primary btn-sm">Add</button>
          </div>
        </div>
      </form>
      <div>
        <?php foreach ($cfg['rce']['extensions'] ?? [] as $e): ?>
        <form method="post" style="display:inline-block;margin:2px">
          <input type="hidden" name="remove_extension" value="<?=htmlspecialchars($e)?>">
          <button type="submit" name="action" value="remove_rce_extension" class="btn btn-sm btn-secondary" style="padding:2px 8px;font-size:11px;margin:1px">
            .<?=htmlspecialchars($e)?> ✕
          </button>
        </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="card-header">Recent RCE Events (last 100)</div>
    <?php if (empty($rce_recent ?? [])): ?>
    <div class="table-empty">No RCE events detected</div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Time</th><th>File</th><th>Server</th><th>Pattern</th><th>Size</th><th>Seen</th>
        </tr></thead>
        <tbody>
          <?php foreach ($rce_recent as $ev): ?>
          <tr>
            <td style="font-size:12px;color:#7a7a9a;white-space:nowrap"><?=$ev['first_seen']?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($ev['file_path'])?>"><?=htmlspecialchars($ev['file_name'])?></td>
            <td style="font-family:monospace;font-size:12px"><?=htmlspecialchars(substr($ev['server_uuid']??'',0,8))?></td>
            <td><span class="badge badge-red"><?=htmlspecialchars($ev['detection_reason'])?></span></td>
            <td><?=$ev['file_size'] > 1048576 ? round($ev['file_size']/1048576,1).' MB' : round($ev['file_size']/1024,1).' KB'?></td>
            <td><?=intval($ev['seen_count'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif ($tab === 'broadcast'): ?>
  <div class="page-title"><span><i class="fa fa-bullhorn"></i></span> Broadcast</div>

  <div class="row row-2">
    <div class="card form-card">
      <div class="card-header">Send Broadcast</div>
      <form method="post">
        <div class="form-group">
          <label>Channel</label>
          <select name="broadcast_channel">
            <option value="channel">Main Channel (<?=htmlspecialchars($cfg['telegram']['channel'] ?? '—')?>)</option>
            <option value="report">Report Channel (<?=htmlspecialchars($cfg['telegram']['report_channel'] ?? '—')?>)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea name="message" rows="5" placeholder="Type your message here..." required></textarea>
        </div>
        <button type="submit" name="send_broadcast" value="1" class="btn btn-primary">Send Broadcast</button>
      </form>
    </div>
    <div class="card">
      <div class="card-header">Channel Info</div>
      <div class="stat"><span class="stat-label">Main Channel</span><span class="stat-value"><?=htmlspecialchars($cfg['telegram']['channel'] ?? 'Not set')?></span></div>
      <div class="stat"><span class="stat-label">Report Channel</span><span class="stat-value"><?=htmlspecialchars($cfg['telegram']['report_channel'] ?? 'Not set')?></span></div>
      <div class="stat"><span class="stat-label">Creator</span><span class="stat-value"><?=htmlspecialchars($cfg['telegram']['creator'] ?? 'Not set')?></span></div>
      <div class="actions" style="margin-top:16px">
        <a href="<?=tabUrl('notifications')?>" class="btn btn-secondary btn-sm">View Notifications</a>
      </div>
    </div>
  </div>

  <?php elseif ($tab === 'notifications'): ?>
  <div class="page-title"><span><i class="fa fa-bell"></i></span> Notifications</div>

  <div class="card" style="max-width:640px">
    <div class="card-header">System Alerts</div>
    <?php
    $notifs = [];
    if ($db) {
        $st = $db->query("SELECT v.*, u.username FROM user_violations v LEFT JOIN users u ON v.user_id = u.id ORDER BY v.created_at DESC LIMIT 20");
        if ($st) $notifs = $st->fetchAll();
    }
    ?>
    <?php if (empty($notifs)): ?>
    <div class="table-empty">No recent notifications</div>
    <?php else: ?>
    <div class="notif-list">
      <?php foreach ($notifs as $n): ?>
      <div class="notif-item">
        <div class="notif-time"><?=$n['created_at']?> &middot; <?=htmlspecialchars($n['username'] ?? "User #{$n['user_id']}")?></div>
        <div class="notif-text">
          <span class="badge <?=$n['severity'] > 5 ? 'badge-red' : 'badge-yellow'?>" style="margin-right:6px">
            <?=strtoupper($n['violation_type'])?>
          </span>
          <?=htmlspecialchars($n['details'] ?? $n['file_name'] ?? '')?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif ($tab === 'health'): ?>
  <div class="page-title"><span><i class="fa fa-heartbeat"></i></span> RUM / Health</div>

  <div class="row row-4">
    <div class="card">
      <div class="card-header">CPU</div>
      <div class="rum-stat">
        <div class="value"><?=round($load[0],1)?></div>
        <div class="label">Load Average</div>
      </div>
    </div>
    <div class="card">
      <div class="card-header">RAM</div>
      <div class="rum-stat">
        <div class="value"><?=$mem_used?> <span style="font-size:14px">GB</span></div>
        <div class="label">Used / <?=$mem_total?> GB</div>
      </div>
      <div class="progress"><div class="progress-bar <?=$mem_pct>80?'red':($mem_pct>50?'yellow':'green')?>" style="width:<?=$mem_pct?>%"></div></div>
    </div>
    <div class="card">
      <div class="card-header">Disk (/var/lib/pterodactyl)</div>
      <div class="rum-stat">
        <div class="value"><?=$disk_used?> <span style="font-size:14px">GB</span></div>
        <div class="label">Used / <?=$disk_total?> GB</div>
      </div>
      <div class="progress"><div class="progress-bar <?=$disk_pct>80?'red':($disk_pct>50?'yellow':'green')?>" style="width:<?=$disk_pct?>%"></div></div>
    </div>
    <div class="card">
      <div class="card-header">System Disk</div>
      <?php $dp = $disk_vol_pct ?? 0; $du = $disk_vol_used ?? 0; $dt = $disk_vol_total ?? 0; ?>
      <div class="rum-stat">
        <div class="value"><?=$du?> <span style="font-size:14px">GB</span></div>
        <div class="label">Used / <?=$dt?> GB</div>
      </div>
      <div class="progress"><div class="progress-bar <?=$dp>80?'red':($dp>50?'yellow':'green')?>" style="width:<?=$dp?>%"></div></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Services Health</div>
    <div class="row row-4" style="margin:0;gap:8px">
      <div class="stat"><span class="stat-label">Nginx</span><span class="badge <?=$nginx_active?'badge-green':'badge-red'?>"><?=$nginx_active?'Running':'Down'?></span></div>
      <div class="stat"><span class="stat-label">PHP-FPM</span><span class="badge <?=$php_active?'badge-green':'badge-red'?>"><?=$php_active?'Running':'Down'?></span></div>
      <div class="stat"><span class="stat-label">Redis</span><span class="badge <?=$redis_active?'badge-green':'badge-red'?>"><?=$redis_active?'Running':'Down'?></span></div>
      <div class="stat"><span class="stat-label">MariaDB</span><span class="badge <?=$mariadb_active?'badge-green':'badge-red'?>"><?=$mariadb_active?'Running':'Down'?></span></div>
      <div class="stat"><span class="stat-label">Wings</span><span class="badge <?=$wings_active?'badge-green':'badge-red'?>"><?=$wings_active?'Running':'Down'?></span></div>
      <div class="stat"><span class="stat-label">Dann Guard</span><span class="badge <?=$dann_active?'badge-green':'badge-red'?>"><?=$dann_active?'Running':'Down'?></span></div>
    </div>
  </div>

  <?php elseif ($tab === 'security'): ?>
  <div class="page-title"><span><i class="fa fa-list-alt"></i></span> Security Events</div>

  <div class="card">
    <?php if (empty($violations_list)): ?>
    <div class="table-empty">No security events recorded</div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Time</th><th>User</th><th>Type</th><th>Server</th><th>File</th><th>Severity</th><th>Action</th>
        </tr></thead>
        <tbody>
          <?php foreach ($violations_list as $v): ?>
          <tr>
            <td style="font-size:12px;color:#7a7a9a"><?=$v['created_at']?></td>
            <td><?=htmlspecialchars($v['username'] ?? "User #{$v['user_id']}")?></td>
            <td><span class="badge badge-purple"><?=htmlspecialchars($v['violation_type'])?></span></td>
            <td style="font-family:monospace;font-size:12px"><?=htmlspecialchars(substr($v['server_uuid'],0,8))?></td>
            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(basename($v['file_name'] ?? ''))?></td>
            <td><span class="badge <?=$v['severity'] > 5 ? 'badge-red' : 'badge-yellow'?>"><?=intval($v['severity'])?>/10</span></td>
            <td style="font-size:12px"><?=htmlspecialchars($v['action_taken'] ?? '—')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif ($tab === 'challenge'): ?>
  <div class="page-title"><span><i class="fa fa-crosshairs"></i></span> Attack & Challenge</div>

  <div class="row row-3">
    <div class="card">
      <div class="card-header">Challenge Status</div>
      <div class="rum-stat">
        <div class="value"><span class="badge <?=$challenge_on?'badge-green':'badge-red'?>" style="font-size:16px;padding:4px 16px"><?=$challenge_on?'ACTIVE':'DISABLED'?></span></div>
        <div class="label">JS Challenge Protection</div>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Recent Challenges</div>
      <div class="rum-stat">
        <div class="value"><?=$challenge_log?></div>
        <div class="label">hits (last 10 requests)</div>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Clearance</div>
      <div class="rum-stat">
        <div class="value">Device + IP</div>
        <div class="label">1 hour TTL</div>
      </div>
    </div>
  </div>

  <div class="row row-3">
    <div class="card">
      <div class="card-header">Rate Limits</div>
      <div class="stat"><span class="stat-label">Panel</span><span class="stat-value">30 req/s</span></div>
      <div class="stat"><span class="stat-label">Login</span><span class="stat-value">5 req/s</span></div>
      <div class="stat"><span class="stat-label">API</span><span class="stat-value">60 req/s</span></div>
    </div>
    <div class="card">
      <div class="card-header">Block Rules</div>
      <div class="stat"><span class="stat-label">Bad Bots</span><span class="badge badge-green">Enabled</span></div>
      <div class="stat"><span class="stat-label">Bad Referers</span><span class="badge badge-green">Enabled</span></div>
    </div>
    <div class="card">
      <div class="card-header">RCE Protection</div>
      <div class="stat"><span class="stat-label">Scanner</span><span class="badge <?=$rce_enabled?'badge-green':'badge-red'?>"><?=$rce_enabled?'Active':'Disabled'?></span></div>
      <div class="stat"><span class="stat-label">Patterns</span><span class="stat-value"><?=count($cfg['rce']['patterns'] ?? [])?> rules</span></div>
      <div class="actions" style="margin-top:12px">
        <a href="<?=tabUrl('notifications')?>" class="btn btn-secondary btn-sm">View Events</a>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Nginx Challenge Config</div>
    <div class="form-group" style="margin:0">
      <textarea readonly rows="6" style="background:#0e0e20;font-size:12px;cursor:text">location / {
  # JS Challenge with 4s delay
  # Clearance: device_id + IP subnet cookie
  # Rate limits: 30/5/60 req/s
  # Static assets & API skip challenge
  # Admin routes bypass challenge
}</textarea>
    </div>
  </div>

  <?php elseif ($tab === 'admins'): ?>
  <div class="page-title"><span><i class="fa fa-users"></i></span> Administrators</div>

  <div class="card">
    <div class="card-header">Manage Admin Permissions</div>
    <p style="font-size:13px;color:#9a9ab0;margin-bottom:16px">Configure which sidebar menus each admin can see. Full root admins (with no `protect_permissions` set) see all menus by default. Restricted admins only see checked items.</p>
    <?php if (empty($admin_users)): ?>
    <div class="table-empty">No admin users found</div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Root</th>
          <th>Overview</th><th>Settings</th><th>API</th><th>Databases</th><th>Locations</th><th>Nodes</th><th>Servers</th><th>Users</th><th>Protect</th><th>Mounts</th><th>Nests</th>
          <th>Save</th>
        </tr></thead>
        <tbody>
          <?php foreach ($admin_users as $au):
            $au_perms = [];
            if ($au['protect_permissions']) {
                $au_perms = json_decode($au['protect_permissions'], true) ?? [];
            }
            $is_root = $au['root_admin'] == 1 && empty($au_perms);
          ?>
          <tr>
            <form method="post">
            <input type="hidden" name="user_id" value="<?=$au['id']?>">
            <td><?=$au['id']?></td>
            <td style="font-weight:600"><?=htmlspecialchars($au['username'])?></td>
            <td><?=htmlspecialchars($au['name_first'] . ' ' . $au['name_last'])?></td>
            <td style="font-size:12px"><?=htmlspecialchars($au['email'])?></td>
            <td><?php if ($is_root): ?><span class="badge badge-green">ROOT</span><?php elseif ($au['root_admin']==1): ?><span class="badge badge-yellow">PARTIAL</span><?php else: ?><span class="badge badge-purple">RESTRICTED</span><?php endif; ?></td>
            <?php
              $all_keys = ['overview','settings','api','databases','locations','nodes','servers','users','protect','mounts','nests'];
              foreach ($all_keys as $k):
                $checked = $is_root || ($au_perms[$k] ?? false);
            ?>
            <td style="text-align:center"><input type="checkbox" name="perm_<?=$k?>" value="1" <?=$checked?'checked':''?> <?=$is_root?'disabled':''?>></td>
            <?php endforeach; ?>
            <td><button type="submit" name="save_admin_perms" value="1" class="btn btn-primary btn-sm" <?=$is_root?'disabled':''?>>Save</button></td>
            </form>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif ($tab === 'ads'): ?>
  <div class="page-title"><span><i class="fa fa-bullseye"></i></span> ADS</div>

  <div class="row row-2">
    <div class="card form-card">
      <div class="card-header">Advertising Settings</div>
      <p style="font-size:13px;color:#9a9ab0;margin-bottom:16px">Configure advertisements displayed on the panel. Coming soon.</p>
      <div class="form-group">
        <label>Ad Status</label>
        <div style="padding:8px 0"><span class="badge badge-yellow">Coming Soon</span></div>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Stats</div>
      <div class="stat"><span class="stat-label">Impressions</span><span class="stat-value">—</span></div>
      <div class="stat"><span class="stat-label">Clicks</span><span class="stat-value">—</span></div>
      <div class="stat"><span class="stat-label">Revenue</span><span class="stat-value">—</span></div>
    </div>
  </div>

  <?php endif; ?>
</div>

<script>
var cpuHist = [], memHist = [], diskHist = [];
var maxPoints = 20;

function sparkline(svgId, data, color) {
  var svg = document.getElementById(svgId);
  if (!svg || data.length < 2) return;
  var w = 60, h = 30;
  var min = Math.min.apply(null, data), max = Math.max.apply(null, data);
  if (max === min) { max = min + 1; }
  var pts = data.map(function(v, i) {
    var x = (i / (data.length - 1)) * w;
    var y = h - ((v - min) / (max - min)) * (h - 4) - 2;
    return x.toFixed(1) + ',' + y.toFixed(1);
  }).join(' ');
  svg.innerHTML = '<polyline fill="none" stroke="' + color + '" stroke-width="1.5" points="' + pts + '"/>';
}

function fetchStats() {
  var xhr = new XMLHttpRequest();
  xhr.open('GET', '/admin/protect?format=json&t=' + Date.now(), true);
  xhr.onload = function() {
    if (xhr.status !== 200) return;
    try {
      var d = JSON.parse(xhr.responseText);

      document.getElementById('cpu-val').innerHTML = d.load[0].toFixed(2);
      document.getElementById('cpu-detail').innerHTML = d.load[1].toFixed(2) + ' / ' + d.load[2].toFixed(2);
      cpuHist.push(d.load[0]);
      if (cpuHist.length > maxPoints) cpuHist.shift();
      sparkline('cpu-graph', cpuHist, '#7c3aed');

      document.getElementById('mem-val').innerHTML = d.mem_used + ' <span style="font-size:16px;color:#7a7a9a">GB</span>';
      document.getElementById('mem-label').innerHTML = 'of ' + d.mem_total + ' GB used';
      var memPct = d.mem_pct;
      document.getElementById('mem-bar').style.width = memPct + '%';
      document.getElementById('mem-bar').className = 'progress-bar ' + (memPct > 80 ? 'red' : memPct > 50 ? 'yellow' : 'green');
      memHist.push(d.mem_pct);
      if (memHist.length > maxPoints) memHist.shift();
      sparkline('mem-graph', memHist, '#22c55e');

      document.getElementById('disk-val').innerHTML = d.disk_used + ' <span style="font-size:16px;color:#7a7a9a">GB</span>';
      document.getElementById('disk-label').innerHTML = 'of ' + d.disk_total + ' GB used';
      var diskPct = d.disk_pct;
      document.getElementById('disk-bar').style.width = diskPct + '%';
      document.getElementById('disk-bar').className = 'progress-bar ' + (diskPct > 80 ? 'red' : diskPct > 50 ? 'yellow' : 'green');
      diskHist.push(d.disk_pct);
      if (diskHist.length > maxPoints) diskHist.shift();
      sparkline('disk-graph', diskHist, '#eab308');

      document.getElementById('uptime-val').innerHTML = d.uptime.replace('up ','');

      // RCE stats update
      var rceTotal = document.getElementById('rce-total-count');
      var rceToday = document.getElementById('rce-today-count');
      var rceWatch = document.getElementById('rce-watch-status');
      if (rceTotal) rceTotal.innerHTML = d.rce_total;
      if (rceToday) rceToday.innerHTML = d.rce_today;
      if (rceWatch) rceWatch.innerHTML = '<span class="badge ' + (d.rce_watch_active ? 'badge-green' : 'badge-red') + '">' + (d.rce_watch_active ? 'Running' : 'Stopped') + '</span>';
    } catch(e) {}
  };
  xhr.send();
}

// Initialize with some data
for (var i = 0; i < 10; i++) {
  cpuHist.push(<?=round($load[0],2)?>);
  memHist.push(<?=$mem_pct?>);
  diskHist.push(<?=$disk_pct?>);
}
sparkline('cpu-graph', cpuHist, '#7c3aed');
sparkline('mem-graph', memHist, '#22c55e');
sparkline('disk-graph', diskHist, '#eab308');

setInterval(fetchStats, 5000);
</script>

</body>
</html>
