<?php
/**
 * RCE Controller — real-time scan + quarantine engine
 * Can be used from CLI: php rce_controller.php scan <filepath>
 * Or included from the Protect dashboard
 */

class RCEController {
    private $cfg;
    private $db;
    private $patterns;
    private $extensions;
    private $quarantinePath;
    private $volumesPath;

    public function __construct($configPath = '/root/dann_guard/config.json') {
        $this->cfg = @json_decode(@file_get_contents($configPath), true) ?: [];
        $rce = $this->cfg['rce'] ?? [];
        $this->patterns = $rce['patterns'] ?? ['base64_decode', 'eval', 'system', 'exec', 'passthru', 'shell_exec', 'proc_open', 'popen', 'assert', 'create_function', 'extract', 'parse_str', 'import_request_variables', 'phpinfo', 'call_user_func', 'call_user_func_array', 'array_map', 'array_filter', 'array_walk', 'preg_replace', 'ob_start', 'create_function', 'mail', 'putenv', 'ini_set', 'curl_exec', 'curl_init', 'fsockopen', 'pfsockopen', 'stream_socket_server', 'stream_socket_client', 'pcntl_exec'];
        $this->extensions = $rce['extensions'] ?? ['php', 'php5', 'phtml', 'php7', 'php8', 'shtml', 'php3', 'php4'];
        $this->quarantinePath = $rce['quarantine_path'] ?? $this->cfg['quarantine']['path'] ?? '/var/lib/pterodactyl/quarantine';
        $this->volumesPath = $this->cfg['paths']['volumes'] ?? '/var/lib/pterodactyl/volumes';
        $this->connectDB();
    }

