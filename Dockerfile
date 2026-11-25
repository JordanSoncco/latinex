FROM serversideup/php:8.5-fpm-alpine

USER root

RUN { \
    echo 'upload_max_filesize=12M'; \
    echo 'post_max_size=14M'; \
    echo 'memory_limit=256M'; \
    echo 'expose_php=Off'; \
    echo 'session.cookie_httponly=1'; \
    echo 'session.cookie_samesite=Lax'; \
    echo 'session.use_strict_mode=1'; \
} > /usr/local/etc/php/conf.d/zz-latinex.ini

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html

RUN mkdir -p /var/www/html/storage/proyectos /var/www/html/temp_uploads \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/temp_uploads

USER www-data
