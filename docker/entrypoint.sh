#!/bin/bash

#
# Copyright (C) 2025 Xibo Signage Ltd
#
# Xibo - Digital Signage - https://xibosignage.com
#
# This file is part of Xibo.
#
# Xibo is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# any later version.
#
# Xibo is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
#

if [ "$CMS_DEV_MODE" == "true" ]
then
  # Print MySQL connection details
  echo "MySQL Connection Details:"
  echo "Username: $MYSQL_USER"
  echo "Password: $MYSQL_PASSWORD"
  echo "DB: $MYSQL_DATABASE"
  echo "Host: $MYSQL_HOST"
  echo ""
  echo "XMR Connection Details:"
  echo "Host: $XMR_HOST"
  echo "Player Port: 9505"
  echo ""
  echo "Starting Webserver"
fi

# Sleep for a few seconds to give MySQL time to initialise
echo "Waiting for MySQL to start - max 300 seconds"
/usr/local/bin/wait-for-command.sh -q -t 300 -c "nc -z $MYSQL_HOST $MYSQL_PORT"

if [ ! "$?" == 0 ]
then
  echo "MySQL didn't start in the allocated time" > /var/www/backup/LOG
fi

# Safety sleep to give MySQL a moment to settle after coming up
echo "MySQL started"
sleep 1

# Write a /root/.my.cnf file
echo "Configuring MySQL cnf file"
echo "[client]" > /root/.my.cnf
echo "host = $MYSQL_HOST" >> /root/.my.cnf
echo "port = $MYSQL_PORT" >> /root/.my.cnf
echo "user = $MYSQL_USER" >> /root/.my.cnf
echo "password = $MYSQL_PASSWORD" >> /root/.my.cnf

if [ ! "$MYSQL_ATTR_SSL_CA" == "none" ]
then
  echo "ssl_ca = $MYSQL_ATTR_SSL_CA" >> /root/.my.cnf

  if [ "$MYSQL_ATTR_SSL_VERIFY_SERVER_CERT" == "true" ]
  then
    echo "ssl_mode = VERIFY_IDENTITY" >> /root/.my.cnf
  fi
fi

# Set permissions on the new cnf file.
chmod 0600 /root/.my.cnf

# Check to see if we have a settings.php file in this container
# if we don't, then we will need to create one here (it only contains the $_SERVER environment
# variables we've already set
if [ ! -f "/var/www/cms/web/settings.php" ]
then
  # Write settings.php
  echo "Updating settings.php"

  # We won't have a settings.php in place, so we'll need to copy one in
  cp /tmp/settings.php-template /var/www/cms/web/settings.php
  chown www-data.www-data /var/www/cms/web/settings.php

  SECRET_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 8)
  /bin/sed -i "s/define('SECRET_KEY','');/define('SECRET_KEY','$SECRET_KEY');/" /var/www/cms/web/settings.php
fi

# Check to see if we have a public/private key pair and encryption key
if [ ! -f "/var/www/cms/library/certs/private.key" ]
then
  # Make the dir
  mkdir -p /var/www/cms/library/certs

  # Create the Keys
  openssl genrsa -out /var/www/cms/library/certs/private.key 2048
  openssl rsa -in /var/www/cms/library/certs/private.key -pubout -out /var/www/cms/library/certs/public.key

  php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;' >> /var/www/cms/library/certs/encryption.key
fi

# Set the correct permissions on the public/private key
chmod 600 /var/www/cms/library/certs/private.key
chmod 660 /var/www/cms/library/certs/public.key
chown -R www-data.www-data /var/www/cms/library/certs

