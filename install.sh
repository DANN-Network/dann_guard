#!/bin/bash
set -e

# ============================================================
# DANN-GUARD Installer v2.0 — Auto-detect & deploy everything
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'

info()  { echo -e "${CYAN}[INFO]${NC}  $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}    $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $1"; }
err()   { echo -e "${RED}[ERR]${NC}   $1"; }

backup_file() {
    local f="$1"
    if [ -f "$f" ] && [ ! -f "${f}.bak" ]; then
        cp "$f" "${f}.bak"
    fi
}

# --- AUTO-DETECT ---
detect_panel_dir() {
    for d in /var/www/pterodactyl /var/www/panel /var/www/html /srv/pterodactyl; do
        if [ -f "$d/.env" ] && [ -f "$d/artisan" ]; then
            PANEL_DIR="$d"
            return 0
        fi
    done
    err "Pterodactyl panel not found! Specify manually."
    exit 1
}

detect_php_version() {
    PHP_VER=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;" 2>/dev/null || true)
    if [ -z "$PHP_VER" ]; then
        PHP_VER=$(ls /etc/php/ 2>/dev/null | grep -oP '^\d+\.\d+' | head -1)
    fi
    if [ -z "$PHP_VER" ]; then PHP_VER="8.3"; fi
}

detect_wings_data() {
    WINGS_DATA="/var/lib/pterodactyl"
    if [ -f /etc/pterodactyl/config.yml ]; then
        local d=$(grep -oP '^\s*data:\s*\K.*' /etc/pterodactyl/config.yml | head -1 | tr -d ' ')
        [ -n "$d" ] && WINGS_DATA="$d"
    fi
}

detect_node_db_config() {
    if [ -f "$PANEL_DIR/.env" ]; then
        DB_HOST=$(grep -oP 'DB_HOST=\K.*' "$PANEL_DIR/.env" | head -1 | tr -d '"')
        DB_USER=$(grep -oP 'DB_USERNAME=\K.*' "$PANEL_DIR/.env" | head -1 | tr -d '"')
        DB_PASS=$(grep -oP 'DB_PASSWORD=\K.*' "$PANEL_DIR/.env" | head -1 | tr -d '"')
        DB_NAME=$(grep -oP 'DB_DATABASE=\K.*' "$PANEL_DIR/.env" | head -1 | tr -d '"')
        [ -z "$DB_HOST" ] && DB_HOST="127.0.0.1"
        [ -z "$DB_USER" ] && DB_USER="pterodactyl"
        [ -z "$DB_NAME" ] && DB_NAME="panel"
    fi
}

detect_volumes() {
    VOLUMES_DIR="${WINGS_DATA}/volumes"
    if [ ! -d "$VOLUMES_DIR" ]; then
        VOLUMES_DIR="${WINGS_DATA}"
    fi
}

detect_challenge_dir() {
    if [ -d /var/www/challenge ]; then
        CHALLENGE_DIR=/var/www/challenge
    elif [ -d "$PANEL_DIR/../challenge" ]; then
        CHALLENGE_DIR="$PANEL_DIR/../challenge"
    else
        CHALLENGE_DIR=/var/www/challenge
    fi
}

detect_mysql_root() {
    # Try common ways to run MySQL commands as root
    if mysql -u root -e "SELECT 1" &>/dev/null; then
        MYSQL_CMD="mysql -u root"
    elif mysql -u root -proot -e "SELECT 1" &>/dev/null; then
        MYSQL_CMD="mysql -u root -proot"
    else
        MYSQL_CMD=""
    fi
}

# --- INSTALL FUNCTIONS ---
install_binary() {
    if [ -f "$SCRIPT_DIR/dann_guard" ]; then
        cp "$SCRIPT_DIR/dann_guard" /usr/local/bin/dann_guard
        chmod +x /usr/local/bin/dann_guard
        ok "dann_guard binary installed to /usr/local/bin"
    fi
}

install_systemd() {
    if [ -f "$SCRIPT_DIR/systemd/dann_guard.service" ]; then
        cp "$SCRIPT_DIR/systemd/dann_guard.service" /etc/systemd/system/dann_guard.service
        systemctl daemon-reload
        ok "systemd service installed"
    fi
}

