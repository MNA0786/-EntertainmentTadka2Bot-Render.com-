FROM php:8.2-apache

# System dependencies aur PHP extensions
RUN apt-get update && apt-get install -y \
    curl \
    libcurl4-openssl-dev \
    libssl-dev \
    unzip \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Apache modules enable karo
RUN a2enmod rewrite headers

# Working directory set karo
WORKDIR /var/www/html

# Pehle dependencies copy karo aur install karo
COPY composer.json .
RUN if [ -f composer.json ]; then \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer install --no-dev --no-scripts --optimize-autoloader; \
    fi

# Phir baki files copy karo
COPY index.php .
COPY .htaccess .

# File permissions set karo
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod 755 /var/www/html/ \
    && touch movies.csv users.json error.log \
    && chmod 666 movies.csv users.json error.log

# Port expose karo
EXPOSE 80

# Health check add karo
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Apache start karo
CMD ["apache2-foreground"]
