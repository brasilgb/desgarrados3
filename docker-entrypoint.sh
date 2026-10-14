#!/bin/sh
set -eu
mkdir -p /var/www/html/public/build
cp -a /opt/public-build/. /var/www/html/public/build/
# Produção: config, rotas, views e eventos em cache a partir do ambiente do contêiner (nunca de um .env).
# Roda como www-data para que o PHP-FPM continue dono dos arquivos; falha aqui interrompe o contêiner.
if [ "${APP_ENV:-}" = "production" ]; then
    su -s /bin/sh www-data -c "/usr/local/bin/php artisan optimize --no-interaction"
fi
exec "$@"
