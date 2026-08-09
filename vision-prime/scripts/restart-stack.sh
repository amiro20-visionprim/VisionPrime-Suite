#!/bin/bash
# Vision Prime — Bring the local stack back up (idempotent).
#
# Starts whatever is missing, never duplicates what is already running:
#   1. Laravel dev server   -> http://127.0.0.1:8000
#   2. Queue worker         -> processes jobs from the shared sqlite database
#   3. Tailscale funnel     -> public https URL (desktop-gco4lcg-1.tailbc9fd2.ts.net)
#
# Usage:
#   bash vision-prime/scripts/restart-stack.sh
#
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APP="$ROOT/vision-prime"
LOG="$ROOT/.freebuff/preview-6d6b5ef1-8367-4ebc-9b0d-efc697be4158.log"
WORKER_LOG="$ROOT/.freebuff/queue-worker.log"
PORT=8000

is_listening() { netstat -ano 2>/dev/null | grep -qE ":$1 .*LISTENING"; }

echo "==> 1/3 Laravel dev server (:${PORT})"
if is_listening "$PORT"; then
    echo "    already running — skip"
else
    ( cd "$APP" && nohup php artisan serve --host=127.0.0.1 --port="$PORT" </dev/null >> "$LOG" 2>&1 & )
    echo "    started, waiting for health ..."
    for _ in $(seq 1 10); do
        sleep 2
        code="$(curl -s --noproxy '*' --max-time 5 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/" 2>/dev/null || true)"
        [ "$code" = "200" ] && break
    done
    echo "    health: ${code:-failed}"
fi

echo "==> 2/3 Queue worker (database)"
if powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" | Where-Object { \$_.CommandLine -match 'queue:work' } | Select-Object -First 1" 2>/dev/null | grep -q ProcessId; then
    echo "    already running — skip"
else
    ( cd "$APP" && nohup php artisan queue:work database --max-time=3600 </dev/null >> "$WORKER_LOG" 2>&1 & )
    echo "    worker started"
fi

echo "==> 3/3 Tailscale funnel (:80 public)"
if timeout 15 tailscale funnel status 2>/dev/null | grep -q "Funnel on"; then
    echo "    already on — skip"
else
    echo "    enabling (requires tailscale login + funnel allowed in admin console) ..."
    timeout 25 tailscale funnel --bg 80 2>&1 | head -6 || echo "    WARN: funnel enable failed — check tailscale state"
fi

echo "==> done. Public: https://desktop-gco4lcg-1.tailbc9fd2.ts.net/"
