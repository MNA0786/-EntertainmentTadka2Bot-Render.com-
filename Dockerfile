FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    curl \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl

RUN a2enmod rewrite headers
COPY .htaccess /var/www/html/.htaccess

COPY index.php /var/www/html/
COPY composer.* /var/www/html/

RUN chown -R www-data:www-data /var/www/html/ \
    && chmod 755 /var/www/html/ \
    && chmod 666 /var/www/html/error.log 2>/dev/null || true \
    && touch /var/www/html/movies.csv && chmod 666 /var/www/html/movies.csv \
    && touch /var/www/html/users.json && chmod 666 /var/www/html/users.json \
    && touch /var/www/html/error.log && chmod 666 /var/www/html/error.log

EXPOSE 80
CMD ["apache2-foreground"]