#!/usr/bin/env bash
# Instalador do PService para Ubuntu 22.04/24.04.
# Pode ser executado de novo com segurança: .env, banco e fotos são preservados.
#
# Variáveis opcionais:
#   DOMAIN=pservice.exemplo.com.br   domínio (sem ele, o sistema responde pelo IP)
#   IP_ADDRESS=142.93.115.155        IP público (detectado sozinho se omitido)
#   CERTBOT_EMAIL=voce@exemplo.com   se definido, emite HTTPS (Let's Encrypt) para o
#                                    domínio ou, sem domínio, para o próprio IP (certificado de 6 dias, renovação automática)
#   ADMIN_EMAIL / ADMIN_NAME / ADMIN_PASSWORD   admin inicial (só na 1ª instalação)
#   APP_DIR=/var/www/pservice        pasta de instalação
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/pservice}"
DOMAIN="${DOMAIN:-}"
IP_ADDRESS="${IP_ADDRESS:-}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@pservice.local}"
ADMIN_NAME="${ADMIN_NAME:-Administrador}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
SOURCE_DIR="${SOURCE_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
PHP_V=8.3
WEB_USER=www-data

log() { echo -e "\n\033[1;34m==> $*\033[0m"; }
as_web() { sudo -u "$WEB_USER" -H "$@"; }

[[ $EUID -eq 0 ]] || { echo 'Execute como root: sudo bash deploy/install_ubuntu.sh'; exit 1; }

FIRST_INSTALL=1
[[ -f "$APP_DIR/.env" ]] && FIRST_INSTALL=0