# Check if there's a database file to import
if [ -f "/var/www/backup/import.sql" ] && [ "$CMS_DEV_MODE" == "false" ]
then
  echo "Attempting to import database"

  echo "Importing Database"
  mysql -D $MYSQL_DATABASE -e "SOURCE /var/www/backup/import.sql"

  echo "Configuring Database Settings"
  # Set LIBRARY_LOCATION
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='/var/www/cms/library/', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='LIBRARY_LOCATION' LIMIT 1"
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='Apache', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='SENDFILE_MODE' LIMIT 1"

  # Set XMR public/private address
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='http://$XMR_HOST:8081', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='XMR_ADDRESS' LIMIT 1"

  # Configure Maintenance
  echo "Setting up Maintenance"
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='Protected' WHERE \`setting\`='MAINTENANCE_ENABLED' LIMIT 1"

  MAINTENANCE_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='$MAINTENANCE_KEY' WHERE \`setting\`='MAINTENANCE_KEY' LIMIT 1"

  # Configure Quick Chart
  echo "Setting up Quickchart"
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='$CMS_QUICK_CHART_URL', userSee=0 WHERE \`setting\`='QUICK_CHART_URL' LIMIT 1"

  mv /var/www/backup/import.sql /var/www/backup/import.sql.done
fi

DB_EXISTS=0
# Check if the database exists already
if mysql -D $MYSQL_DATABASE -e "SELECT settingId FROM \`setting\` LIMIT 1"
then
  # Database exists.
  DB_EXISTS=1
fi

# Check if we need to run an upgrade
# if DB_EXISTS then see if the version installed matches
# only upgrade for production containers
if [ "$DB_EXISTS" == "1" ] && [ "$CMS_DEV_MODE" == "false" ]
then
  echo "Existing Database, checking if we need to upgrade it"
  # Determine if there are any migrations to be run
  /var/www/cms/vendor/bin/phinx status -c "/var/www/cms/phinx.php"

  if [ ! "$?" == 0 ]
  then
    echo "We will upgrade it, take a backup"

    # We're going to run an upgrade. Make a database backup
    mysqldump -h $MYSQL_HOST -P $MYSQL_PORT -u $MYSQL_USER -p$MYSQL_PASSWORD --hex-blob $MYSQL_DATABASE | gzip > /var/www/backup/db-$(date +"%Y-%m-%d_%H-%M-%S").sql.gz

    # Drop app cache on upgrade
    rm -rf /var/www/cms/cache/*

    # Upgrade
    echo 'Running database migrations'
    /var/www/cms/vendor/bin/phinx migrate -c /var/www/cms/phinx.php
  fi
fi

if [ "$DB_EXISTS" == "0" ]
then
  # This is a fresh install so bootstrap the whole
  # system
  echo "New install"

  echo "Provisioning Database"

  # Create the database if it doesn't exist
  mysql -e "CREATE DATABASE IF NOT EXISTS $MYSQL_DATABASE;"

  # Populate the database
  php /var/www/cms/vendor/bin/phinx migrate -c "/var/www/cms/phinx.php"

  CMS_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 8)

  echo "Configuring Database Settings"
  # Set LIBRARY_LOCATION
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='/var/www/cms/library/', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='LIBRARY_LOCATION' LIMIT 1"
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='Apache', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='SENDFILE_MODE' LIMIT 1"

  # Set admin username/password
  mysql -D $MYSQL_DATABASE -e "UPDATE \`user\` SET \`UserName\`='xibo_admin', \`UserPassword\`='5f4dcc3b5aa765d61d8327deb882cf99' WHERE \`UserID\` = 1 LIMIT 1"

  # Set XMR public address
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='tcp://cms.example.org:9505' WHERE \`setting\`='XMR_PUB_ADDRESS' LIMIT 1"

  # Set CMS Key
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='$CMS_KEY' WHERE \`setting\`='SERVER_KEY' LIMIT 1"

  # Configure Maintenance
  echo "Setting up Maintenance"

  if [ "$CMS_DEV_MODE" == "false" ]
  then
    echo "Protected Maintenance"
    mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='Protected' WHERE \`setting\`='MAINTENANCE_ENABLED' LIMIT 1"
  fi

  MAINTENANCE_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='$MAINTENANCE_KEY' WHERE \`setting\`='MAINTENANCE_KEY' LIMIT 1"
fi

if [ "$CMS_DEV_MODE" == "false" ]
then
    # Import any ca-certificate files that might be needed to use a proxy etc
    echo "Importing ca-certs"
    cp -v /var/www/cms/ca-certs/*.pem /usr/local/share/ca-certificates
    cp -v /var/www/cms/ca-certs/*.crt /usr/local/share/ca-certificates
    /usr/sbin/update-ca-certificates

    # Configure XMR private API
    echo "Setting up XMR private API"
    mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='http://$XMR_HOST:8081', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='XMR_ADDRESS' LIMIT 1"

    # Configure Quick Chart
    echo "Setting up Quickchart"
    mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='$CMS_QUICK_CHART_URL', userSee=0 WHERE \`setting\`='QUICK_CHART_URL' LIMIT 1"

    # Set the daily maintenance task to run
    mysql -D $MYSQL_DATABASE -e "UPDATE \`task\` SET \`runNow\`=1 WHERE \`taskId\`='1' LIMIT 1"

    # Update /etc/periodic/15min/cms-db-backup with current environment (for cron)
    /bin/sed -i "s/^MYSQL_BACKUP_ENABLED=.*$/MYSQL_BACKUP_ENABLED=$MYSQL_BACKUP_ENABLED/" /etc/periodic/15min/cms-db-backup
    /bin/sed -i "s/^MYSQL_DATABASE=.*$/MYSQL_DATABASE=$MYSQL_DATABASE/" /etc/periodic/15min/cms-db-backup

    echo "*/15 * * * * root /etc/periodic/15min/cms-db-backup > /dev/null 2>&1" > /etc/cron.d/cms_backup_cron
    echo "" >> /etc/cron.d/cms_backup_cron

    # Update /var/www/maintenance with current environment (for cron)
    if [ "$XTR_ENABLED" == "true" ]
    then
        echo "Configuring Maintenance"
        echo "#!/bin/bash" > /var/www/maintenance.sh
        echo "" >> /var/www/maintenance.sh
        /usr/bin/env | sed 's/^\(.*\)$/export \1/g' | grep -E "^export MYSQL" >> /var/www/maintenance.sh
        /usr/bin/env | sed 's/^\(.*\)$/export \1/g' | grep -E "^export MEMCACHED" >> /var/www/maintenance.sh
        echo "export CMS_USE_MEMCACHED=$CMS_USE_MEMCACHED" >> /var/www/maintenance.sh
        echo "cd /var/www/cms && /usr/bin/php bin/xtr.php" >> /var/www/maintenance.sh
        chmod 755 /var/www/maintenance.sh

        echo "* * * * *  www-data   /var/www/maintenance.sh > /dev/null 2>&1 " > /etc/cron.d/cms_maintenance_cron
        echo "" >> /etc/cron.d/cms_maintenance_cron
    fi

    # Configure MSMTP to send emails if required
    # Config lives in /etc/msmtprc

    # Split CMS_SMTP_SERVER in to CMS_SMTP_SEVER_HOST : PORT
    host_port=($(echo $CMS_SMTP_SERVER | tr ":" "\n"))

    /bin/sed -i "s/host .*$/host ${host_port[0]}/" /etc/msmtprc
    /bin/sed -i "s/port .*$/port ${host_port[1]}/" /etc/msmtprc

    if [ -z "$CMS_SMTP_USERNAME" ] || [ "$CMS_SMTP_USERNAME" == "none" ]
    then
      # Use no authentication
      /bin/sed -i "s/^auth .*$/auth off/" /etc/msmtprc
    else
      if [ -z "$CMS_SMTP_OAUTH_CLIENT_ID" ] || [ "$CMS_SMTP_OAUTH_CLIENT_ID" == "none" ]
      then
        # Use Username/Password
        /bin/sed -i "s/^auth .*$/auth on/" /etc/msmtprc
        /bin/sed -i "s/^user .*$/user $CMS_SMTP_USERNAME/" /etc/msmtprc
        /bin/sed -i "s/^password .*$/password $CMS_SMTP_PASSWORD/" /etc/msmtprc
      else
        # Use OAUTH credentials
        /bin/sed -i "s/^auth .*$/auth oauthbearer/" /etc/msmtprc
        /bin/sed -i "s/^user .*$/#user/" /etc/msmtprc
        /bin/sed -i "s/^password .*$/passwordeval \"/usr/bin/oauth2.py --quiet --user=$CMS_SMTP_USERNAME --client_id=$CMS_SMTP_OAUTH_CLIENT_ID --client_secret=$CMS_SMTP_OAUTH_CLIENT_SECRET --refresh_token=$CMS_SMTP_OAUTH_CLIENT_REFRESH\"/" /etc/msmtprc
      fi
    fi

    if [ "$CMS_SMTP_USE_TLS" == "YES" ]
    then
      /bin/sed -i "s/tls .*$/tls on/" /etc/msmtprc
    else
      /bin/sed -i "s/tls .*$/tls off/" /etc/msmtprc
    fi

    if [ "$CMS_SMTP_USE_STARTTLS" == "YES" ]
    then
      /bin/sed -i "s/tls_starttls .*$/tls_starttls on/" /etc/msmtprc
    else
      /bin/sed -i "s/tls_starttls .*$/tls_starttls off/" /etc/msmtprc
    fi

    /bin/sed -i "s/maildomain .*$/maildomain $CMS_SMTP_REWRITE_DOMAIN/" /etc/msmtprc
    /bin/sed -i "s/domain .*$/domain $CMS_SMTP_HOSTNAME/" /etc/msmtprc

    if [ "$CMS_SMTP_FROM" == "none" ]
    then
      /bin/sed -i "s/from .*$/from cms@$CMS_SMTP_REWRITE_DOMAIN/" /etc/msmtprc
    else
      /bin/sed -i "s/from .*$/from $CMS_SMTP_FROM/" /etc/msmtprc
    fi

    mkdir -p /var/www/cms/library/temp
    chown www-data:www-data -R /var/www/cms/library
    chown www-data:www-data -R /var/www/cms/custom
    chown www-data:www-data -R /var/www/cms/web/theme/custom
    chown www-data:www-data -R /var/www/cms/web/userscripts
    chown www-data:www-data -R /var/www/cms/ca-certs

    # If we have a CMS ALIAS environment variable, then configure that in our Apache conf.
    # this must not be done in DEV mode, as it modifies the .htaccess file, which might then be committed by accident
    if [ ! "$CMS_ALIAS" == "none" ]
    then
        echo "Setting up CMS alias"
        /bin/sed -i "s|.*Alias.*$|Alias $CMS_ALIAS /var/www/cms/web|" /etc/apache2/sites-enabled/000-default.conf

        echo "Settings up htaccess"
        /bin/cp /tmp/.htaccess /var/www/cms/web/.htaccess
        /bin/sed -i "s|REPLACE_ME|$CMS_ALIAS|" /var/www/cms/web/.htaccess
    fi

    if [ ! -e /var/www/cms/custom/settings-custom.php ]
    then
        /bin/cp /tmp/settings-custom.php /var/www/cms/custom
    fi

    # Remove install.php if it exists
    if [ -e /var/www/cms/web/install/index.php ]
    then
        echo "Removing web/install/index.php from production container"
        rm /var/www/cms/web/install/index.php
    fi
fi

# Configure Anonymous usage reporting
if [ "$CMS_USAGE_REPORT" == "true" ]
then
  # Turn on
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='1', userChange=0 WHERE \`setting\`='PHONE_HOME' LIMIT 1"
fi

if [ "$CMS_USAGE_REPORT" == "false" ]
then
  # Turn off
  mysql -D $MYSQL_DATABASE -e "UPDATE \`setting\` SET \`value\`='0', userChange=0 WHERE \`setting\`='PHONE_HOME' LIMIT 1"
fi

echo "Configure PHP"

# Configure PHP
sed -i "s/session.gc_maxlifetime = .*$/session.gc_maxlifetime = $CMS_PHP_SESSION_GC_MAXLIFETIME/" /etc/php/8.2/apache2/php.ini
sed -i "s/post_max_size = .*$/post_max_size = $CMS_PHP_POST_MAX_SIZE/" /etc/php/8.2/apache2/php.ini
sed -i "s/upload_max_filesize = .*$/upload_max_filesize = $CMS_PHP_UPLOAD_MAX_FILESIZE/" /etc/php/8.2/apache2/php.ini
sed -i "s/max_execution_time = .*$/max_execution_time = $CMS_PHP_MAX_EXECUTION_TIME/" /etc/php/8.2/apache2/php.ini
sed -i "s/memory_limit = .*$/memory_limit = $CMS_PHP_MEMORY_LIMIT/" /etc/php/8.2/apache2/php.ini
sed -i "s/session.cookie_httponly =.*$/session.cookie_httponly = $CMS_PHP_COOKIE_HTTP_ONLY/" /etc/php/8.2/apache2/php.ini
sed -i "s/session.cookie_samesite =.*$/session.cookie_samesite = $CMS_PHP_COOKIE_SAMESITE/" /etc/php/8.2/apache2/php.ini
sed -i "s/;session.cookie_secure =.*$/session.cookie_secure = $CMS_PHP_COOKIE_SECURE/" /etc/php/8.2/apache2/php.ini
sed -i "s/session.gc_maxlifetime = .*$/session.gc_maxlifetime = $CMS_PHP_SESSION_GC_MAXLIFETIME/" /etc/php/8.2/cli/php.ini
sed -i "s/post_max_size = .*$/post_max_size = $CMS_PHP_POST_MAX_SIZE/" /etc/php/8.2/cli/php.ini
sed -i "s/upload_max_filesize = .*$/upload_max_filesize = $CMS_PHP_UPLOAD_MAX_FILESIZE/" /etc/php/8.2/cli/php.ini
sed -i "s/max_execution_time = .*$/max_execution_time = $CMS_PHP_CLI_MAX_EXECUTION_TIME/" /etc/php/8.2/cli/php.ini
sed -i "s/memory_limit = .*$/memory_limit = $CMS_PHP_CLI_MEMORY_LIMIT/" /etc/php/8.2/cli/php.ini
sed -i "s/session.cookie_httponly =.*$/session.cookie_httponly = $CMS_PHP_COOKIE_HTTP_ONLY/" /etc/php/8.2/cli/php.ini
sed -i "s/session.cookie_samesite =.*$/session.cookie_samesite = $CMS_PHP_COOKIE_SAMESITE/" /etc/php/8.2/cli/php.ini
sed -i "s/;session.cookie_secure =.*$/session.cookie_secure = $CMS_PHP_COOKIE_SECURE/" /etc/php/8.2/cli/php.ini

echo "Configure Apache"

# Configure Apache TimeOut
sed -i "s/\bTimeout\b .*$/Timeout $CMS_APACHE_TIMEOUT/" /etc/apache2/apache2.conf

# Configure Indexes
if [ "$CMS_APACHE_OPTIONS_INDEXES" == "true" ]
then
  sed -i "s/\-Indexes/\+Indexes/" /etc/apache2/sites-enabled/000-default.conf
fi

# Configure Apache ServerTokens
if [ "$CMS_APACHE_SERVER_TOKENS" == "Prod" ]
then
  sed -i "s/ServerTokens.*$/ServerTokens Prod/" /etc/apache2/sites-enabled/000-default.conf
fi

# Configure Apache logging
if [ "$CMS_APACHE_LOG_REQUEST_TIME" == "true" ]
then
  sed -i '/combined/s/^/#/' /etc/apache2/sites-enabled/000-default.conf
else
  sed -i '/requesttime/s/^/#/' /etc/apache2/sites-enabled/000-default.conf
fi

# Run CRON in Production mode
if [ "$CMS_DEV_MODE" == "false" ]
then
    echo "Starting cron"
    /usr/sbin/cron
fi

echo "Starting webserver"
exec /usr/local/bin/httpd-foreground
