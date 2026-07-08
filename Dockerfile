FROM php:8.2-apache

# Install dependencies (unzip and git are required by Composer)
RUN apt-get update && apt-get install -y \
  unzip \
  git \
  && docker-php-ext-install pdo pdo_mysql

# Enable mod_rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Apache configuration
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Permissions (Ensure www-data owns the files)
RUN chown -R www-data:www-data /var/www/html

# Allow URL rewriting
RUN echo '<Directory /var/www/html/public>\n\
  Options Indexes FollowSymLinks\n\
  AllowOverride All\n\
  Require all granted\n\
  </Directory>' > /etc/apache2/conf-available/zdajnik.conf \
  && a2enconf zdajnik