# DANN-GUARD

Pterodactyl panel guard, theme, and security suite — all-in-one.

## Features

- **Disk Protect** — monitor server volumes for flood, disk over-limit, and illegal files
- **RCE Scanner** — scan all server files and archives for malicious code patterns (eval, system, base64_decode, etc.)
- **Process Scanner** — detect and kill DDoS tools running inside containers
- **Anti DDoS** — detect outbound attack traffic and auto-suspend servers
- **Quarantine** — isolate malicious files to `/var/lib/pterodactyl/quarantine/`
- **Purple Theme** — Danex-inspired dark purple theme for panel client + admin
- **Dashboard Specs** — live CPU/RAM/disk usage bars with alarm thresholds
- **Telegram Alerts** — real-time notifications for all security events

## Quick Install

```bash
git clone https://github.com/DANN-Network/dann_guard.git
cd dann_guard
chmod +x install.sh
./install.sh
```

Then edit `/root/dann_guard/config.json` and set your Telegram credentials.

## Config

| Section | Description |
|---------|-------------|
| `database` | Panel MySQL connection (auto-detected) |
| `telegram` | Bot token, chat ID, channel (edit manually) |
| `paths.volumes` | Server data directory (auto-detected) |
| `limits` | Disk/rate/flood thresholds |
| `rce` | Malicious code patterns to scan |
| `quarantine` | Quarantine path and auto-clean days |
| `process_scan` | DDoS process detection keywords |
| `anti_ddos` | Outbound connection limits |

## Manual Commands

```bash
# Run RCE scan manually
php /root/dann_guard/scan_rce.php

# Tail guard logs
journalctl -u dann_guard -f

# Restart guard
systemctl restart dann_guard
```

## Requirements

- Pterodactyl Panel 1.x
- Wings 1.x
- PHP 8.x
- MySQL/MariaDB
- Nginx
