# Multi-stage build
# Stage 0
# Compile xsendfile apache module
FROM alpine:3.15 as sendfile
ADD docker/mod_xsendfile.c /mod_xsendfile.c
RUN apk update && apk upgrade && apk add \
    gcc \
    musl-dev \
    apache2-dev \
    apache2

RUN cd / && \
    apxs -cia mod_xsendfile.c

# Stage 0.5
# Build PHP 7.4 from the security backports branch (Alpine no longer packages fixes for 7.4)
# PHP_SRC_REF is pinned to a reviewed commit; bump it when new backports land.
# 802c2107: GHSA-w8hw-w9fx-j79w (CVE-2026-109618), GHSA-6wmf-4rv5-p3rp (CVE-2026-109619)
FROM alpine:3.15 as php
ARG PHP_SRC_REF=802c21075308066362aab4993478dcd0577993a6
ADD https://github.com/remicollet/php-src-security/archive/${PHP_SRC_REF}.tar.gz /usr/src/php.tar.gz
COPY php-build/build-php.sh /usr/src/build-php.sh
RUN mkdir /usr/src/php && \
    tar -xzf /usr/src/php.tar.gz -C /usr/src/php --strip-components=1 && \
    rm /usr/src/php.tar.gz && \
    sh /usr/src/build-php.sh

# Stage 0.6
# Current Mozilla CA trust store.
# Alpine 3.15 is EOL and its ca-certificates (20230506) still trusts roots Mozilla has since removed,
# including those behind the October 2026 eMudhra intermediate CA key compromise.
FROM alpine:3.22 as cacerts
RUN apk add --no-cache ca-certificates

# Stage 1
# Run composer
FROM composer:1.6 as composer
COPY ./composer.json /app
COPY ./composer.lock /app

RUN composer install --no-interaction --no-dev --optimize-autoloader

# Tidy up
# remove non-required vendor files
WORKDIR /app/vendor
RUN find -type d -name '.git' -exec rm -r {} + && \
    find -path ./twig/twig/lib/Twig -prune -type d -name 'Test' -exec rm -r {} + && \
    find -type d -name 'tests' -depth -exec rm -r {} + && \
    find -type d -name 'benchmarks' -depth -exec rm -r {} + && \
    find -type d -name 'smoketests' -depth -exec rm -r {} + && \
    find -type d -name 'demo' -depth -exec rm -r {} + && \
    find -type d -name 'doc' -depth -exec rm -r {} + && \
    find -type d -name 'docs' -depth -exec rm -r {} + && \
    find -type d -name 'examples' -depth -exec rm -r {} + && \
    find -type f -name 'phpunit.xml' -exec rm -r {} + && \
    find -type f -name '*.md' -exec rm -r {} +


# Stage 2
# Run webpack
FROM node:12 AS webpack
WORKDIR /app

# Install webpack
RUN npm install webpack -g

# Copy package.json and the webpack config file
COPY webpack.config.js .
COPY package.json .
COPY package-lock.json .

# Install npm packages
RUN npm install --only=prod

# Copy ui folder
COPY ./ui ./ui

# Copy modules source folder
COPY ./modules/src ./modules/src

# Build webpack
RUN npm run publish

# Stage 3
# Build the CMS container
FROM alpine:3.15
MAINTAINER Xibo Signage <support@xibosignage.com>

# PHP built from source in the php stage
COPY --from=php /usr/local/bin/php /usr/local/bin/php
COPY --from=php /usr/local/lib/php /usr/local/lib/php
COPY --from=php /usr/local/php-run-deps /usr/local/php-run-deps
COPY --from=php /etc/php7 /etc/php7
COPY --from=php /usr/lib/apache2/mod_php7.so /usr/lib/apache2/mod_php7.so

