#!/bin/sh
# Données persistantes : le disque Render est monté sur /var/data
set -e
mkdir -p /var/data/data /var/data/uploads
for d in data uploads; do
  if [ ! -L /var/www/html/$d ]; then
    cp -n /var/www/html/$d/.htaccess /var/www/html/$d/index.html /var/data/$d/ 2>/dev/null || true
    rm -rf /var/www/html/$d && ln -s /var/data/$d /var/www/html/$d
  fi
done
chown -R www-data:www-data /var/data
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