install_config() {
    local cfg_path="${1:-/root/dann_guard/config.json}"
    if [ -f "$cfg_path" ]; then
        warn "Config already exists at $cfg_path — skipping"
        return
    fi
    mkdir -p /root/dann_guard
    sed \
        -e "s|\"host\": \"127.0.0.1\"|\"host\": \"$DB_HOST\"|" \
        -e "s|\"user\": \"pterodactyl\"|\"user\": \"$DB_USER\"|" \
        -e "s|\"password\": \"\"|\"password\": \"$DB_PASS\"|" \
        -e "s|\"name\": \"panel\"|\"name\": \"$DB_NAME\"|" \
        -e "s|\"/var/lib/pterodactyl/volumes\"|\"$VOLUMES_DIR\"|" \
        -e "s|\"php_version\": \"8.3\"|\"php_version\": \"$PHP_VER\"|" \
        "$SCRIPT_DIR/config.example.json" > "$cfg_path"
    chmod 600 "$cfg_path"
    ok "Config created at $cfg_path — edit Telegram credentials manually"
}

install_php_scripts() {
    mkdir -p /root/dann_guard
    [ -f "$SCRIPT_DIR/scan_rce.php" ] && cp "$SCRIPT_DIR/scan_rce.php" /root/dann_guard/scan_rce.php
    [ -f "$SCRIPT_DIR/rce_controller.php" ] && cp "$SCRIPT_DIR/rce_controller.php" /root/dann_guard/rce_controller.php
    [ -f "$SCRIPT_DIR/rce_watch.sh" ] && cp "$SCRIPT_DIR/rce_watch.sh" /root/dann_guard/rce_watch.sh && chmod +x /root/dann_guard/rce_watch.sh
    ok "PHP scripts installed"
}

install_challenge() {
    mkdir -p "$CHALLENGE_DIR"
    [ -f "$SCRIPT_DIR/challenge/protect.php" ] && cp "$SCRIPT_DIR/challenge/protect.php" "$CHALLENGE_DIR/protect.php"
    [ -f "$SCRIPT_DIR/challenge/index.html" ] && cp "$SCRIPT_DIR/challenge/index.html" "$CHALLENGE_DIR/index.html"
    ok "Challenge pages installed"
}

install_nginx_config() {
    # Main site config
    if [ -f "$SCRIPT_DIR/nginx/pterodactyl" ]; then
        if [ -f /etc/nginx/sites-enabled/pterodactyl ]; then
            backup_file /etc/nginx/sites-enabled/pterodactyl
            cp "$SCRIPT_DIR/nginx/pterodactyl" /etc/nginx/sites-enabled/pterodactyl
        elif [ -f /etc/nginx/conf.d/pterodactyl.conf ]; then
            backup_file /etc/nginx/conf.d/pterodactyl.conf
            cp "$SCRIPT_DIR/nginx/pterodactyl" /etc/nginx/conf.d/pterodactyl.conf
        else
            cp "$SCRIPT_DIR/nginx/pterodactyl" /etc/nginx/sites-enabled/pterodactyl 2>/dev/null || \
            warn "Could not install nginx config — check manually"
        fi
        ok "Nginx site config applied"
    fi

    # Anti-DDoS map
    if [ -f "$SCRIPT_DIR/nginx/anti-ddos-map.conf" ]; then
        mkdir -p /etc/nginx/dann
        cp "$SCRIPT_DIR/nginx/anti-ddos-map.conf" /etc/nginx/dann/anti-ddos-map.conf
        ok "Anti-DDoS nginx map installed"
    fi

    nginx -t 2>/dev/null && systemctl reload nginx && ok "Nginx reloaded" || warn "Nginx config has errors — check manually"
}

