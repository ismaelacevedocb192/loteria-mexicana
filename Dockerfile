FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite headers
COPY . /var/www/html/
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads
# Railway inyecta PORT; Apache debe escuchar ahí.
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/:80>/:${PORT}>/' /etc/apache2/sites-available/000-default.conf
ENV PORT=8080
EXPOSE 8080
CMD ["apache2-foreground"]