    private function connectDB() {
        $dbConf = $this->cfg['database'] ?? [];
        try {
            $this->db = new PDO(
                "mysql:host={$dbConf['host']};dbname={$dbConf['name']};charset=utf8",
                $dbConf['user'], $dbConf['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (Exception $e) {
            $this->db = null;
        }
    }

    public function isEnabled() {
        return ($this->cfg['rce']['enabled'] ?? false) && $this->db !== null;
    }

    public function matchesExtension($filepath) {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if (!$ext) return false;
        foreach ($this->extensions as $allowed) {
            if ($ext === strtolower(trim($allowed, '.'))) return true;
        }
        return false;
    }

    public function extractServerUuid($filepath) {
        $real = realpath($filepath);
        if (!$real) return null;
        $volumes = realpath($this->volumesPath);
        if (!$volumes) return null;
        if (strpos($real, $volumes) !== 0) return null;
        $rel = substr($real, strlen($volumes) + 1);
        $parts = explode('/', $rel);
        return $parts[0] ?? null;
    }

    public function scanFile($filepath) {
        if (!file_exists($filepath) || is_dir($filepath)) return null;
        if (!$this->matchesExtension($filepath)) return null;

        $content = @file_get_contents($filepath);
        if ($content === false || trim($content) === '') return null;

        $matched = [];
        foreach ($this->patterns as $pattern) {
            $count = 0;
            if (stripos($content, $pattern) !== false) {
                $count = preg_match_all('/' . preg_quote($pattern, '/') . '/i', $content, $m);
                $positions = [];
                $lastPos = 0;
                while (($pos = stripos($content, $pattern, $lastPos)) !== false) {
                    $line = substr_count(substr($content, 0, $pos), "\n") + 1;
                    $positions[] = $line;
                    $lastPos = $pos + 1;
                }
                $matched[] = [
                    'pattern' => $pattern,
                    'count' => $count,
                    'lines' => $positions,
                ];
            }
        }

        if (empty($matched)) return null;

        $hash = md5_file($filepath);
        $serverUuid = $this->extractServerUuid($filepath);
        $filename = basename($filepath);
        $size = filesize($filepath);

        return [
            'hash' => $hash,
            'filepath' => $filepath,
            'filename' => $filename,
            'server_uuid' => $serverUuid,
            'size' => $size,
            'matched' => $matched,
            'patterns_found' => array_map(function($m) { return $m['pattern']; }, $matched),
        ];
    }

    public function logIllegal($result) {
        if (!$this->db || !$result) return false;
        $reason = 'RCE: ' . implode(', ', $result['patterns_found']);
        try {
            $st = $this->db->prepare("SELECT id FROM illegal_files WHERE file_hash=? AND server_uuid=?");
            $st->execute([$result['hash'], $result['server_uuid'] ?? '']);
            if ($st->fetch()) {
                $this->db->prepare("UPDATE illegal_files SET last_seen=NOW(), seen_count=seen_count+1 WHERE file_hash=?")->execute([$result['hash']]);
                return 'exists';
            }
            $this->db->prepare("INSERT INTO illegal_files (file_hash,file_name,file_path,server_uuid,user_id,detection_reason,file_size) VALUES (?,?,?,?,0,?,?)")->execute([
                $result['hash'], $result['filename'], $result['filepath'],
                $result['server_uuid'] ?? '', $reason, $result['size'],
            ]);
            return 'logged';
        } catch (Exception $e) {
            return false;
        }
    }

    public function quarantine($result) {
        if (!$this->db || !$result) return false;
        $serverUuid = $result['server_uuid'] ?? 'unknown';
        $qDir = $this->quarantinePath . '/' . $serverUuid;
        if (!is_dir($qDir)) @mkdir($qDir, 0755, true);

        $qFile = $qDir . '/' . $result['filename'] . '.' . time() . '.quar';
        if (!@copy($result['filepath'], $qFile)) return false;

        try {
            $this->db->prepare("INSERT INTO quarantine (file_path,original_path,file_hash,server_uuid,user_id,reason,file_size,status) VALUES (?,?,?,?,0,?,?,'quarantined')")->execute([
                $qFile, $result['filepath'], $result['hash'],
                $serverUuid, 'RCE: ' . implode(', ', $result['patterns_found']), $result['size'],
            ]);
        } catch (Exception $e) {
            @unlink($qFile);
            return false;
        }

        if (@unlink($result['filepath'])) {
            return true;
        }
        return 'quarantined_copy';
    }

    public function notifyTelegram($result) {
        $tg = $this->cfg['telegram'] ?? [];
        $token = $tg['token'] ?? '';
        $chatId = $tg['chat_id'] ?? '';
        if (!$token || !$chatId) return false;
        $msg = "🚨 RCE BLOCKED\nFile: " . basename($result['filepath']) . "\nServer: " . ($result['server_uuid'] ?? '?') . "\nPatterns: " . implode(', ', $result['patterns_found']) . "\nAction: Quarantined";
        @file_get_contents("https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chatId}&text=" . urlencode($msg) . "&parse_mode=Markdown");
        return true;
    }

    public function processFile($filepath) {
        if (!$this->isEnabled()) return ['status' => 'disabled'];
        $result = $this->scanFile($filepath);
        if (!$result) return ['status' => 'clean'];
        $this->logIllegal($result);
        $qResult = $this->quarantine($result);
        $this->notifyTelegram($result);
        return [
            'status' => $qResult === true ? 'quarantined' : $qResult,
            'result' => $result,
        ];
    }

    public function getRecentEvents($limit = 50) {
        if (!$this->db) return [];
        return $this->db->query("SELECT * FROM illegal_files ORDER BY first_seen DESC LIMIT $limit")->fetchAll();
    }

    public function getStats() {
        if (!$this->db) return ['total' => 0, 'today' => 0, 'unique_servers' => 0];
        $total = $this->db->query("SELECT COUNT(*) FROM illegal_files")->fetchColumn();
        $today = $this->db->query("SELECT COUNT(*) FROM illegal_files WHERE DATE(first_seen)=CURDATE()")->fetchColumn();
        $servers = $this->db->query("SELECT COUNT(DISTINCT server_uuid) FROM illegal_files")->fetchColumn();
        return ['total' => intval($total), 'today' => intval($today), 'unique_servers' => intval($servers)];
    }
}

// CLI mode
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $ctrl = new RCEController();
    switch ($argv[1]) {
        case 'scan':
            if (!isset($argv[2])) { echo "Usage: php rce_controller.php scan <filepath>\n"; exit(1); }
            $result = $ctrl->processFile($argv[2]);
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            exit($result['status'] === 'quarantined' ? 2 : ($result['status'] === 'clean' ? 0 : 1));
        case 'stats':
            echo json_encode($ctrl->getStats(), JSON_PRETTY_PRINT) . "\n";
            exit(0);
        case 'events':
            echo json_encode($ctrl->getRecentEvents(intval($argv[2] ?? 50)), JSON_PRETTY_PRINT) . "\n";
            exit(0);
        default:
            echo "Commands: scan <filepath>, stats, events [limit]\n";
            exit(1);
    }
}
