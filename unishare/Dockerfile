FROM php:8.2-apache

# MySQL va PDO kengaytmalarini o'rnatish
RUN docker-php-ext-install pdo pdo_mysql

# Fayllarni Apache veb-server katalogiga ko'chirish
COPY . /var/www/html/

# Apache porti
EXPOSE 80