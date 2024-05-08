FROM php:8.2.18-fpm as base

ENV NGINX_VERSION   1.25.5
ENV NJS_VERSION     0.8.4
ENV NJS_RELEASE     3~bookworm
ENV PKG_RELEASE     1~bookworm

RUN apt-get update && apt-get install --no-install-recommends --no-install-suggests -y \
    curl \
    wget \
    bash \
    supervisor \
    git \
    dos2unix \
    openssh-client \
    zip \
    unzip \
    # need for nginx and node
    # @see https://github.com/nodejs/docker-node/blob/3101ce6b5b3a0308b58d464eef141e0043c3bf5b/14/buster-slim/Dockerfile
    # @see https://github.com/nginxinc/docker-nginx/blob/f3fe494531f9b157d9c09ba509e412dace54cd4f/stable/debian/Dockerfile
    gnupg1 \
    ca-certificates \
    dirmngr \
    xz-utils \
    && rm -r /var/lib/apt/lists/* \
    # install nginx (copied from official nginx Dockerfile) \
    && groupadd --system --gid 102 nginx \
    && useradd --system --gid nginx --no-create-home --home /nonexistent --comment "nginx user" --shell /bin/false --uid 102 nginx \
    && \
    NGINX_GPGKEY=573BFD6B3D8FBC641079A6ABABF5BD827BD9BF62; \
    NGINX_GPGKEY_PATH=/etc/apt/keyrings/nginx-archive-keyring.gpg; \
    export GNUPGHOME="$(mktemp -d)"; \
    found=''; \
    for server in \
        hkp://keyserver.ubuntu.com:80 \
        pgp.mit.edu \
    ; do \
        echo "Fetching GPG key $NGINX_GPGKEY from $server"; \
        gpg1 --keyserver "$server" --keyserver-options timeout=10 --recv-keys "$NGINX_GPGKEY" && found=yes && break; \
    done; \
    test -z "$found" && echo >&2 "error: failed to fetch GPG key $NGINX_GPGKEY" && exit 1; \
    gpg1 --export "$NGINX_GPGKEY" > "$NGINX_GPGKEY_PATH" ; \
    rm -rf "$GNUPGHOME"; \
    apt-get remove --purge --auto-remove -y gnupg1 && rm -rf /var/lib/apt/lists/* \
    && dpkgArch="$(dpkg --print-architecture)" \
    && nginxPackages=" \
        nginx=${NGINX_VERSION}-${PKG_RELEASE} \
        nginx-module-xslt=${NGINX_VERSION}-${PKG_RELEASE} \
        nginx-module-geoip=${NGINX_VERSION}-${PKG_RELEASE} \
        nginx-module-image-filter=${NGINX_VERSION}-${PKG_RELEASE} \
        nginx-module-njs=${NGINX_VERSION}+${NJS_VERSION}-${NJS_RELEASE} \
    " \
    && echo "deb [signed-by=$NGINX_GPGKEY_PATH] https://nginx.org/packages/mainline/debian/ bookworm nginx" >> /etc/apt/sources.list.d/nginx.list \
        && apt-get update \
        && apt-get install --no-install-recommends --no-install-suggests -y \
            $nginxPackages \
            gettext-base \
            curl \
        #&& apt-get remove --purge --auto-remove -y \
        && rm -rf /var/lib/apt/lists/* /etc/apt/sources.list.d/nginx.list \
        # forward request and error logs to docker log collector
        && ln -sf /dev/stdout /var/log/nginx/access.log \
        && ln -sf /dev/stderr /var/log/nginx/error.log \
    # install php extensions
    # using mlocati/docker-php-extension-installer recomended by official php docker repo
    # @see https://github.com/docker-library/docs/blob/master/php/README.md#php-core-extensions
    && curl -sSLf -o /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
    pdo_mysql \
    json \
    gd \
    intl \
    mbstring \
    redis \
    zip \
    xml

# install composer from official image @see https://hub.docker.com/_/composer
COPY --from=composer:2.7.6 /usr/bin/composer /usr/bin/composer

FROM base as after-base

##
## Configuration of supervisord, php and nginx
##
# supervisord config
COPY ./docker/app/supervisord.conf /etc/supervisord.conf

# add custom php-fpm pool settings, these get written at entrypoint startup
ENV FPM_PM_MAX_CHILDREN=20 \
    FPM_PM_START_SERVERS=2 \
    FPM_PM_MIN_SPARE_SERVERS=1 \
    FPM_PM_MAX_SPARE_SERVERS=3 \
    FPM_PING_URI='/---ping' \
    NGINX_READ_TIMEOUT=60

### php-fpm config ###
COPY ./docker/app/zzz-app.conf /tmp/zzz-app.conf
# name of the file is `zzz-app.conf`, so that the directive in /usr/local/etc/php-fpm.conf `include=etc/php-fpm.d/*.conf`
# would include zzz-app.conf file as the latest and according php-fpm configuration overwrite any previous config files
RUN envsubst '$FPM_PM_MAX_CHILDREN $FPM_PM_START_SERVERS $FPM_PM_MIN_SPARE_SERVERS $FPM_PM_MAX_SPARE_SERVERS $FPM_PING_URI' < /tmp/zzz-app.conf > /usr/local/etc/php-fpm.d/zzz-app.conf

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
# custom php.ini
COPY ./docker/app/php-prod.ini /tmp/zzz-php.ini

### nginx config ###
COPY ./docker/app/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/app/nginx-site.conf /tmp/nginx-site.conf
RUN mkdir -p /etc/nginx/conf.d \
    && envsubst '$FPM_PING_URI $NGINX_READ_TIMEOUT' < /tmp/nginx-site.conf > /etc/nginx/conf.d/default.conf


### dev stage ###
FROM after-base as dev

WORKDIR /var/www/html

# Use development configuration (overwrite php.ini-production -- @see above)
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# custom php.ini
COPY ./docker/app/php-dev.ini /tmp/zzz-php.ini

### copy entrypoint ###
COPY ./docker/app/docker-php-entrypoint-dev /usr/local/bin/
RUN dos2unix /usr/local/bin/docker-php-entrypoint-dev \
    && chmod 770 /usr/local/bin/docker-php-entrypoint-dev


### dependencies stage ###
# for running installing non-dev dependencies of composer and npm ###
FROM base as dependencies

WORKDIR /var/www/html

##
## Compose Package Manager
##
COPY --chown=www-data:www-data ./composer.json ./composer.lock* ./
# RUN composer config github-oauth.github.com YOUROAUTHKEYHERE
# for some reason if you run on hosting with flag doctrine is not install
RUN composer install --no-scripts --no-autoloader --ansi --no-interaction

##
## Copy Other Files
##
COPY --chown=www-data:www-data ["./artisan", "./"]
COPY --chown=www-data:www-data ./bootstrap ./bootstrap
COPY --chown=www-data:www-data ./config ./config
COPY --chown=www-data:www-data ./database ./database
COPY --chown=www-data:www-data ./public ./public
COPY --chown=www-data:www-data ./resources/views ./resources/views
COPY --chown=www-data:www-data ./routes ./routes
COPY --chown=www-data:www-data ./src ./src
COPY --chown=www-data:www-data ./storage ./storage
COPY --chown=www-data:www-data ./.env ./.env

### copy entrypoint and overwrite default one of base php image ###
COPY ./docker/app/docker-php-entrypoint-prod /usr/local/bin/
RUN dos2unix /usr/local/bin/docker-php-entrypoint-prod \
    && chmod 770 /usr/local/bin/docker-php-entrypoint-prod

ENTRYPOINT ["docker-php-entrypoint-prod"]

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]

