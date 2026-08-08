#!/bin/bash
# Vision Prime — Sync the local public deployment (:80) from the workspace.
#
# The public site runs from a SEPARATE copy (vision-prime-deploy/) so that the
# dev/preview stack (Vite HMR on :5173 + artisan serve on :8000) stays untouched.
# Run this script after applying any patch to push it live:
#
#   bash scripts/deploy-public.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SRC="$ROOT/vision-prime"
DST="$ROOT/vision-prime-deploy"
LOG="$ROOT/.freebuff/preview-6d6b5ef1-8367-4ebc-9b0d-efc697be4158.log"

echo "==> 1/4 Building production assets (npm run build) ..."
( cd "$SRC" && npm run build )

echo "==> 2/4 Syncing code to $DST ..."
# robocopy needs native Windows paths, and its /flags must not be path-mangled
# by Git Bash: combine MSYS_NO_PATHCONV=1 (protects /E, /XD, /XF) with cygpath -w
# (converts the source/destination to drive-letter form).
SRC_WIN="$(cygpath -w "$SRC")"
DST_WIN="$(cygpath -w "$DST")"
# Exit codes 0-7 are success for robocopy; never let set -e trip on them.
MSYS_NO_PATHCONV=1 robocopy "$SRC_WIN" "$DST_WIN" \
    /E /XD node_modules .git .freebuff \
    /XF .env "public\\hot" /NFL /NDL /NJH /NJS /NP || true
# The deploy copy must serve BUILT assets, never the Vite dev server.
rm -f "$DST/public/hot"

echo "==> 3/4 Restarting public server on :80 ..."
OLD_PID="$(netstat -ano 2>/dev/null | grep ':80 ' | grep LISTENING | awk '{print $NF}' | head -1)"
if [ -n "$OLD_PID" ]; then
    taskkill //F //PID "$OLD_PID" >/dev/null 2>&1 || true
    sleep 2
fi

cd "$DST" && nohup php artisan serve --host=0.0.0.0 --port=80 </dev/null >> "$LOG" 2>&1 &
disown || true
cd "$ROOT"
sleep 6

echo "==> 4/4 Health check ..."
curl -s --noproxy '*' --max-time 15 -o /dev/null -w "    http://127.0.0.1:80 -> %{http_code}\n" http://127.0.0.1:80/ || {
    echo "    WARN: server not responding yet — check the log: $LOG"
    exit 1
}

echo "==> Done. Public site: http://visionprime-suite.ir (needs DNS A record + router port forwarding)"
