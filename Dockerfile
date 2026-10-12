# Potters School - image pour Render (PHP 8.3 + Apache)
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libzip-dev libsqlite3-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install gd zip pdo_sqlite exif \
 && a2enmod rewrite headers deflate \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && echo "ServerTokens Prod" >> /etc/apache2/apache2.conf \
 && rm -rf /var/lib/apt/lists/*
COPY app/ /var/www/html/
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh && chown -R www-data:www-data /var/www/html
ENV PORT=10000
CMD ["/usr/local/bin/start.sh"]
