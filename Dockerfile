FROM php:8.2-apache

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable apache rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . /var/www/html

# Expose port 80
EXPOSE 80

# Run migration script and then start Apache
CMD php scripts/migrate.php && apache2-foreground
