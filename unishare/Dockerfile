FROM php:8.2-apache

# MySQL va PDO kengaytmasi
RUN docker-php-ext-install pdo pdo_mysql

# Aynan unishare papkasi ichidagilarni veb-ildizga ko'chirish
COPY unishare/ /var/www/html/

EXPOSE 80