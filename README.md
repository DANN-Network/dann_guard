# DANN GUARD Professional

Advanced protection system for Pterodactyl Panel with DDoS challenge, RCE scanning, process monitoring, and violation tracking.

## Components

- **src/** - C++ source code (disk protector, process scanner, Telegram bot, database tracker)
- **include/** - C++ headers
- **index.html** - DDoS challenge page with animated security check
- **protect.php** - Admin protection dashboard with real-time notifications
- **config.json** - System configuration
- **rce_watch.sh** - Real-time file monitoring via inotify
- **rce_controller.php** - RCE scan engine
- **scan_rce.php** - CLI batch scanner

## Features

- DDoS challenge & rate limiting (Nginx)
- Real-time file monitoring (inotify + PHP)
- RCE pattern scanning (PHP, C, C++, Python, etc.)
- Process scanner (detect DDoS tools)
- ZIP archive scanning
- Local DDoS detection (outbound connection monitoring)
- Database violation tracking
- Telegram notifications
- Admin protect dashboard
