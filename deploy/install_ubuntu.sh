#!/usr/bin/env bash
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/pservice}"; DOMAIN="${DOMAIN:-}"; APP_URL="${APP_URL:-}"; ADMIN_EMAIL="${ADMIN_EMAIL:-admin@pservice.local}"; ADMIN_NAME="${ADMIN_NAME:-Administrador}"; ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"; SOURCE_DIR="${SOURCE_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
[[ $EUID -eq 0 ]] || { echo 'Execute como root: sudo bash deploy/install_ubuntu.sh'; exit 1; }
if [[ -z "$ADMIN_PASSWORD" ]]; then read -r -s -p 'Senha do administrador (mín. 8): ' ADMIN_PASSWORD; echo; fi
[[ ${#ADMIN_PASSWORD} -ge 8 ]] || { echo 'Senha muito curta.'; exit 1; }
[[ -n "$APP_URL" ]] || APP_URL="${DOMAIN:+https://$DOMAIN}"; APP_URL="${APP_URL:-http://localhost}"
export DEBIAN_FRONTEND=noninteractive
apt-get update; apt-get install -y software-properties-common ca-certificates curl unzip nginx sqlite3 libsqlite3-dev zip rsync openssl cron
add-apt-repository -y ppa:ondrej/php; apt-get update
apt-get install -y php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl
if ! command -v composer >/dev/null; then curl -fsSL https://getcomposer.org/installer -o /tmp/composer.php; php /tmp/composer.php --install-dir=/usr/local/bin --filename=composer; rm -f /tmp/composer.php; fi
mkdir -p "$APP_DIR"; rsync -a --delete --exclude=.env --exclude=database/database.sqlite --exclude=storage/ "$SOURCE_DIR/" "$APP_DIR/"; cd "$APP_DIR"
mkdir -p storage/app/private storage/framework/{cache,sessions,views} storage/logs database; touch database/database.sqlite; chown -R www-data:www-data "$APP_DIR"; chmod -R ug+rwx storage bootstrap/cache; chmod 664 database/database.sqlite
cat > .env <<EOF
APP_NAME=PService
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=$APP_URL
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo
LOG_CHANNEL=stack
LOG_LEVEL=warning
DB_CONNECTION=sqlite
DB_DATABASE=$APP_DIR/database/database.sqlite
SESSION_DRIVER=file
SESSION_LIFETIME=120
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=file
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@pservice.local
MAIL_FROM_NAME=PService
BACKUP_RETENTION_DAYS=14
ADMIN_EMAIL=$ADMIN_EMAIL
ADMIN_NAME=$ADMIN_NAME
ADMIN_PASSWORD=$ADMIN_PASSWORD
EOF
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force; php artisan migrate --force; php artisan db:seed --force; php artisan optimize:clear; php artisan optimize
cat >/etc/php/8.3/fpm/conf.d/99-pservice.ini <<EOF
upload_max_filesize=60M
post_max_size=70M
max_execution_time=300
max_input_time=300
memory_limit=512M
EOF
systemctl enable --now php8.3-fpm nginx cron
cat >/etc/nginx/sites-available/pservice <<EOF
server { listen 80; listen [::]:80; server_name ${DOMAIN:-_}; root $APP_DIR/public; index index.php; client_max_body_size 70M; location / { try_files \$uri \$uri/ /index.php?\$query_string; } location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.3-fpm.sock; } location ~ /\. { deny all; } }
EOF
ln -sf /etc/nginx/sites-available/pservice /etc/nginx/sites-enabled/pservice; rm -f /etc/nginx/sites-enabled/default; nginx -t; systemctl reload nginx
cat >/etc/cron.d/pservice-backup <<EOF
30 2 * * * www-data cd $APP_DIR && /usr/bin/php artisan pservice:backup >> $APP_DIR/storage/logs/backup.log 2>&1
EOF
chmod 644 /etc/cron.d/pservice-backup
echo; echo "PService instalado em $APP_URL"; echo "Login: $ADMIN_EMAIL"; echo 'Backup diário: 02:30'; [[ -n "$DOMAIN" ]] && echo "Depois do DNS: apt-get install -y certbot python3-certbot-nginx && certbot --nginx -d $DOMAIN --redirect"
