#!/bin/bash
set -e

# ============================================================
# DANN-GUARD Installer — Auto-detect & deploy everything
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'

info()  { echo -e "${CYAN}[INFO]${NC}  $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}    $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $1"; }
err()   { echo -e "${RED}[ERR]${NC}   $1"; }

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
    if [ -f /etc/pterodactyl/config.yml ]; then
        WINGS_DATA=$(grep -oP '^\s*data:\s*\K.*' /etc/pterodactyl/config.yml | head -1 | tr -d ' ')
    fi
    if [ -z "$WINGS_DATA" ]; then
        WINGS_DATA="/var/lib/pterodactyl"
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
    # Use wings data + volumes or fallback
    VOLUMES_DIR="${WINGS_DATA}/volumes"
    if [ ! -d "$VOLUMES_DIR" ]; then
        VOLUMES_DIR="${WINGS_DATA}"
    fi
}

# --- INSTALL ---
install_binary() {
    if [ -f "$SCRIPT_DIR/dann_guard" ]; then
        cp "$SCRIPT_DIR/dann_guard" /usr/local/bin/dann_guard
        chmod +x /usr/local/bin/dann_guard
        ok "dann_guard binary installed"
    fi
}

install_systemd() {
    mkdir -p /etc/dann_guard
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
    local challenge_dir
    if [ -d /var/www/challenge ]; then
        challenge_dir=/var/www/challenge
    elif [ -d "$PANEL_DIR/../challenge" ]; then
        challenge_dir="$PANEL_DIR/../challenge"
    else
        return
    fi
    [ -f "$SCRIPT_DIR/challenge/protect.php" ] && cp "$SCRIPT_DIR/challenge/protect.php" "$challenge_dir/protect.php"
    ok "Challenge protect page installed"
}

install_nginx_config() {
    if [ -f "$SCRIPT_DIR/nginx/pterodactyl" ]; then
        cp "$SCRIPT_DIR/nginx/pterodactyl" /etc/nginx/sites-enabled/pterodactyl 2>/dev/null || \
        cp "$SCRIPT_DIR/nginx/pterodactyl" /etc/nginx/conf.d/pterodactyl.conf 2>/dev/null || \
        warn "Could not install nginx config — check manually"
        nginx -t 2>/dev/null && systemctl reload nginx && ok "Nginx config applied" || warn "Nginx config has errors"
    fi
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
        local tw="$PANEL_DIR/tailwind.config.js"
        if [ -f "$tw" ]; then
            cp "$tw" "${tw}.bak"
        fi
        cp "$SCRIPT_DIR/panel/tailwind.config.js" "$tw"
        ok "tailwind.config.js applied"
    fi

    # GlobalStylesheet
    mkdir -p "$PANEL_DIR/resources/scripts/assets/css"
    [ -f "$SCRIPT_DIR/panel/GlobalStylesheet.ts" ] && \
        cp "$SCRIPT_DIR/panel/GlobalStylesheet.ts" "$PANEL_DIR/resources/scripts/assets/css/GlobalStylesheet.ts" && \
        ok "GlobalStylesheet.ts applied"

    # Dashboard components
    local dash_dir="$PANEL_DIR/resources/scripts/components/dashboard"
    [ -f "$SCRIPT_DIR/panel/ServerRow.tsx" ] && cp "$SCRIPT_DIR/panel/ServerRow.tsx" "$dash_dir/ServerRow.tsx"
    [ -f "$SCRIPT_DIR/panel/DashboardContainer.tsx" ] && cp "$SCRIPT_DIR/panel/DashboardContainer.tsx" "$dash_dir/DashboardContainer.tsx"
    [ -f "$SCRIPT_DIR/panel/MiniGamesPage.tsx" ] && cp "$SCRIPT_DIR/panel/MiniGamesPage.tsx" "$dash_dir/MiniGamesPage.tsx"
    ok "Dashboard components updated"

    # Admin server views
    local admin_views="$PANEL_DIR/resources/views/admin/servers"
    [ -f "$SCRIPT_DIR/panel/admin_servers_index.blade.php" ] && \
        cp "$SCRIPT_DIR/panel/admin_servers_index.blade.php" "$admin_views/index.blade.php"
    [ -f "$SCRIPT_DIR/panel/admin_servers_navigation.blade.php" ] && \
        cp "$SCRIPT_DIR/panel/admin_servers_navigation.blade.php" "$admin_views/partials/navigation.blade.php"
    [ -f "$SCRIPT_DIR/panel/admin_users_view.blade.php" ] && \
        cp "$SCRIPT_DIR/panel/admin_users_view.blade.php" "$PANEL_DIR/resources/views/admin/users/view.blade.php"
    ok "Admin server/user views updated"
}

install_quarantine() {
    local qdir="${VOLUMES_DIR}/../quarantine"
    [ "$qdir" = "/../quarantine" ] && qdir="/var/lib/pterodactyl/quarantine"
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
    systemctl enable dann_guard 2>/dev/null && systemctl restart dann_guard 2>/dev/null && ok "dann_guard restarted" || warn "dann_guard service not started"
    systemctl restart php${PHP_VER}-fpm 2>/dev/null || systemctl restart php${PHP_VER}-fpm 2>/dev/null || true
    systemctl reload nginx 2>/dev/null || true
}

# --- MAIN ---
echo -e "${CYAN}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║        DANN-GUARD INSTALLER v1.0         ║"
echo "  ╚══════════════════════════════════════════╝"
echo -e "${NC}"

# Detect
detect_panel_dir
detect_php_version
detect_wings_data
detect_node_db_config
detect_volumes

echo -e "  ${CYAN}Panel:${NC}      $PANEL_DIR"
echo -e "  ${CYAN}PHP:${NC}        $PHP_VER"
echo -e "  ${CYAN}Wings data:${NC} $WINGS_DATA"
echo -e "  ${CYAN}Volumes:${NC}    $VOLUMES_DIR"
echo -e "  ${CYAN}DB:${NC}         $DB_USER@$DB_HOST/$DB_NAME"
echo ""

# Install
install_binary
install_systemd
install_config "/root/dann_guard/config.json"
install_php_scripts
install_challenge
install_nginx_config
install_panel_theme
install_quarantine
rebuild_frontend
restart_services

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