install_panel_theme() {
    [ -z "$PANEL_DIR" ] && return

    # Admin layout
    [ -f "$SCRIPT_DIR/panel/admin.blade.php" ] && \
        cp "$SCRIPT_DIR/panel/admin.blade.php" "$PANEL_DIR/resources/views/layouts/admin.blade.php" && \
        ok "admin.blade.php applied"

    # CSS
    mkdir -p "$PANEL_DIR/public/themes/pterodactyl/css"
    [ -f "$SCRIPT_DIR/panel/pterodactyl.css" ] && \
        cp "$SCRIPT_DIR/panel/pterodactyl.css" "$PANEL_DIR/public/themes/pterodactyl/css/pterodactyl.css" && \
        ok "pterodactyl.css applied"

    # Tailwind config
    if [ -f "$SCRIPT_DIR/panel/tailwind.config.js" ]; then
        backup_file "$PANEL_DIR/tailwind.config.js"
        cp "$SCRIPT_DIR/panel/tailwind.config.js" "$PANEL_DIR/tailwind.config.js"
        ok "tailwind.config.js applied"
    fi

    # GlobalStylesheet
    mkdir -p "$PANEL_DIR/resources/scripts/assets/css"
    [ -f "$SCRIPT_DIR/panel/GlobalStylesheet.ts" ] && \
        cp "$SCRIPT_DIR/panel/GlobalStylesheet.ts" "$PANEL_DIR/resources/scripts/assets/css/GlobalStylesheet.ts" && \
        ok "GlobalStylesheet.ts applied"

    # Dashboard React components
    local dash_dir="$PANEL_DIR/resources/scripts/components/dashboard"
    [ -f "$SCRIPT_DIR/panel/ServerRow.tsx" ] && cp "$SCRIPT_DIR/panel/ServerRow.tsx" "$dash_dir/ServerRow.tsx"
    [ -f "$SCRIPT_DIR/panel/DashboardContainer.tsx" ] && cp "$SCRIPT_DIR/panel/DashboardContainer.tsx" "$dash_dir/DashboardContainer.tsx"
    [ -f "$SCRIPT_DIR/panel/MiniGamesPage.tsx" ] && cp "$SCRIPT_DIR/panel/MiniGamesPage.tsx" "$dash_dir/MiniGamesPage.tsx"
    ok "Dashboard components updated"

    # Admin server/user views
    [ -f "$SCRIPT_DIR/panel/admin_servers_index.blade.php" ] && \
        cp "$SCRIPT_DIR/panel/admin_servers_index.blade.php" "$PANEL_DIR/resources/views/admin/servers/index.blade.php"
    [ -f "$SCRIPT_DIR/panel/admin_servers_navigation.blade.php" ] && \
        cp "$SCRIPT_DIR/panel/admin_servers_navigation.blade.php" "$PANEL_DIR/resources/views/admin/servers/partials/navigation.blade.php"
    [ -f "$SCRIPT_DIR/panel/admin_users_view.blade.php" ] && \
        cp "$SCRIPT_DIR/panel/admin_users_view.blade.php" "$PANEL_DIR/resources/views/admin/users/view.blade.php"
    ok "Admin server/user views updated"

    # NavigationBar + DashboardRouter (frontend nav + routing)
    local comp_dir="$PANEL_DIR/resources/scripts/components"
    [ -f "$SCRIPT_DIR/panel/components/NavigationBar.tsx" ] && \
        cp "$SCRIPT_DIR/panel/components/NavigationBar.tsx" "$comp_dir/NavigationBar.tsx" && \
        ok "NavigationBar.tsx applied (mini games nav)"

    local router_dir="$PANEL_DIR/resources/scripts/routers"
    [ -f "$SCRIPT_DIR/panel/routers/DashboardRouter.tsx" ] && \
        cp "$SCRIPT_DIR/panel/routers/DashboardRouter.tsx" "$router_dir/DashboardRouter.tsx" && \
        ok "DashboardRouter.tsx applied (mini games route)"
}

