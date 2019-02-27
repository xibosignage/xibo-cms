#!/bin/sh

# Print some details for developers and non-root docker installations
if [ "$CMS_DEV_MODE" == "true" ] || [ "$NON_ROOT_DOCKER" == "true" ];
then
  echo "MySQL Connection Details:"
  echo "Username: $MYSQL_USER"
  echo "Password: $MYSQL_PASSWORD"
  echo "Host: $MYSQL_HOST:$MYSQL_PORT"
  echo ""
  echo "XMR Connection Details:"
  echo "Host: $XMR_HOST"
  echo "CMS Port: 50001"
  echo "Player Port: 9505"
  echo ""
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

# Check to see if we have a settings.php file in this container
# if we don't, then we will need to create one here (it only contains the $_SERVER environment
# variables we've already set
if [ ! -f "/var/www/cms/web/settings.php" ]
then
  # Write settings.php
  echo "Updating settings.php"

  # We won't have a settings.php in place, so we'll need to copy one in
  cp /tmp/settings.php-template /var/www/cms/web/settings.php
  chown apache.apache -R /var/www/cms/web/settings.php

  SECRET_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 8)
  /bin/sed -i "s/define('SECRET_KEY','');/define('SECRET_KEY','$SECRET_KEY');/" /var/www/cms/web/settings.php
fi

