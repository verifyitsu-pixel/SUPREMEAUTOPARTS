#!/usr/bin/env bash
set -euo pipefail

# Railway's managed MySQL variables are mapped to the official WordPress image.
export WORDPRESS_DB_HOST="${WORDPRESS_DB_HOST:-${MYSQLHOST:-mysql}:${MYSQLPORT:-3306}}"
export WORDPRESS_DB_USER="${WORDPRESS_DB_USER:-${MYSQLUSER:-root}}"
export WORDPRESS_DB_PASSWORD="${WORDPRESS_DB_PASSWORD:-${MYSQLPASSWORD:-}}"
export WORDPRESS_DB_NAME="${WORDPRESS_DB_NAME:-${MYSQLDATABASE:-railway}}"
export WORDPRESS_TABLE_PREFIX="${WORDPRESS_TABLE_PREFIX:-wp_}"

# Railway routes health checks and public traffic to the injected runtime port.
# The upstream WordPress image defaults Apache to port 80, so update both the
# listener and virtual host before handing off to its official entrypoint.
if [[ -n "${PORT:-}" && "${PORT}" =~ ^[0-9]+$ ]]; then
  sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# PHP's Apache module requires prefork. Ensure no inherited platform/module
# configuration leaves a second MPM enabled, which makes Apache refuse startup.
rm -f /etc/apache2/mods-enabled/mpm_event.load \
  /etc/apache2/mods-enabled/mpm_event.conf \
  /etc/apache2/mods-enabled/mpm_worker.load \
  /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork >/dev/null

if [[ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]]; then
  export WP_HOME="${WP_HOME:-https://${RAILWAY_PUBLIC_DOMAIN}}"
  export WP_SITEURL="${WP_SITEURL:-https://${RAILWAY_PUBLIC_DOMAIN}}"
fi

# Persist uploads on an optional Railway volume, while keeping code immutable.
if [[ "${RAILWAY_VOLUME_MOUNT_PATH:-}" == "/var/www/html/wp-content/uploads" ]]; then
  mkdir -p /var/www/html/wp-content/uploads
  chown -R www-data:www-data /var/www/html/wp-content/uploads
fi

exec docker-entrypoint.sh "$@"