install_middleware() {
    [ -z "$PANEL_DIR" ] && return

    # AdminAuthenticate
    [ -f "$SCRIPT_DIR/app/Http/Middleware/AdminAuthenticate.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Middleware/AdminAuthenticate.php" "$PANEL_DIR/app/Http/Middleware/AdminAuthenticate.php" && \
        ok "AdminAuthenticate.php applied"

    # AdminRestriction
    mkdir -p "$PANEL_DIR/app/Http/Middleware/Admin"
    [ -f "$SCRIPT_DIR/app/Http/Middleware/Admin/AdminRestriction.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Middleware/Admin/AdminRestriction.php" "$PANEL_DIR/app/Http/Middleware/Admin/AdminRestriction.php" && \
        ok "AdminRestriction.php applied"

    # API middlewares
    [ -f "$SCRIPT_DIR/app/Http/Middleware/Api/Application/AuthenticateApplicationUser.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Middleware/Api/Application/AuthenticateApplicationUser.php" \
           "$PANEL_DIR/app/Http/Middleware/Api/Application/AuthenticateApplicationUser.php" && \
        ok "API Application middleware applied"

    [ -f "$SCRIPT_DIR/app/Http/Middleware/Api/Client/Server/AuthenticateServerAccess.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Middleware/Api/Client/Server/AuthenticateServerAccess.php" \
           "$PANEL_DIR/app/Http/Middleware/Api/Client/Server/AuthenticateServerAccess.php" && \
        ok "API Client/Server middleware applied"

    # Kernel
    [ -f "$SCRIPT_DIR/app/Http/Kernel.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Kernel.php" "$PANEL_DIR/app/Http/Kernel.php" && \
        ok "Kernel.php applied (admin.restrict alias)"

    # RouteServiceProvider
    [ -f "$SCRIPT_DIR/app/Providers/RouteServiceProvider.php" ] && \
        cp "$SCRIPT_DIR/app/Providers/RouteServiceProvider.php" "$PANEL_DIR/app/Providers/RouteServiceProvider.php" && \
        ok "RouteServiceProvider.php applied (admin.restrict on admin routes)"

    # VerifyCsrfToken (exclude protect page from CSRF)
    [ -f "$SCRIPT_DIR/app/Http/Middleware/VerifyCsrfToken.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Middleware/VerifyCsrfToken.php" "$PANEL_DIR/app/Http/Middleware/VerifyCsrfToken.php" && \
        ok "VerifyCsrfToken.php applied (protect route excluded)"
}

install_controllers() {
    [ -z "$PANEL_DIR" ] && return

    # ProtectController
    mkdir -p "$PANEL_DIR/app/Http/Controllers/Admin"
    [ -f "$SCRIPT_DIR/app/Http/Controllers/Admin/ProtectController.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Controllers/Admin/ProtectController.php" "$PANEL_DIR/app/Http/Controllers/Admin/ProtectController.php" && \
        ok "ProtectController.php applied"

    # Patched controllers with restricted_admin checks
    [ -f "$SCRIPT_DIR/app/Http/Controllers/Admin/UserController.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Controllers/Admin/UserController.php" "$PANEL_DIR/app/Http/Controllers/Admin/UserController.php" && \
        ok "UserController.php applied (restricted_admin)"

    [ -f "$SCRIPT_DIR/app/Http/Controllers/Admin/ServersController.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Controllers/Admin/ServersController.php" "$PANEL_DIR/app/Http/Controllers/Admin/ServersController.php" && \
        ok "ServersController.php applied (restricted_admin)"

    [ -f "$SCRIPT_DIR/app/Http/Controllers/Admin/Servers/ServerController.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Controllers/Admin/Servers/ServerController.php" "$PANEL_DIR/app/Http/Controllers/Admin/Servers/ServerController.php" && \
        ok "ServerController.php applied (restricted_admin)"

    [ -f "$SCRIPT_DIR/app/Http/Controllers/Admin/Servers/ServerViewController.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Controllers/Admin/Servers/ServerViewController.php" "$PANEL_DIR/app/Http/Controllers/Admin/Servers/ServerViewController.php" && \
        ok "ServerViewController.php applied (restricted_admin)"

    mkdir -p "$PANEL_DIR/app/Http/Controllers/Admin/Nodes"
    [ -f "$SCRIPT_DIR/app/Http/Controllers/Admin/Nodes/NodeController.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Controllers/Admin/Nodes/NodeController.php" "$PANEL_DIR/app/Http/Controllers/Admin/Nodes/NodeController.php" && \
        ok "NodeController.php applied"

    mkdir -p "$PANEL_DIR/app/Http/Controllers/Admin/Nests"
    [ -f "$SCRIPT_DIR/app/Http/Controllers/Admin/Nests/NestController.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Controllers/Admin/Nests/NestController.php" "$PANEL_DIR/app/Http/Controllers/Admin/Nests/NestController.php" && \
        ok "NestController.php applied"

    [ -f "$SCRIPT_DIR/app/Http/Controllers/Admin/LocationController.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Controllers/Admin/LocationController.php" "$PANEL_DIR/app/Http/Controllers/Admin/LocationController.php" && \
        ok "LocationController.php applied"

    mkdir -p "$PANEL_DIR/app/Http/Controllers/Admin/Settings"
    [ -f "$SCRIPT_DIR/app/Http/Controllers/Admin/Settings/IndexController.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Controllers/Admin/Settings/IndexController.php" "$PANEL_DIR/app/Http/Controllers/Admin/Settings/IndexController.php" && \
        ok "Settings/IndexController.php applied"

    [ -f "$SCRIPT_DIR/app/Http/Controllers/Admin/NodesController.php" ] && \
        cp "$SCRIPT_DIR/app/Http/Controllers/Admin/NodesController.php" "$PANEL_DIR/app/Http/Controllers/Admin/NodesController.php" && \
        ok "NodesController.php applied"
}

install_routes() {
    [ -z "$PANEL_DIR" ] && return
    [ -f "$SCRIPT_DIR/routes/admin.php" ] && \
        cp "$SCRIPT_DIR/routes/admin.php" "$PANEL_DIR/routes/admin.php" && \
        ok "Routes applied (protect route)"
}

run_migration() {
    if [ -z "$MYSQL_CMD" ]; then
        warn "Cannot run DB migration — no MySQL root access. Run manually:"
        echo "  mysql -u root $DB_NAME < $SCRIPT_DIR/db/migration.sql"
        return
    fi
    if [ -f "$SCRIPT_DIR/db/migration.sql" ]; then
        # Check if columns already exist
        local has_restricted=$($MYSQL_CMD -N -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='users' AND COLUMN_NAME='restricted_admin'" 2>/dev/null)
        if [ "$has_restricted" = "0" ]; then
            $MYSQL_CMD "$DB_NAME" < "$SCRIPT_DIR/db/migration.sql" 2>/dev/null && ok "Database migration applied" || warn "DB migration failed"
        else
            ok "Database columns already exist"
        fi
    fi
}

install_uno() {
    local uno_dir="$PANEL_DIR/public/minigames/uno"
    if [ -f "$SCRIPT_DIR/minigames/uno/index.php" ]; then
        rm -rf "$uno_dir"
        mkdir -p "$PANEL_DIR/public/minigames"
        cp -r "$SCRIPT_DIR/minigames/uno" "$PANEL_DIR/public/minigames/uno"
        chmod -R 755 "$uno_dir"
        ok "UNO game installed ($(find "$uno_dir" -type f | wc -l) files)"

        # Ensure config symlink for UNO database credentials
        if [ -f "$CHALLENGE_DIR/config.json" ] && [ ! -L /pteroprotect/config.json ]; then
            ln -sf "$CHALLENGE_DIR/config.json" /pteroprotect/config.json 2>/dev/null || \
            mkdir -p /pteroprotect && cp "$CHALLENGE_DIR/config.json" /pteroprotect/config.json 2>/dev/null || true
        fi
    fi
}

install_quarantine() {
    local qdir
    if [ "$VOLUMES_DIR" = "/var/lib/pterodactyl/volumes" ]; then
        qdir="/var/lib/pterodactyl/quarantine"
    else
        qdir="${VOLUMES_DIR}/../quarantine"
    fi
    mkdir -p "$qdir"
    chmod 755 "$qdir"
    ok "Quarantine directory ready at $qdir"
}

rebuild_frontend() {
    [ -z "$PANEL_DIR" ] && return
    if [ -f "$PANEL_DIR/package.json" ] && command -v npx &>/dev/null; then
        info "Rebuilding frontend (this may take a while)..."
        cd "$PANEL_DIR"
        npx webpack --mode production 2>&1 | tail -5
        ok "Frontend rebuilt"
    else
        warn "Cannot rebuild frontend — do it manually: cd $PANEL_DIR && npx webpack --mode production"
    fi
}

restart_services() {
    systemctl daemon-reload 2>/dev/null
    if [ -f /usr/local/bin/dann_guard ] || [ -f /root/dann_guard/dann_guard ]; then
        systemctl enable dann_guard 2>/dev/null && systemctl restart dann_guard 2>/dev/null && ok "dann_guard restarted" || warn "dann_guard service not started"
    fi
    systemctl restart php${PHP_VER}-fpm 2>/dev/null || systemctl restart php${PHP_VER}-fpm 2>/dev/null || true
    systemctl reload nginx 2>/dev/null || true
    ok "Services restarted"
}

# --- MAIN ---
echo -e "${CYAN}"
echo "  ╔══════════════════════════════════════════════╗"
echo "  ║         DANN-GUARD INSTALLER v2.0            ║"
echo "  ║  Guard + Theme + Admin Restrict + Protect    ║"
echo "  ╚══════════════════════════════════════════════╝"
echo -e "${NC}"

# Auto-detect everything
detect_panel_dir
detect_php_version
detect_wings_data
detect_node_db_config
detect_volumes
detect_challenge_dir
detect_mysql_root

echo ""
echo -e "  ${CYAN}Panel:${NC}       $PANEL_DIR"
echo -e "  ${CYAN}PHP:${NC}         $PHP_VER"
echo -e "  ${CYAN}Wings data:${NC}  $WINGS_DATA"
echo -e "  ${CYAN}Volumes:${NC}     $VOLUMES_DIR"
echo -e "  ${CYAN}Challenge:${NC}   $CHALLENGE_DIR"
echo -e "  ${CYAN}DB:${NC}          $DB_USER@$DB_HOST/$DB_NAME"
echo ""

# === GUARD ===
echo -e "${YELLOW}─── Guard ───${NC}"
install_binary
install_systemd
install_config "/root/dann_guard/config.json"
install_php_scripts

# === CHALLENGE ===
echo -e "${YELLOW}─── Challenge / Protect ───${NC}"
install_challenge

# === NGINX ===
echo -e "${YELLOW}─── Nginx ───${NC}"
install_nginx_config

# === PANEL THEME ===
echo -e "${YELLOW}─── Panel Theme ───${NC}"
install_panel_theme

# === MIDDLEWARE (admin restrict) ===
echo -e "${YELLOW}─── Middleware (Admin Restrict) ───${NC}"
install_middleware

# === CONTROLLERS ===
echo -e "${YELLOW}─── Controllers ───${NC}"
install_controllers

# === ROUTES ===
echo -e "${YELLOW}─── Routes ───${NC}"
install_routes

# === DATABASE ===
echo -e "${YELLOW}─── Database ───${NC}"
run_migration

# === MINIGAMES (UNO) ===
echo -e "${YELLOW}─── Mini Games (UNO) ───${NC}"
install_uno

# === QUARANTINE ===
echo -e "${YELLOW}─── Quarantine ───${NC}"
install_quarantine

# === BUILD ===
echo -e "${YELLOW}─── Build ───${NC}"
rebuild_frontend
restart_services

# === DONE ===
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  DANN-GUARD INSTALLED SUCCESSFULLY${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "  ${YELLOW}IMPORTANT:${NC} Edit config first:"
echo -e "  nano /root/dann_guard/config.json"
echo -e "  → Set telegram.token, telegram.chat_id, etc."
echo ""
echo -e "  ${YELLOW}Start guard:${NC}"
echo -e "  systemctl start dann_guard"
echo ""
echo -e "  ${YELLOW}Manual scan:${NC}"
echo -e "  php /root/dann_guard/scan_rce.php"
echo ""
echo -e "  ${YELLOW}Admin restrict check:${NC}"
echo -e "  Check Panel → Admin → Users → Edit user ➔ set Restricted Admin"
echo ""
