#!/bin/sh
# ==========================================
# DroneHub Backend — container entrypoint
# Renders the nginx config for the runtime
# PORT, prepares Laravel directories and
# starts PHP-FPM + Nginx via Supervisor.
# ==========================================
set -e

PORT="${PORT:-8080}"

mkdir -p \
    /var/www/storage/framework/cache/data \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
    /var/www/public/build

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache || true
chmod -R 775 /var/www/storage /var/www/bootstrap/cache || true

# Only ${PORT} is substituted; nginx runtime vars ($uri, $query_string) are kept
sed -e "s/\${PORT}/${PORT}/g" \
    /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
