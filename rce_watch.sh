#!/bin/bash
# RCE Watch Daemon — real-time file monitoring via inotifywait
# Watches all server volume directories for new/changed PHP files
# and immediately scans them via rce_controller.php
#
# Usage: ./rce_watch.sh [start|stop|restart|status]

PIDFILE="/var/run/rce_watch.pid"
LOGFILE="/var/log/rce_watch.log"
CONTROLLER="/root/dann_guard/rce_controller.php"
VOLUMES="/var/lib/pterodactyl/volumes"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >> "$LOGFILE"; }

scan_file() {
    local filepath="$1"
    [ -d "$filepath" ] && return
    local ext="${filepath##*.}"
    case "${ext,,}" in
        php|php5|phtml|php7|php8|shtml|php3|php4|cpp|c|h|hpp|hxx|cxx|cc|hh|cs|py|pl|rb|go|rs|js|sh|bash|java|jar|zip|tar|gz|rar|tgz) ;;
        *) return ;;
    esac
    sleep 0.3
    log "Scanning: $filepath"
    local result
    result=$(php "$CONTROLLER" scan "$filepath" 2>/dev/null)
    local status
    status=$(echo "$result" | grep -o '"status":"[^"]*"' | head -1 | cut -d'"' -f4)
    case "$status" in
        quarantined)   log "QUARANTINED: $filepath" ;;
        quarantined_copy) log "QUARANTINED (copy): $filepath" ;;
    esac
}

watch_volumes() {
    # Ensure volumes dir exists
    [ ! -d "$VOLUMES" ] && mkdir -p "$VOLUMES"

    log "Starting volume watcher (PID $$)"

    # Watch the parent volumes dir for new subdirectories
    while true; do
        # Watch existing server dirs recursively for file changes
        local dirs=()
        for d in "$VOLUMES"/*/; do
            [ -d "$d" ] && dirs+=("$d")
        done

        if [ ${#dirs[@]} -eq 0 ]; then
            log "No server directories found, waiting..."
            inotifywait -t 30 -e create "$VOLUMES" 2>/dev/null || true
            continue
        fi

        log "Watching ${#dirs[@]} server directories..."

        # Watch all existing server dirs for new/modified files
        # Also watch the parent for new server dirs
        inotifywait -r -m -e create -e modify -e moved_to \
            --format '%w%f' \
            --exclude '\.(txt|log|json|yml|yaml|md|sql|git|svn|swp|bak|old|tmp|session|zip|tar|gz|rar|jpg|jpeg|png|gif|ico|svg|css|js|map|woff|woff2|ttf|eot)$' \
            "${dirs[@]}" "$VOLUMES" 2>/dev/null | while read -r filepath; do
            scan_file "$filepath"
        done

        log "Watch exited, restarting in 3s..."
        sleep 3
    done
}

start_daemon() {
    if [ -f "$PIDFILE" ] && kill -0 $(cat "$PIDFILE") 2>/dev/null; then
        echo "rce_watch already running (PID $(cat "$PIDFILE"))"
        exit 0
    fi
    echo "Starting rce_watch daemon..."
    nohup "$0" _daemon >> "$LOGFILE" 2>&1 &
    echo $! > "$PIDFILE"
    sleep 1
    if kill -0 $(cat "$PIDFILE") 2>/dev/null; then
        echo "rce_watch started (PID $(cat "$PIDFILE"))"
    else
        echo "Failed to start rce_watch"
        rm -f "$PIDFILE"
        exit 1
    fi
}

stop_daemon() {
    if [ ! -f "$PIDFILE" ]; then
        pkill -f "rce_watch.sh _daemon" 2>/dev/null && echo "rce_watch stopped (force)" || echo "Not running"
        rm -f "$PIDFILE"
        exit 0
    fi
    local pid=$(cat "$PIDFILE")
    kill "$pid" 2>/dev/null
    sleep 2
    if kill -0 "$pid" 2>/dev/null; then
        kill -9 "$pid" 2>/dev/null
    fi
    rm -f "$PIDFILE"
    echo "rce_watch stopped"
}

status_daemon() {
    if [ -f "$PIDFILE" ] && kill -0 $(cat "$PIDFILE") 2>/dev/null; then
        echo "rce_watch running (PID $(cat "$PIDFILE"))"
    else
        echo "rce_watch not running"
        [ -f "$PIDFILE" ] && rm -f "$PIDFILE" 2>/dev/null
    fi
}

case "${1:-status}" in
    start)   start_daemon ;;
    stop)    stop_daemon ;;
    restart) stop_daemon; sleep 1; start_daemon ;;
    status)  status_daemon ;;
    _daemon) watch_volumes ;;
    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac
