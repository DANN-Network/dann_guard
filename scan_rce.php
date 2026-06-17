<?php
$cfg = @json_decode(@file_get_contents('/root/dann_guard/config.json'), true);
if (!$cfg) { echo "No config\n"; exit(1); }

$rce = $cfg['rce'] ?? [];
if (!($rce['enabled'] ?? false)) { echo "RCE scan disabled\n"; exit(0); }

$patterns = $rce['patterns'] ?? [];
$extensions = $rce['extensions'] ?? ['php', 'php5', 'phtml', 'php7', 'php8'];
$volumes = $cfg['paths']['volumes'] ?? '/var/lib/pterodactyl/volumes';
$quarantine_path = $cfg['quarantine']['path'] ?? '/var/lib/pterodactyl/quarantine';

$db_host = $cfg['database']['host'] ?? '127.0.0.1';
$db_user = $cfg['database']['user'] ?? 'pterodactyl';
$db_pass = $cfg['database']['password'] ?? '';
$db_name = $cfg['database']['name'] ?? 'panel';

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Exception $e) {
    echo "DB connection failed\n";
    exit(1);
}

if (!is_dir($quarantine_path)) mkdir($quarantine_path, 755, true);

$found = 0;
$ext_map = [];
foreach ($extensions as $ext) $ext_map['.' . trim($ext, '.')] = true;
$archive_map = ['.zip' => true, '.tar' => true, '.gz' => true, '.rar' => true, '.tar.gz' => true, '.tgz' => true];

