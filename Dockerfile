# Multi-stage build
# Stage 0
# Compile xsendfile apache module
FROM alpine:3.6 as sendfile
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
COPY . /app
RUN composer install --no-interaction --no-dev --ignore-platform-reqs --optimize-autoloader

# Tidy up
# remove non-required vendor files and anything else we do not want in the archive and do not need for
# the next state
RUN rm /app/composer.* && \
    rm -r /app/docker && \
    rm .dockerignore

WORKDIR /app/vendor
RUN find -type d -name '.git' -exec rm -r {} + && \
  find -path ./twig/twig/lib/Twig -prune -type d -name 'Test' -exec rm -r {} + && \
  find -type d -name 'tests' -exec rm -r {} + && \
  find -type d -name 'benchmarks' -exec rm -r {} + && \
  find -type d -name 'smoketests' -exec rm -r {} + && \
  find -type d -name 'demo' -exec rm -r {} + && \
  find -type d -name 'doc' -exec rm -r {} + && \
  find -type d -name 'docs' -exec rm -r {} + && \
  find -type d -name 'examples' -exec rm -r {} + && \
  find -type f -name 'phpunit.xml' -exec rm -r {} + && \
  find -type f -name '*.md' -exec rm -r {} +


# Stage 2
# Run webpack
# todo: this will come in 2.0

# Stage 3
# Build the CMS container
FROM alpine:3.6
MAINTAINER Spring Signage <support@springsignage.com>

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

# Add xsendfile Module
COPY --from=sendfile /usr/lib/apache2/mod_xsendfile.so /usr/lib/apache2/mod_xsendfile.so

# Update the PHP.ini file
RUN sed -i "s/error_reporting = .*$/error_reporting = E_ERROR | E_WARNING | E_PARSE/" /etc/php7/php.ini && \
    sed -i "s/session.gc_probability = .*$/session.gc_probability = 1/" /etc/php7/php.ini && \
    sed -i "s/session.gc_divisor = .*$/session.gc_divisor = 100/" /etc/php7/php.ini

# Setup persistent environment variables
ENV CMS_DEV_MODE=false \
    XMR_HOST=xmr \
    CMS_DB_VERSION=139 \
    CMS_SERVER_NAME=localhost \
    MYSQL_HOST=mysql \
    MYSQL_USER=cms \
    MYSQL_PASSWORD=none \
    MYSQL_PORT=3306 \
    MYSQL_DATABASE=cms \
    CMS_SMTP_SERVER=smtp.gmail.com:587 \
    CMS_SMTP_USERNAME=none \
    CMS_SMTP_PASSWORD=none \
    CMS_SMTP_USE_TLS=YES \
    CMS_SMTP_USE_STARTTLS=YES \
    CMS_SMTP_REWRITE_DOMAIN=gmail.com \
    CMS_SMTP_HOSTNAME=none \
    CMS_SMTP_FROM_LINE_OVERRIDE=YES \
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
RUN mkdir -p /var/www/cms
COPY --from=composer /app /var/www/cms

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
    touch /CMS-FLAG && \
    addgroup ssmtp && \
    chmod 777 /tmp

# Expose volume mount points
VOLUME /var/www/cms/library
VOLUME /var/www/cms/custom
VOLUME /var/www/cms/web/theme/custom
VOLUME /var/www/backup
VOLUME /var/www/cms/web/userscripts

CMD ["/entrypoint.sh"]
