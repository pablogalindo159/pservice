#!/usr/bin/env bash
# Atualiza o PService a partir do GitHub sem mexer em .env, banco ou fotos.
# Uso: sudo bash deploy/update.sh
set -euo pipefail
APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
PHP_V=8.3
cd "$APP_DIR"
as_web() { sudo -u www-data -H "$@"; }

[[ $EUID -eq 0 ]] || { echo 'Execute como root: sudo bash deploy/update.sh'; exit 1; }
[[ -d .git ]] || { echo "$APP_DIR não é um clone git. Use deploy/install_ubuntu.sh com o novo código."; exit 1; }

echo "==> Snapshot do banco antes de atualizar"
as_web php artisan pservice:backup --no-offsite || echo "(backup falhou; continuando)"

as_web php artisan down --retry=30 || true
trap 'as_web php artisan up || true' EXIT

git config --global --add safe.directory "$APP_DIR" >/dev/null 2>&1 || true
git fetch --quiet origin
git reset --hard "origin/$(git rev-parse --abbrev-ref HEAD)"

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
chown -R root:www-data vendor app bootstrap/app.php config public resources routes database/migrations
chown -R www-data:www-data storage bootstrap/cache database/database.sqlite
as_web php artisan package:discover --ansi
as_web php artisan migrate --force
as_web php artisan optimize:clear >/dev/null
as_web php artisan optimize
systemctl reload php${PHP_V}-fpm

echo "✅ Atualizado para $(git log -1 --format='%h %s')"
