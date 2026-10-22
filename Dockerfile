FROM php:8.5.12-apache

# mbstring and opcache ship with this image; install MySQL PDO only.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/scigenie.ini

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 775 /var/www/html/data

EXPOSE 80