# Install apache, PHP runtime libraries, and supplimentary programs.
RUN apk update && apk upgrade && apk add tar \
    bash \
    curl \
    $(cat /usr/local/php-run-deps) \
    mysql-client \
    msmtp \
    python2 \
    apache2 \
    ca-certificates \
    tzdata \
    openssl \
    && rm -rf /var/cache/apk/* \
    && ln -s /usr/local/bin/php /usr/bin/php \
    && ln -s /usr/local/bin/php /usr/bin/php7

# Replace the Alpine 3.15 Mozilla trust store with the current one
# (custom certificates from /var/www/cms/ca-certs are still merged in by the entrypoint)
COPY --from=cacerts /usr/share/ca-certificates/mozilla /tmp/mozilla-ca
COPY --from=cacerts /etc/ca-certificates.conf /etc/ca-certificates.conf
RUN rm -rf /usr/share/ca-certificates/mozilla && \
    mv /tmp/mozilla-ca /usr/share/ca-certificates/mozilla && \
    update-ca-certificates

# Add all necessary config files in one layer
ADD docker/ /

# Add xsendfile Module
COPY --from=sendfile /usr/lib/apache2/mod_xsendfile.so /usr/lib/apache2/mod_xsendfile.so

# Update the PHP.ini file
RUN sed -i "s/error_reporting = .*$/error_reporting = E_ERROR | E_WARNING | E_PARSE/" /etc/php7/php.ini && \
    sed -i "s/session.gc_probability = .*$/session.gc_probability = 1/" /etc/php7/php.ini && \
    sed -i "s/session.gc_divisor = .*$/session.gc_divisor = 100/" /etc/php7/php.ini && \
    sed -i "s/allow_url_fopen = .*$/allow_url_fopen = Off/" /etc/php7/php.ini && \
    sed -i "s/expose_php = .*$/expose_php = Off/" /etc/php7/php.ini

# Capture the git commit for this build if we provide one
ARG GIT_COMMIT=prod

# Setup persistent environment variables
ENV CMS_DEV_MODE=false \
    INSTALL_TYPE=docker \
    XMR_HOST=xmr \
    CMS_SERVER_NAME=localhost \
    MYSQL_HOST=mysql \
    MYSQL_USER=cms \
    MYSQL_PASSWORD=none \
    MYSQL_PORT=3306 \
    MYSQL_DATABASE=cms \
    MYSQL_BACKUP_ENABLED=true \
    MYSQL_ATTR_SSL_CA=none \
    MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=true \
    CMS_SMTP_SERVER=smtp.gmail.com:587 \
    CMS_SMTP_USERNAME=none \
    CMS_SMTP_PASSWORD=none \
    CMS_SMTP_USE_TLS=YES \
    CMS_SMTP_USE_STARTTLS=YES \
    CMS_SMTP_REWRITE_DOMAIN=gmail.com \
    CMS_SMTP_HOSTNAME=none \
    CMS_SMTP_FROM_LINE_OVERRIDE=YES \
    CMS_SMTP_FROM=none \
    CMS_ALIAS=none \
    CMS_PHP_SESSION_GC_MAXLIFETIME=1440 \
    CMS_PHP_POST_MAX_SIZE=2G \
    CMS_PHP_UPLOAD_MAX_FILESIZE=2G \
    CMS_PHP_MAX_EXECUTION_TIME=300 \
    CMS_PHP_MEMORY_LIMIT=256M \
    CMS_PHP_COOKIE_SECURE=Off \
    CMS_PHP_COOKIE_HTTP_ONLY=On \
    CMS_PHP_COOKIE_SAMESITE=Lax \
    CMS_APACHE_START_SERVERS=2 \
    CMS_APACHE_MIN_SPARE_SERVERS=5 \
    CMS_APACHE_MAX_SPARE_SERVERS=10 \
    CMS_APACHE_MAX_REQUEST_WORKERS=60 \
    CMS_APACHE_MAX_CONNECTIONS_PER_CHILD=300 \
    CMS_APACHE_TIMEOUT=30 \
    CMS_APACHE_OPTIONS_INDEXES=false \
    CMS_QUICK_CHART_URL=http://cms-quickchart:3400 \
    CMS_APACHE_SERVER_TOKENS=OS \
    CMS_APACHE_LOG_REQUEST_TIME=false \
    CMS_USE_MEMCACHED=false \
    MEMCACHED_HOST=memcached \
    MEMCACHED_PORT=11211 \
    CMS_USAGE_REPORT=true \
    XTR_ENABLED=true \
    GIT_COMMIT=$GIT_COMMIT

# Expose port 80
EXPOSE 80

# Map the source files into /var/www/cms
RUN mkdir -p /var/www/cms

# Composer generated vendor files
COPY --from=composer /app /var/www/cms

# Copy dist built webpack app folder to web
COPY --from=webpack /app/web/dist /var/www/cms/web/dist

# Copy modules built webpack app folder to cms modules
COPY --from=webpack /app/modules /var/www/cms/modules

# All other files (.dockerignore excludes many things, but we tidy up the rest below)
COPY --chown=apache:apache . /var/www/cms

# OpenOOH specification
ADD https://raw.githubusercontent.com/openooh/venue-taxonomy/main/specification.json /var/www/cms/openooh/specification.json

# Tidy up
RUN rm /var/www/cms/composer.* && \
    rm -r /var/www/cms/docker && \
    rm -r /var/www/cms/php-build && \
    rm -r /var/www/cms/tests && \
    rm /var/www/cms/.dockerignore && \
    rm /var/www/cms/phpunit.xml && \
    rm /var/www/cms/package.json && \
    rm /var/www/cms/package-lock.json && \
    rm /var/www/cms/cypress.config.js && \
    rm -r /var/www/cms/cypress && \
    rm -r /var/www/cms/ui && \
    rm /var/www/cms/webpack.config.js

# Map a volumes to this folder.
# Our CMS files, library, cache and backups will be in here.
RUN mkdir -p /var/www/cms/library/temp &&  \
    mkdir -p /var/www/backup && \
    mkdir -p /var/www/cms/cache && \
    mkdir -p /var/www/cms/web/userscripts && \
    chown -R apache:apache /var/www/cms && \
    chmod +x /entrypoint.sh /usr/local/bin/httpd-foreground /usr/local/bin/wait-for-command.sh \
    /etc/periodic/15min/cms-db-backup && \
    mkdir -p /run/apache2 && \
    rm /etc/apache2/conf.d/info.conf && \
    rm /etc/apache2/conf.d/userdir.conf && \
    ln -sf /usr/bin/msmtp /usr/sbin/sendmail && \
    chmod 777 /tmp

# Expose volume mount points
VOLUME /var/www/cms/library
VOLUME /var/www/cms/custom
VOLUME /var/www/cms/web/theme/custom
VOLUME /var/www/backup
VOLUME /var/www/cms/web/userscripts
VOLUME /var/www/cms/ca-certs

CMD ["/entrypoint.sh"]
