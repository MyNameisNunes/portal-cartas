FROM php:8.3-apache

# UID/GID do dono dos arquivos no host. Alinhar o www-data com esse usuario
# permite que o upload grave em /var/www/html/uploads atraves do bind mount.
ARG APP_UID=1000
ARG APP_GID=1000

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers

RUN if [ "$APP_GID" != "33" ]; then groupmod -o -g "$APP_GID" www-data; fi \
 && if [ "$APP_UID" != "33" ]; then usermod  -o -u "$APP_UID" www-data; fi

COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN { \
      echo 'upload_max_filesize = 8M'; \
      echo 'post_max_size = 12M'; \
      echo 'display_errors = Off'; \
      echo 'log_errors = On'; \
      echo 'error_log = /dev/stderr'; \
      echo 'session.cookie_httponly = 1'; \
      echo 'session.use_strict_mode = 1'; \
      echo 'session.cookie_samesite = Lax'; \
    } > /usr/local/etc/php/conf.d/portal-cartas.ini

WORKDIR /var/www/html
