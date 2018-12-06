# this is the dev container and doesn't contain CRON or composer
# it maps the PWD straight through

# Multi-stage build
# Stage 0
# Compile xsendfile apache module
FROM alpine:3.8 as sendfile
ADD docker/mod_xsendfile.c /mod_xsendfile.c
RUN apk update && apk upgrade && apk add \
        gcc \
        musl-dev \
        apache2-dev \
        apache2

RUN cd / && \
    apxs -cia mod_xsendfile.c

# Stage 1
# Run composer
FROM composer:1.6 as composer
COPY ./composer.json /app
COPY ./composer.lock /app

RUN composer install --no-interaction

# Stage 2
# Run webpack
FROM node:latest AS webpack
WORKDIR /app

# Install webpack
RUN npm install webpack -g

# Copy package.json and the webpack config file
COPY webpack.config.js .
COPY package.json .

# Install npm packages
RUN npm install --only=prod

# Copy ui folder
COPY ./ui ./ui

# Build webpack
RUN npm run build

# Stage 1
# Build the CMS container
FROM alpine:3.8
MAINTAINER Xibo Signage <support@xibosignage.com>

# Install apache, PHP, and supplimentary programs.
RUN apk update && apk upgrade && apk add tar \
    bash \
    curl \
    php7 \
    php7-apache2 \
    php7-zmq \
    php7-json \
    php7-gd \
    php7-mcrypt \
    php7-dom \
    php7-pdo \
    php7-zip \
    php7-pdo_mysql \
    php7-gettext \
    php7-soap \
    php7-iconv \
    php7-curl \
    php7-session \
    php7-ctype \
    php7-fileinfo \
    php7-xml \
    php7-simplexml \
    php7-mbstring \
    php7-memcached \
    php7-zlib \
    mysql-client \
    ssmtp \
    apache2 \
    ca-certificates \
    tzdata \
    && rm -rf /var/cache/apk/*

# Add all necessary config files in one layer
ADD docker/ /

# Adjust file permissions as appropriate
RUN chmod +x /entrypoint.sh /usr/local/bin/httpd-foreground /usr/local/bin/wait-for-command.sh \
             /etc/periodic/15min/cms-db-backup && \
    mkdir -p /run/apache2 && \
    rm /etc/apache2/conf.d/info.conf && \
    rm /etc/apache2/conf.d/userdir.conf && \
    chmod 777 /tmp

# Add xsendfile Module
COPY --from=sendfile /usr/lib/apache2/mod_xsendfile.so /usr/lib/apache2/mod_xsendfile.so

# Update the PHP.ini file
RUN sed -i "s/error_reporting = .*$/error_reporting = E_ERROR | E_WARNING | E_PARSE/" /etc/php7/php.ini && \
    sed -i "s/session.gc_probability = .*$/session.gc_probability = 1/" /etc/php7/php.ini && \
    sed -i "s/session.gc_divisor = .*$/session.gc_divisor = 100/" /etc/php7/php.ini

# Set some environment variables
ENV CMS_DEV_MODE=true \
    MYSQL_HOST=db \
    MYSQL_PORT=3306 \
    MYSQL_USER=root \
    MYSQL_PASSWORD=root \
    MYSQL_DATABASE=cms \
    CMS_SERVER_NAME=localhost \
    CMS_ALIAS=none \
    CMS_PHP_SESSION_GC_MAXLIFETIME=1440 \
    CMS_PHP_POST_MAX_SIZE=2G \
    CMS_PHP_UPLOAD_MAX_FILESIZE=2G \
    CMS_PHP_MAX_EXECUTION_TIME=300 \
    CMS_PHP_MEMORY_LIMIT=256M \
    CMS_APACHE_START_SERVERS=2 \
    CMS_APACHE_MIN_SPARE_SERVERS=5 \
    CMS_APACHE_MAX_SPARE_SERVERS=10 \
    CMS_APACHE_MAX_REQUEST_WORKERS=60 \
    CMS_APACHE_MAX_CONNECTIONS_PER_CHILD=300

# Expose port 80
EXPOSE 80

# Map the source files into /var/www/cms
# Create library and cache, because they might not exist
# Create /var/www/backup so that we have somewhere for entrypoint to log errors.
RUN mkdir -p /var/www/cms && \
    mkdir -p /var/www/cms/library/temp && \
    mkdir -p /var/www/cms/cache && \
    mkdir -p /var/www/backup

# Composer generated vendor files
COPY --from=composer /app /var/www/cms

# Copy dist built webpack app folder to web
COPY --from=webpack /app/web/dist /var/www/cms/web/dist

# All other files (.dockerignore excludes things we don't want)
COPY . /var/www/cms

# Run entry
CMD ["/entrypoint.sh"]