# Check if there's a database file to import
MAINTENANCE_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
if [ -f "/var/www/backup/import.sql" ] && [ "$CMS_DEV_MODE" == "false" ]
then
  echo "Importing Database" 
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "SOURCE /var/www/backup/import.sql"

  echo "Configuring Database Settings"
  # Set LIBRARY_LOCATION
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='/var/www/cms/library/', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='LIBRARY_LOCATION' LIMIT 1"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='Apache', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='SENDFILE_MODE' LIMIT 1"

  # Set XMR public/private address
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='tcp://$XMR_HOST:50001', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='XMR_ADDRESS' LIMIT 1"

  # Configure Maintenance
  echo "Setting up Maintenance"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='Protected' WHERE \`setting\`='MAINTENANCE_ENABLED' LIMIT 1"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='$MAINTENANCE_KEY' WHERE \`setting\`='MAINTENANCE_KEY' LIMIT 1"

  mv /var/www/backup/import.sql /var/www/backup/import.sql.done
  
  echo ""
  echo "Maintenance-Key: ${MAINTENANCE_KEY}"
  echo ""
fi

# Check if the database exists already
DB_EXISTS=0
if mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "SELECT settingId FROM \`setting\` LIMIT 1"
then
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
    mysqldump -h $MYSQL_HOST -P $MYSQL_PORT -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE | gzip > /var/www/backup/db-$(date +"%Y-%m-%d_%H-%M-%S").sql.gz

    # Drop app cache on upgrade
    rm -rf /var/www/cms/cache/*

    # Upgrade
    echo 'Running database migrations'
    /var/www/cms/vendor/bin/phinx migrate -c /var/www/cms/phinx.php
  fi
fi

if [ "$DB_EXISTS" == "0" ]
then
  # This is a fresh install so bootstrap the whole system
  echo "New install"
  echo "Provisioning Database"

  # Create the database if it doesn't exist
  mysql -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "CREATE DATABASE IF NOT EXISTS $MYSQL_DATABASE;"

  # Populate the database
  php /var/www/cms/vendor/bin/phinx migrate -c "/var/www/cms/phinx.php"

  echo "Configuring Database Settings"
  # Set LIBRARY_LOCATION
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='/var/www/cms/library/', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='LIBRARY_LOCATION' LIMIT 1"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='Apache', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='SENDFILE_MODE' LIMIT 1"

  # Set admin username/password
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`user\` SET \`UserName\`='xibo_admin', \`UserPassword\`='5f4dcc3b5aa765d61d8327deb882cf99' WHERE \`UserID\` = 1 LIMIT 1"

  # Set XMR public/private address
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='tcp://$XMR_HOST:50001', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='XMR_ADDRESS' LIMIT 1"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='tcp://cms.example.org:9505' WHERE \`setting\`='XMR_PUB_ADDRESS' LIMIT 1"

  # Set CMS Key
  CMS_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 8)
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='$CMS_KEY' WHERE \`setting\`='SERVER_KEY' LIMIT 1"

  # Configure Maintenance
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='Protected' WHERE \`setting\`='MAINTENANCE_ENABLED' LIMIT 1"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='$MAINTENANCE_KEY' WHERE \`setting\`='MAINTENANCE_KEY' LIMIT 1"
  
  echo ""
  echo "Maintenance-Key: ${MAINTENANCE_KEY}"
  echo ""
fi

# Update /etc/periodic/cms-db.env with current environment (for cron)
echo "MYSQL_USER=$MYSQL_USER"         >  /etc/periodic/cms-db.env
echo "MYSQL_PASSWORD=$MYSQL_PASSWORD" >> /etc/periodic/cms-db.env
echo "MYSQL_HOST=$MYSQL_HOST"         >> /etc/periodic/cms-db.env
echo "MYSQL_PORT=$MYSQL_PORT"         >> /etc/periodic/cms-db.env
echo "MYSQL_DATABASE=$MYSQL_DATABASE" >> /etc/periodic/cms-db.env

# Configure SSMTP to send emails if required
echo "root="                                  >  /etc/ssmtp/ssmtp.conf
echo "mailhub=$CMS_SMTP_SERVER"               >> /etc/ssmtp/ssmtp.conf
echo "UseTLS=$CMS_SMTP_USE_TLS"               >> /etc/ssmtp/ssmtp.conf
echo "UseSTARTTLS=$CMS_SMTP_USE_STARTTLS"     >> /etc/ssmtp/ssmtp.conf
echo "rewriteDomain=$CMS_SMTP_REWRITE_DOMAIN" >> /etc/ssmtp/ssmtp.conf
echo "hostname=$CMS_SMTP_HOSTNAME"            >> /etc/ssmtp/ssmtp.conf
echo "FromLineOverride=$CMS_SMTP_FROM_LINE_OVERRIDE" >> /etc/ssmtp/ssmtp.conf
if [ ! -z "$CMS_SMTP_USERNAME" ] && [ ! "$CMS_SMTP_USERNAME" == "none" ]
then
  echo "AuthUser=$CMS_SMTP_USERNAME" >> /etc/ssmtp/ssmtp.conf
  echo "AuthPass=$CMS_SMTP_PASSWORD" >> /etc/ssmtp/ssmtp.conf
fi

# Setup standard docker installations which are not built for Non-Root Docker environments like Openshift
if [ "$CMS_DEV_MODE" == "false" ] && [ "$NON_ROOT_DOCKER" != "true" ];
then
    # Import any ca-certificate files that might be needed to use a proxy etc
    echo "Importing ca-certs"
    cp -v /var/www/cms/ca-certs/*.pem /usr/local/share/ca-certificates
    cp -v /var/www/cms/ca-certs/*.crt /usr/local/share/ca-certificates
    /usr/sbin/update-ca-certificates

    # Update Crontab
    crontab -u apache /etc/crontabs/apache

    # Secure SSMTP files
    # Following recommendations here:
    # https://wiki.archlinux.org/index.php/SSMTP#Security
    /bin/chgrp ssmtp /etc/ssmtp/ssmtp.conf
    /bin/chgrp ssmtp /usr/sbin/ssmtp
    /bin/chmod 640 /etc/ssmtp/ssmtp.conf
    /bin/chmod g+s /usr/sbin/ssmtp

    mkdir -p /var/www/cms/library/temp
    chown apache.apache -R /var/www/cms

    # If we have a CMS ALIAS environment variable, then configure that in our Apache conf.
    # this must not be done in DEV mode, as it modifies the .htaccess file, which might then be committed by accident
    if [ ! "$CMS_ALIAS" == "none" ]
    then
        echo "Setting up CMS alias"
        /bin/sed -i "s|.*Alias.*$|Alias $CMS_ALIAS /var/www/cms/web|" /etc/apache2/conf.d/cms.conf
        /bin/sed -i "s|.*RewriteBase.*$|RewriteBase $CMS_ALIAS|" /var/www/cms/web/.htaccess
    fi

    # Remove install.php if it exists
    if [ -e /var/www/cms/web/install/index.php ]
    then
        echo "Removing web/install/index.php from production container"
        rm /var/www/cms/web/install/index.php
    fi
fi

if [ "$NON_ROOT_DOCKER" != "true" ];
then
  # Configure PHP session.gc_maxlifetime
  sed -i "s/session.gc_maxlifetime = .*$/session.gc_maxlifetime = $CMS_PHP_SESSION_GC_MAXLIFETIME/" /etc/php7/php.ini
  sed -i "s/post_max_size = .*$/post_max_size = $CMS_PHP_POST_MAX_SIZE/" /etc/php7/php.ini
  sed -i "s/upload_max_filesize = .*$/upload_max_filesize = $CMS_PHP_UPLOAD_MAX_FILESIZE/" /etc/php7/php.ini
  sed -i "s/max_execution_time = .*$/max_execution_time = $CMS_PHP_MAX_EXECUTION_TIME/" /etc/php7/php.ini
  sed -i "s/memory_limit = .*$/memory_limit = $CMS_PHP_MEMORY_LIMIT/" /etc/php7/php.ini
  
  # Run CRON in Production mode
  if [ "$CMS_DEV_MODE" == "false" ]
  then
    echo "Starting cron"
    /usr/sbin/crond
  fi
fi

# Define the secret if not done already defined
SECRET_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 8) && \
/bin/sed -i "s/define('SECRET_KEY','');/define('SECRET_KEY','$SECRET_KEY');/" /var/www/cms/web/settings.php

# If this is a initial run, save the secret also in custom settings if there is nothing defined
# This only makes sens in case /var/www/cms/custom/ is a VOLUME
if [ ! -f /var/www/cms/custom/settings-custom.php ];
then
   touch /var/www/cms/custom/settings-custom.php
fi
if [ -s /var/www/cms/custom/settings-custom.php ];
then
  echo "<?php" >> /var/www/cms/custom/settings-custom.php
  echo "define('SECRET_KEY','$SECRET_KEY');" >> /var/www/cms/custom/settings-custom.php
  echo "?>" >> /var/www/cms/custom/settings-custom.php
fi

echo "Running maintenance"
cd /var/www/cms
/usr/bin/php bin/run.php

if [ "$NON_ROOT_DOCKER" == "true" ];
then
  echo ""
  echo "IMPORTANT:"
  echo "  -->> Add a Lineness Check in Openshift <<--"
  echo "        - Start after: 60 Second"
  echo "        - Timeout:     60 Seconds"
  echo "        - Type:        Container Command"
  echo "        - Command lines: "
  echo "            1: /bin/bash"
  echo "            2: -c"
  echo "            3: cd /var/www/cms ; . /etc/periodic/cms-db.env ; /usr/bin/php bin/xtr.php > /dev/null 2>&1"
  echo ""
fi

echo "Starting webserver"
exec /usr/local/bin/httpd-foreground
