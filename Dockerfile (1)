# Immagine base con PHP + Apache
FROM php:8.2-apache

# Copia i file PHP nella root del web server
COPY index.php /var/www/html/

# Abilita mod_rewrite se ti servisse (opzionale)
RUN a2enmod rewrite