#!/bin/sh
set -eu
mkdir -p "$AVERION_RUNTIME_DIR" /var/www/html/uploads /var/www/html/cache /var/lib/php/sessions
chown -R www-data:www-data "$AVERION_RUNTIME_DIR" /var/www/html/uploads /var/www/html/cache /var/lib/php/sessions
chmod 700 "$AVERION_RUNTIME_DIR" /var/lib/php/sessions
ln -sfn "$AVERION_RUNTIME_DIR/config.php" /var/www/html/config.php
exec docker-php-entrypoint "$@"
