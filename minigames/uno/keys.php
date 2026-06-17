<?php
$configPath = '/pteroprotect/config.json';
$config = json_decode(file_get_contents($configPath), true);
$database = $config['minigames']['uno']['database'] ?? [];

$serverIp = $database['host'] ?? '127.0.0.1';
$dbName = $database['name'] ?? 'uno_online';
$username = $database['user'] ?? 'game';
$pass = $database['password'] ?? '';
?>