$dirs = glob($volumes . '/*');
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $server_uuid = basename($dir);
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $pathname = $file->getPathname();
        $ext = '.' . strtolower(pathinfo($pathname, PATHINFO_EXTENSION));
        
        // Handle .tar.gz double extension
        $basename = $file->getFilename();
        $double_ext = '';
        if (str_ends_with(strtolower($basename), '.tar.gz')) $double_ext = '.tar.gz';
        elseif (str_ends_with(strtolower($basename), '.tgz')) $double_ext = '.tgz';
        
        // SCAN ARCHIVES (ZIP, TAR, etc.)
        if (isset($archive_map[$ext]) || $double_ext) {
            $zip_content = null;
            $zip_files = [];
            
            if (class_exists('ZipArchive') && ($ext === '.zip' || strtolower(pathinfo($basename, PATHINFO_EXTENSION)) === 'zip')) {
                $za = new ZipArchive();
                if ($za->open($pathname) === true) {
                    for ($i = 0; $i < $za->numFiles; $i++) {
                        $zname = $za->getNameIndex($i);
                        $zlower = strtolower($zname);
                        
                        // Check file name in archive for malicious keywords
                        $malicious_keywords = ['ddos', 'flood', 'attack', 'stresser', 'hitme', 
                            'child_process', 'backdoor', 'reverse', 'shell', 'payload',
                            'xmrig', 'minerd', 'goldeneye', 'slowloris', 'hulk',
                            'synflood', 'udpflood', 'httpflood', 'torshammer'];
                        $is_malicious = false;
                        foreach ($malicious_keywords as $mk) {
                            if (str_contains($zlower, $mk)) { $is_malicious = true; break; }
                        }
                        
                        if ($is_malicious) {
                            $zip_files[] = ['name' => $zname, 'reason' => "filename: $mk"];
                            break;
                        }
                        
                        // Check extension
                        $zpos = strrpos($zname, '.');
                        if ($zpos !== false) {
                            $zext = substr($zname, $zpos);
                            if ($zext === '.php' || $zext === '.cpp' || $zext === '.c' || 
                                $zext === '.h' || $zext === '.hpp' || $zext === '.py' ||
                                $zext === '.pl' || $zext === '.rb' || $zext === '.sh' ||
                                $zext === '.bash' || $zext === '.js') {
                                
                                $zcontent = $za->getFromIndex($i);
                                if ($zcontent !== false) {
                                    foreach ($patterns as $pattern) {
                                        if (stripos($zcontent, $pattern) !== false) {
                                            $zip_files[] = ['name' => $zname, 'reason' => "RCE: $pattern"];
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $za->close();
                }
            } else {
                // Fallback: use unzip -l for ZIP files
                $cmd = "unzip -l " . escapeshellarg($pathname) . " 2>/dev/null";
                $output = shell_exec($cmd);
                if ($output) {
                    $lines = explode("\n", $output);
                    foreach ($lines as $line) {
                        $parts = preg_split('/\s+/', trim($line));
                        if (count($parts) >= 4 && is_numeric($parts[0])) {
                            $zname = end($parts);
                            $zlower = strtolower($zname);
                            foreach (['ddos', 'flood', 'attack', 'stresser', 'hitme', 'child_process', 'backdoor', 'xmrig'] as $mk) {
                                if (str_contains($zlower, $mk)) {
                                    $zip_files[] = ['name' => $zname, 'reason' => "filename: $mk"];
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
            
            if (!empty($zip_files)) {
                $hash = md5_file($pathname);
                $reason = "Archive contains: " . $zip_files[0]['reason'];
                $st = $db->prepare("SELECT id FROM illegal_files WHERE file_hash=? AND server_uuid=?");
                $st->execute([$hash, $server_uuid]);
                if (!$st->fetch()) {
                    $db->prepare("INSERT INTO illegal_files (file_hash,file_name,file_path,server_uuid,user_id,detection_reason,file_size) VALUES (?,?,?,?,0,?,?)")->execute([
                        $hash, $basename, $pathname, $server_uuid, $reason, $file->getSize()
                    ]);
                    echo "Archive malicious: $pathname ($reason)\n";
                }
                // Move to quarantine
                $qdir = "$quarantine_path/$server_uuid";
                if (!is_dir($qdir)) mkdir($qdir, 755, true);
                $qfile = "$qdir/" . $basename . '.' . time();
                if (copy($pathname, $qfile)) {
                    $db->prepare("INSERT IGNORE INTO quarantine (file_path,original_path,file_hash,server_uuid,user_id,reason,file_size) VALUES (?,?,?,?,0,?,?)")->execute([
                        $qfile, $pathname, $hash, $server_uuid, $reason, $file->getSize()
                    ]);
                    unlink($pathname);
                    echo "Quarantined archive: $pathname\n";
                }
                $found++;
            }
            continue;
        }
        
        // SCAN REGULAR FILES
        if (!isset($ext_map[$ext])) continue;
        $content = @file_get_contents($pathname);
        if ($content === false) continue;
        foreach ($patterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $hash = md5_file($pathname);
                $st = $db->prepare("SELECT id FROM illegal_files WHERE file_hash=? AND server_uuid=?");
                $st->execute([$hash, $server_uuid]);
                if (!$st->fetch()) {
                    $db->prepare("INSERT INTO illegal_files (file_hash,file_name,file_path,server_uuid,user_id,detection_reason,file_size) VALUES (?,?,?,?,0,?,?)")->execute([
                        $hash,
                        $basename,
                        $pathname,
                        $server_uuid,
                        "RCE: $pattern",
                        $file->getSize(),
                    ]);
                    echo "RCE detected: $pathname ($pattern)\n";
                }
                // Move to quarantine
                $qdir = "$quarantine_path/$server_uuid";
                if (!is_dir($qdir)) mkdir($qdir, 755, true);
                $qfile = "$qdir/" . $basename . '.' . time();
                if (copy($pathname, $qfile)) {
                    $db->prepare("INSERT IGNORE INTO quarantine (file_path,original_path,file_hash,server_uuid,user_id,reason,file_size) VALUES (?,?,?,?,0,?,?)")->execute([
                        $qfile,
                        $pathname,
                        $hash,
                        $server_uuid,
                        "RCE: $pattern",
                        $file->getSize(),
                    ]);
                    unlink($pathname);
                    echo "Quarantined: $pathname\n";
                }
                $found++;
                break;
            }
        }
    }
}

echo "Scan complete. $found items found.\n";