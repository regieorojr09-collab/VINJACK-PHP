# 1. Base Image: Official PHP 8.2 with Apache
FROM php:8.2-apache

# Set container working directory
WORKDIR /var/www/html

# 2 & 3. Install system dependencies and PHP extensions for database and XML handling
RUN apt-get update && apt-get install -y --no-install-recommends \
        libxml2-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mysqli \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Configure Apache to allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 4. Copy project files to the web root
COPY . /var/www/html/

# Set ownership and file permissions for the Apache web server
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 5. Expose port 80 and run Apache in the foreground
EXPOSE 80
CMD ["apache2-foreground"]
