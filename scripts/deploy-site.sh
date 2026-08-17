#!/usr/bin/env bash
# deploy-site.sh — استقرار خودکار سایت استاتیک
# کاربرد: deploy-site.sh <domain> <git-url | zip-file | directory>
#   git-url : کلون/پول از مخزن عمومی (یا با کلید SSH)
#   zip-file: فایل zip فایل‌های سایت
#   directory: پوشهٔ محلی فایل‌ها
set -euo pipefail

DOMAIN="${1:?کاربرد: deploy-site.sh <domain> <git-url|zip|dir>}"
SOURCE="${2:?کاربرد: deploy-site.sh <domain> <git-url|zip|dir>}"
SITES_ROOT="/var/www/sites"
TARGET="$SITES_ROOT/$DOMAIN"
LOG="/var/log/deploy-site.log"
export HOME="/var/www/.deploy-home"

log() { echo "$(date '+%F %T') $DOMAIN: $*" | tee -a "$LOG"; }
die() { log "ERROR: $*"; exit 1; }

[ -d "$TARGET" ] || die "سایت $DOMAIN وجود ندارد — اول «add-site.sh add $DOMAIN» را اجرا کن."

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

case "$SOURCE" in
  *.zip|*.ZIP)
    command -v unzip >/dev/null 2>&1 || die "unzip نصب نیست"
    command -v rsync >/dev/null 2>&1 || die "rsync نصب نیست"
    unzip -q "$SOURCE" -d "$WORK" || die "unzip ناموفق: $SOURCE"
    TOPDIRS=$(find "$WORK" -mindepth 1 -maxdepth 1 -type d | wc -l)
    TOPFILES=$(find "$WORK" -mindepth 1 -maxdepth 1 -type f | wc -l)
    if [ "$TOPDIRS" -eq 1 ] && [ "$TOPFILES" -eq 0 ]; then
      SRC=$(find "$WORK" -mindepth 1 -maxdepth 1 -type d | head -1)
      log "zip: پوشهٔ ریشهٔ واحد ($(basename "$SRC")) باز شد"
    else
      SRC="$WORK"
    fi
    rsync -a --delete --checksum "$SRC/" "$TARGET/" || die "rsync ناموفق"
    log "deployed zip: $SOURCE"
    ;;
  http://*|https://*|git@*|file://*)
    command -v git >/dev/null 2>&1 || die "git نصب نیست"
    command -v rsync >/dev/null 2>&1 || die "rsync نصب نیست"
    if [ -d "$TARGET/.git" ]; then
      git -C "$TARGET" pull --ff-only 2>>"$LOG" || die "git pull ناموفق در $TARGET"
      log "git pull: $SOURCE"
    else
      git clone --depth 1 "$SOURCE" "$WORK/src" 2>>"$LOG" || die "git clone ناموفق: $SOURCE"
      rsync -a --delete --checksum --exclude '.git' "$WORK/src/" "$TARGET/" || die "rsync ناموفق"
      log "git clone+rsync: $SOURCE"
    fi
    ;;
  *)
    [ -d "$SOURCE" ] || die "منبع نامعتبر است: $SOURCE"
    command -v rsync >/dev/null 2>&1 || die "rsync نصب نیست"
    rsync -a --delete --checksum "$SOURCE/" "$TARGET/" || die "rsync ناموفق"
    log "deployed dir: $SOURCE"
    ;;
esac

chown -R www-data:www-data "$TARGET"
chmod -R u+rwX,go+rX "$TARGET"
log "deploy finished OK"
