FROM php:7.3-fpm-stretch

ENV LANG "C.UTF-8"
ENV LANGUAGE "C.UTF-8"
ENV APP_ENV "prod"
ENV APP_DEBUG 0

ENV PHPIZE_DEPS \
		autoconf \
		dpkg-dev dpkg \
		file \
		g++ \
		gcc \
		libc-dev \
		make \
		pkgconf \
		re2c \
		wget

RUN sed -i "s/stretch main/stretch main contrib non-free/" /etc/apt/sources.list \
    && sed -i '/stretch-updates/d' /etc/apt/sources.list \
    && apt-get update \
    && apt-get install -y \
    $PHPIZE_DEPS \
    git \
    vim \
    bzip2 \
    libbz2-dev \
    unzip \
    libzip-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libghc-postgresql-libpq-dev \
    libicu-dev \
    libxrender1 libfontconfig \
    \
    && docker-php-ext-install mbstring bz2 zip \
    && docker-php-ext-configure gd -with-freetype-dir=/usr/include/ -with-jpeg-dir=/usr/include/ \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/include/postgresql/ \
    && docker-php-ext-install gd pgsql pdo_pgsql bcmath intl \
    && pecl install apcu redis \
    && docker-php-ext-enable apcu redis opcache \
    && apt-get clean \
    && chown www-data:www-data /var/www

RUN wget https://github.com/tony2001/pinba_extension/archive/master.zip \
    && unzip master.zip \
    && rm master.zip \
    && cd pinba_extension-master \
    && phpize \
    && ./configure --enable-pinba \
    && make \
    && make install \
    && cd .. \
    && cp pinba_extension-master/modules/pinba.so /usr/local/lib/php/extensions/pinba.so \
    && rm -R pinba_extension-master

# fix files permissions
RUN groupmod -o -g 1000 www-data && usermod -o -u 1000 www-data

COPY services/php_prod/fpm-pool.ini /usr/local/etc/php-fpm.d/www.conf
COPY services/php_prod/prod.ini /usr/local/etc/php/conf.d/prod.ini
COPY services/php_prod/zzz.docker.conf /usr/local/etc/php-fpm.d/zzz.docker.conf

COPY / /var/www/stripe

WORKDIR /var/www/stripe

RUN mkdir -p var/cache var/log && bin/console c:w && chmod -R a+wx var/cache var/log

USER www-data
