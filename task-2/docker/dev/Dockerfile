FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libzip-dev \
    postgresql-client \
    zip \
    unzip \
    git \
    curl \
    cron \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_pgsql bcmath gd zip exif

RUN groupadd -g ${GID} app \
 && useradd -u ${UID} -g app -m -s /bin/bash app

RUN git config --global --add safe.directory /var/www

COPY --from=composer:2.8.10 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY --chown=app:app . .

COPY docker/cron/laravel-scheduler /etc/cron.d/laravel-scheduler
RUN chmod 0644 /etc/cron.d/laravel-scheduler

USER app
RUN composer install --no-dev --no-interaction
USER root

RUN sed -i 's/^user = .*/user = app/'  /usr/local/etc/php-fpm.d/www.conf \
 && sed -i 's/^group = .*/group = app/' /usr/local/etc/php-fpm.d/www.conf

RUN mkdir -p storage/logs \
             storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             bootstrap/cache \
 && chown -R app:app storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