if [[ $FIRST_INSTALL -eq 1 && -z "$ADMIN_PASSWORD" ]]; then
  read -r -s -p 'Senha do administrador (mín. 8): ' ADMIN_PASSWORD; echo
  [[ ${#ADMIN_PASSWORD} -ge 8 ]] || { echo 'Senha muito curta.'; exit 1; }
fi

if [[ -z "$DOMAIN" ]] && ls /etc/nginx/sites-enabled/ 2>/dev/null | grep -vqE '^(default|pservice)$'; then
  echo "ATENÇÃO: já existem outros sites no Nginx desta VPS."
  echo "Sem DOMAIN definido o PService responderia como site padrão e poderia conflitar."
  echo "Rode novamente com: sudo DOMAIN=pservice.seudominio.com.br bash deploy/install_ubuntu.sh"
  exit 1
fi

log "Pacotes do sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -y -q ca-certificates curl unzip zip git nginx sqlite3 rsync cron sudo software-properties-common
if ! apt-cache show "php${PHP_V}-fpm" >/dev/null 2>&1; then
  add-apt-repository -y ppa:ondrej/php && apt-get update -q
fi
apt-get install -y -q php${PHP_V}-{fpm,cli,common,sqlite3,mysql,mbstring,xml,curl,zip,gd,bcmath,intl}

if [[ -z "$IP_ADDRESS" ]]; then
  IP_ADDRESS="$(curl -fsS4 --max-time 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')"
fi
CERT_NAME="${DOMAIN:-$IP_ADDRESS}"

if ! command -v composer >/dev/null; then
  log "Composer"
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
  rm -f /tmp/composer-setup.php
fi

log "Código da aplicação em $APP_DIR"
mkdir -p "$APP_DIR"
if [[ "$(realpath "$SOURCE_DIR")" != "$(realpath "$APP_DIR")" ]]; then
  rsync -a --delete \
    --exclude=.git --exclude=.env --exclude=vendor/ --exclude=database/database.sqlite --exclude=storage/ \
    "$SOURCE_DIR/" "$APP_DIR/"
fi
cd "$APP_DIR"

mkdir -p storage/app/private storage/app/tmp storage/framework/{cache/data,sessions,views} storage/logs storage/backups bootstrap/cache database
[[ -f database/database.sqlite ]] || touch database/database.sqlite

if [[ $FIRST_INSTALL -eq 1 ]]; then
  log "Criando .env"
  sed -e "s#^APP_URL=.*#APP_URL=http://$CERT_NAME#" .env.example > .env
  echo "DB_DATABASE=$APP_DIR/database/database.sqlite" >> .env
else
  log ".env existente preservado"
  sed -i '/^ADMIN_PASSWORD=/d' .env   # a v1 deixava a senha gravada aqui
fi
chmod 640 .env

log "Permissões"
# Código pertence ao root (o PHP não consegue alterá-lo); só as pastas de dados são graváveis.
chown -R root:$WEB_USER "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 750 {} +
find "$APP_DIR" -type f -exec chmod 640 {} +
chmod 750 artisan deploy/*.sh
chown -R $WEB_USER:$WEB_USER storage bootstrap/cache database
chmod -R ug+rwX storage bootstrap/cache database

log "Dependências PHP (composer)"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
chown -R root:$WEB_USER vendor
as_web php artisan package:discover --ansi

grep -q '^APP_KEY=base64:' .env || { chown $WEB_USER .env; as_web php artisan key:generate --force; chown root:$WEB_USER .env; }

log "Banco de dados"
as_web php artisan migrate --force
if [[ $FIRST_INSTALL -eq 1 ]]; then
  # A senha vai só para o ambiente deste processo; não fica gravada em arquivo.
  as_web env ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_NAME="$ADMIN_NAME" ADMIN_PASSWORD="$ADMIN_PASSWORD" php artisan db:seed --force
fi
unset ADMIN_PASSWORD
as_web php artisan optimize:clear >/dev/null
as_web php artisan optimize

log "PHP-FPM"
cat >/etc/php/${PHP_V}/fpm/conf.d/99-pservice.ini <<INI
; Fotos são enviadas uma por requisição pelo app.
upload_max_filesize=45M
post_max_size=50M
max_file_uploads=30
max_execution_time=300
max_input_time=300
memory_limit=512M
expose_php=Off
INI
systemctl enable -q php${PHP_V}-fpm nginx cron
systemctl restart php${PHP_V}-fpm

log "Nginx"
SERVER_NAME="${DOMAIN:-_}"
LIVE="/etc/letsencrypt/live/$CERT_NAME"

write_nginx() {  # $1 = "ssl" quando já existe certificado
  local app_block
  app_block=$(cat <<BLOCK
    root $APP_DIR/public;
    index index.php;
    charset utf-8;
    client_max_body_size 50M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(self), geolocation=()" always;

    gzip on;
    gzip_types text/css application/javascript application/json application/manifest+json image/svg+xml;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /sw.js { add_header Cache-Control "no-cache"; try_files \$uri =404; }
    location ~* ^/(css|js|icons|assets)/ { expires 30d; access_log off; try_files \$uri =404; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_V}-fpm.sock;
        fastcgi_read_timeout 300;
    }
    location ~ /\.(?!well-known) { deny all; }
BLOCK
)
  if [[ "${1:-}" == ssl ]]; then
    cat >/etc/nginx/sites-available/pservice <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $SERVER_NAME;
    # desafio do Let's Encrypt precisa continuar em HTTP para as renovações
    location ^~ /.well-known/acme-challenge/ { root $APP_DIR/public; }
    location / { return 301 https://\$host\$request_uri; }
}
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $SERVER_NAME;
    ssl_certificate     $LIVE/fullchain.pem;
    ssl_certificate_key $LIVE/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
$app_block
}
NGINX
  else
    cat >/etc/nginx/sites-available/pservice <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $SERVER_NAME;
    location ^~ /.well-known/acme-challenge/ { root $APP_DIR/public; }
$app_block
}
NGINX
  fi
  ln -sf /etc/nginx/sites-available/pservice /etc/nginx/sites-enabled/pservice
  [[ -z "$DOMAIN" ]] && rm -f /etc/nginx/sites-enabled/default
  nginx -t && systemctl reload nginx
}

if [[ -f "$LIVE/fullchain.pem" ]]; then write_nginx ssl; else write_nginx; fi

if [[ -n "$CERTBOT_EMAIL" && ! -f "$LIVE/fullchain.pem" ]]; then
  log "HTTPS (Let's Encrypt) para $CERT_NAME"
  # Certbot via snap: certificado para IP exige versão 5.4+ (a do apt é antiga).
  apt-get remove -y -q certbot python3-certbot-nginx >/dev/null 2>&1 || true
  snap install --classic certbot >/dev/null
  ln -sf /snap/bin/certbot /usr/bin/certbot
  CB_ARGS=(certonly --webroot -w "$APP_DIR/public" --agree-tos -m "$CERTBOT_EMAIL" --non-interactive
           --deploy-hook "systemctl reload nginx")
  if [[ -z "$DOMAIN" ]]; then
    CB_VER="$(certbot --version 2>&1 | awk '{print $2}')"
    if [[ "$(printf '%s\n5.4.0\n' "$CB_VER" | sort -V | head -1)" != "5.4.0" ]]; then
      echo "Certbot $CB_VER não emite certificado para IP (precisa 5.4+). Seguindo em HTTP."
    else
      CB_ARGS+=(--preferred-profile shortlived --ip-address "$IP_ADDRESS")
    fi
  else
    CB_ARGS+=(-d "$DOMAIN")
  fi
  if [[ " ${CB_ARGS[*]} " == *" --ip-address "* || -n "$DOMAIN" ]] && certbot "${CB_ARGS[@]}"; then
    RENEW_CONF="/etc/letsencrypt/renewal/$CERT_NAME.conf"
    if [[ -z "$DOMAIN" && -f "$RENEW_CONF" ]]; then
      # certificado de 6 dias: renova com 2 dias de antecedência (o timer do certbot roda 2x ao dia)
      sed -i '/^#\? *renew_before_expiry/d' "$RENEW_CONF"
      sed -i '1i renew_before_expiry = 2 days' "$RENEW_CONF"
    fi
    write_nginx ssl
  else
    echo "Não foi possível emitir o certificado. O sistema segue em HTTP; rode o instalador de novo depois."
  fi
fi

log "URL e cookies"
if [[ -f "$LIVE/fullchain.pem" ]]; then SCHEME=https; SECURE=true; else SCHEME=http; SECURE=false; fi
sed -i "s#^APP_URL=.*#APP_URL=$SCHEME://$CERT_NAME#" .env
if grep -q '^SESSION_SECURE_COOKIE=' .env; then sed -i "s/^SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=$SECURE/" .env; else echo "SESSION_SECURE_COOKIE=$SECURE" >> .env; fi
as_web php artisan config:cache >/dev/null

log "Agendador (backup diário 02:30 e limpezas)"
cat >/etc/cron.d/pservice <<CRON
* * * * * $WEB_USER cd $APP_DIR && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/pservice
rm -f /etc/cron.d/pservice-backup   # agendamento antigo (v1)

echo
echo "✅ PService instalado em $APP_DIR"
[[ "$SCHEME" == http ]] && echo "⚠️  Sem HTTPS: o app não fica instalável como PWA e a senha trafega sem criptografia. Rode de novo com CERTBOT_EMAIL=... para ativar."
grep '^APP_URL=' .env
[[ $FIRST_INSTALL -eq 1 ]] && echo "Login: $ADMIN_EMAIL"
grep -q '^BACKUP_RCLONE_REMOTE=.\+' .env || echo "⚠️  Configure BACKUP_RCLONE_REMOTE no .env para ter cópia das fotos fora da VPS (veja DEPLOY_VPS.md)."
grep -q '^MAIL_MAILER=log' .env && echo "⚠️  MAIL_MAILER=log: a recuperação de senha por e-mail só funciona após configurar SMTP."
exit 0
