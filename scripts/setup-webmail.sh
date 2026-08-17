#!/usr/bin/env bash
# setup-webmail.sh — راه‌اندازی Roundcube برای دامنهٔ ایمیل
set -e

# --- roundcube sqlite db ---
mkdir -p /var/lib/roundcube/roundcubesqldb
chown www-data:www-data /var/lib/roundcube/roundcubesqldb
DB=/var/lib/roundcube/roundcubesqldb/roundcube.db
if [ ! -f "$DB" ]; then
  SQL=$(ls /usr/share/roundcube/SQL/sqlite.initial.sql /usr/share/roundcube/SQL/sqlite.create.sql 2>/dev/null | head -1)
  echo "using schema: $SQL"
  php -r "\$db = new PDO('sqlite:/var/lib/roundcube/roundcubesqldb/roundcube.db'); \$db->exec(file_get_contents('$SQL')); echo 'DB-INIT-OK\n';"
  chown www-data:www-data "$DB"
fi

# --- roundcube config ---
DESKEY=$(openssl rand -base64 32)
cat > /etc/roundcube/config.inc.php <<CFG
<?php
\$config = [];
\$config['db_dsnw'] = 'sqlite:////var/lib/roundcube/roundcubesqldb/roundcube.db';
\$config['default_host'] = 'ssl://127.0.0.1';
\$config['default_port'] = 993;
\$config['smtp_host'] = 'ssl://127.0.0.1';
\$config['smtp_port'] = 465;
\$config['smtp_user'] = '%u';
\$config['smtp_pass'] = '%p';
\$config['des_key'] = '$DESKEY';
\$config['language'] = 'fa_IR';
\$config['create_default_folders'] = true;
\$config['imap_conn_options'] = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
\$config['smtp_conn_options'] = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
\$config['session_lifetime'] = 60;
\$config['ip_check'] = false;
CFG
chown www-data:www-data /etc/roundcube/config.inc.php
php -l /etc/roundcube/config.inc.php | tail -1

# --- nginx vhost ---
cat > /etc/nginx/sites-available/mail.visionprime-suite.ir <<'NGINX'
server {
    listen 80;
    server_name mail.visionprime-suite.ir;
    root /usr/share/roundcube;
    index index.php index.html;
    client_max_body_size 30M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. { deny all; }
    location ^~ /installer/ { deny all; }
}
NGINX
ln -sfn /etc/nginx/sites-available/mail.visionprime-suite.ir /etc/nginx/sites-enabled/mail.visionprime-suite.ir
nginx -t 2>&1 | tail -1
systemctl reload nginx && echo "NGINX-RELOADED"
echo "WEBMAIL-READY"
