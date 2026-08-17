#!/usr/bin/env bash
# ============================================================
# add-site.sh — مدیریت خودکار دامنه‌های استاتیک روی سرور
# افزودن:  add-site.sh add <domain> [webroot]
# حذف:     add-site.sh remove <domain>
# ============================================================
set -euo pipefail

SERVER_IP="45.156.186.6"
NS1="ns1.visionprime-suite.ir"
NS2="ns2.visionprime-suite.ir"
ADMIN_EMAIL="admin.visionprime-suite.ir"
SITES_ROOT="/var/www/sites"
BIND_ZONES="/etc/bind/zones"
NAMED_LOCAL="/etc/bind/named.conf.local"
NGINX_AVAIL="/etc/nginx/sites-available"
NGINX_ENABLED="/etc/nginx/sites-enabled"

DOMAIN_RE='^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$'

die() { echo "✗ $*" >&2; exit 1; }
ok()  { echo "✓ $*"; }
info(){ echo "• $*"; }

validate_domain() {
  local d="$1"
  [[ "$d" =~ $DOMAIN_RE ]] || die "نام دامنه نامعتبر است: $d"
}

add_zone() {
  local d="$1"
  local zone_file="$BIND_ZONES/db.$d"
  if grep -q "\"$d\"" "$NAMED_LOCAL"; then
    info "زون $d از قبل در named.conf.local هست — رد می‌شوم."
    return 0
  fi
  cat > "$zone_file" <<ZONE
\$TTL 3600
@   IN  SOA $NS1. $ADMIN_EMAIL. (
        $(date +%Y%m%d%H) ; serial
        7200       ; refresh
        3600       ; retry
        1209600    ; expire
        3600 )     ; minimum
    IN  NS  $NS1.
    IN  NS  $NS2.
    IN  A   $SERVER_IP
www IN  A   $SERVER_IP
ZONE
  cat >> "$NAMED_LOCAL" <<NS

zone "$d" {
    type master;
    file "$zone_file";
};
NS
  if ! named-checkzone "$d" "$zone_file" >/dev/null 2>&1; then
    sed -i "/zone \"$d\"/,/};/d" "$NAMED_LOCAL"
    rm -f "$zone_file"
    die "زون $d معتبر نیست — تغییرات برگشت داده شد."
  fi
  ok "زون bind9 برای $d ساخته و معتبر شد."
}

add_vhost() {
  local d="$1"
  local root="$2"
  local conf="$NGINX_AVAIL/$d"
  if [[ -f "$conf" ]]; then
    info "vhost $d از قبل هست — رد می‌شوم."
    return 0
  fi
  cat > "$conf" <<NGINX
# $d — سایت استاتیک (تولیدشده توسط add-site.sh)
server {
    listen 80;
    server_name www.$d;
    return 301 http://$d\$request_uri;
}

server {
    listen 80;
    server_name $d;

    root $root;
    index index.html;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~* \.(css|js|jpg|jpeg|png|gif|svg|webp|ico|woff2?|ttf|eot|pdf)\$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        try_files \$uri =404;
    }

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    gzip on;
    gzip_types text/plain text/css application/javascript application/json image/svg+xml;
    gzip_min_length 1024;
}
NGINX
  ln -sfn "$conf" "$NGINX_ENABLED/$d"
  if ! nginx -t >/dev/null 2>&1; then
    rm -f "$conf" "$NGINX_ENABLED/$d"
    die "پیکربندی nginx برای $d نامعتبر است — تغییرات برگشت داده شد."
  fi
  ok "vhost nginx برای $d ساخته و فعال شد."
}

make_root() {
  local d="$1"
  local root="$2"
  mkdir -p "$root"
  if [[ ! -f "$root/index.html" ]]; then
    cat > "$root/index.html" <<HTML
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>$d</title>
<style>body{font-family:Tahoma,Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center}
.card{background:#1e293b;padding:3rem;border-radius:16px;max-width:560px}h1{color:#38bdf8;font-size:1.6rem}
code{background:#0f172a;padding:.2rem .5rem;border-radius:6px;color:#a5f3fc}</style></head>
<body><div class="card">
<h1>🌐 سایت $d آماده است</h1>
<p>این صفحهٔ پیش‌فرض است. فایل‌های سایت را در پوشهٔ زیر بریز:</p>
<p><code>$root</code></p>
<p>بعد از آپلود فایل‌ها، فقط کافیست این صفحه را با فایل <code>index.html</code> خودت جایگزین کنی.</p>
</div></body></html>
HTML
    ok "پوشهٔ سایت ساخته شد: $root (با صفحهٔ پیش‌فرض)"
  else
    info "پوشهٔ سایت از قبل فایل دارد — دست نمی‌زنم."
  fi
}

cmd_add() {
  local d="${2:?نام دامنه را بده، مثلاً: add-site.sh add example.com}"
  local root="${3:-$SITES_ROOT/$d}"
  validate_domain "$d"
  [[ "$d" == "visionprime-suite.ir" ]] && die "این دامنهٔ اصلی سوئیت است؛ دستی مدیریت می‌شود."
  make_root "$d" "$root"
  add_zone "$d"
  add_vhost "$d" "$root"
  systemctl reload named >/dev/null && ok "named ری‌لود شد."
  systemctl reload nginx >/dev/null && ok "nginx ری‌لود شد."
  echo
  echo "=============================================================="
  echo " ✅ دامنهٔ $d آماده است"
  echo "=============================================================="
  echo " فایل‌های سایت را بگذار در:  $root"
  echo
  echo " در پنل ایرنیک (برای این دامنه):"
  echo "   نام کارگزار ۱: $NS1   (آیپی: خالی)"
  echo "   نام کارگزار ۲: $NS2   (آیپی: خالی)"
  echo "   (چون این دو نامسرور از قبل با آیپی سرور ثبت شده‌اند، آیپی لازم نیست)"
  echo
  echo " بعد از انتشار DNS (تا ۲۴ ساعت)، SSL را نصب می‌کنیم."
  echo "=============================================================="
}

cmd_remove() {
  local d="${2:?نام دامنه را بده، مثلاً: add-site.sh remove example.com}"
  [[ "$d" == "visionprime-suite.ir" ]] && die "دامنهٔ اصلی سوئیت را حذف نمی‌کنم."
  rm -f "$NGINX_ENABLED/$d" "$NGINX_AVAIL/$d" "$BIND_ZONES/db.$d"
  sed -i "/zone \"$d\"/,/};/d" "$NAMED_LOCAL"
  systemctl reload named >/dev/null 2>&1 || true
  systemctl reload nginx >/dev/null 2>&1 || true
  ok "دامنهٔ $d حذف شد (فایل‌های سایت در $SITES_ROOT/$d دست‌نخورده ماندند)."
}

case "${1:-}" in
  add)    cmd_add "$@" ;;
  remove) cmd_remove "$@" ;;
  *) echo "کاربرد: add-site.sh add <domain> [webroot] | add-site.sh remove <domain>"; exit 1 ;;
esac
