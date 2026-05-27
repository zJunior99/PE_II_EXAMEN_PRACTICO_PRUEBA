FROM php:8.2-apache

RUN a2enmod rewrite

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libonig-dev \
        libcurl4-openssl-dev \
        libpq-dev \
    && docker-php-ext-install mbstring curl pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html

RUN sed -i 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
      '<Directory /var/www/html/public>' \
      '  Options Indexes FollowSymLinks' \
      '  AllowOverride All' \
      '  Require all granted' \
      '</Directory>' \
      > /etc/apache2/conf-available/ri-public.conf \
    && a2enconf ri-public

ENV PORT=10000

CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf && sed -i \"s/:80/:${PORT}/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
