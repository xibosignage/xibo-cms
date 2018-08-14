#!/bin/sh

# Print some details
echo "MySQL Connection Details:"
echo "Username: cms"
echo "Password: $MYSQL_PASSWORD"
echo "Host: mysql"
echo ""
echo "XMR Connection Details:"
echo "Host: $XMR_HOST"
echo "CMS Port: 50001"
echo "Player Port: 9505"
echo ""

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

# Check if there's a database file to import
if [ -f "/var/www/backup/import.sql" ]
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
  MAINTENANCE_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='Protected' WHERE \`setting\`='MAINTENANCE_ENABLED' LIMIT 1"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='$MAINTENANCE_KEY' WHERE \`setting\`='MAINTENANCE_KEY' LIMIT 1"

  mv /var/www/backup/import.sql /var/www/backup/import.sql.done
fi

# Check if we need to run an upgrade
# if DB_EXISTS then see if the version installed matches
# only upgrade for production containers
if mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "SELECT DBVersion from version"
then
  echo "Existing Database, checking if we need to upgrade it"
  # Get the currently installed schema version number
  CURRENT_DB_VERSION=$(mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -se 'SELECT DBVersion from version')

  if [ ! "$CURRENT_DB_VERSION"  == "$CMS_DB_VERSION" ]
  then
    echo "We will upgrade it, take a backup"
    mysqldump -h $MYSQL_HOST -P $MYSQL_PORT -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE | gzip > /var/www/backup/db-$(date +"%Y-%m-%d_%H-%M-%S").sql.gz

    # Drop app cache on upgrade
    rm -rf /var/www/cms/cache/*
  fi
else
  # This is a fresh install so bootstrap the whole system
  echo "Provisioning Database"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "SOURCE /var/www/cms/install/master/structure.sql"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "SOURCE /var/www/cms/install/master/data.sql"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "SOURCE /var/www/cms/install/master/constraints.sql"

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
  MAINTENANCE_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='Protected' WHERE \`setting\`='MAINTENANCE_ENABLED' LIMIT 1"
  mysql -D $MYSQL_DATABASE -u $MYSQL_USER -p$MYSQL_PASSWORD -h $MYSQL_HOST -P $MYSQL_PORT -e "UPDATE \`setting\` SET \`value\`='$MAINTENANCE_KEY' WHERE \`setting\`='MAINTENANCE_KEY' LIMIT 1"
fi

# Define the secret if not done already defined
SECRET_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 8) && \
/bin/sed -i "s/define('SECRET_KEY','');/define('SECRET_KEY','$SECRET_KEY');/" /var/www/cms/web/settings.php

# Update /etc/periodic/cms-db-backup.env with current environment (for cron)
/bin/sed -i "s/^MYSQL_USER=.*$/MYSQL_USER=$MYSQL_USER/"             /etc/periodic/cms-db-backup.env
/bin/sed -i "s/^MYSQL_PASSWORD=.*$/MYSQL_PASSWORD=$MYSQL_PASSWORD/" /etc/periodic/cms-db-backup.env
/bin/sed -i "s/^MYSQL_HOST=.*$/MYSQL_HOST=$MYSQL_HOST/"             /etc/periodic/cms-db-backup.env
/bin/sed -i "s/^MYSQL_PORT=.*$/MYSQL_PORT=$MYSQL_PORT/"             /etc/periodic/cms-db-backup.env
/bin/sed -i "s/^MYSQL_DATABASE=.*$/MYSQL_DATABASE=$MYSQL_DATABASE/" /etc/periodic/cms-db-backup.env

# Install apache Crontab
crontab -u apache /etc/periodic/apache

# Configure SSMTP to send emails if required
/bin/sed -i "s/mailhub=.*$/mailhub=$CMS_SMTP_SERVER/" /etc/ssmtp/ssmtp.conf
if [ -z "$CMS_SMTP_USERNAME" ] || [ "$CMS_SMTP_USERNAME" == "none" ]
then
  /bin/sed -i "s/^#*AuthUser=.*$/#AuthUser=/" /etc/ssmtp/ssmtp.conf
  /bin/sed -i "s/^#*AuthPass=.*$/#AuthPass=/" /etc/ssmtp/ssmtp.conf
else
  /bin/sed -i "s/^#*AuthUser=.*$/AuthUser=$CMS_SMTP_USERNAME/" /etc/ssmtp/ssmtp.conf
  /bin/sed -i "s/^#*AuthPass=.*$/AuthPass=$CMS_SMTP_PASSWORD/" /etc/ssmtp/ssmtp.conf
fi

/bin/sed -i "s/UseTLS=.*$/UseTLS=$CMS_SMTP_USE_TLS/" /etc/ssmtp/ssmtp.conf
/bin/sed -i "s/UseSTARTTLS=.*$/UseSTARTTLS=$CMS_SMTP_USE_STARTTLS/" /etc/ssmtp/ssmtp.conf
/bin/sed -i "s/rewriteDomain=.*$/rewriteDomain=$CMS_SMTP_REWRITE_DOMAIN/" /etc/ssmtp/ssmtp.conf
/bin/sed -i "s/hostname=.*$/hostname=$CMS_SMTP_HOSTNAME/" /etc/ssmtp/ssmtp.conf
/bin/sed -i "s/FromLineOverride=.*$/FromLineOverride=$CMS_SMTP_FROM_LINE_OVERRIDE/" /etc/ssmtp/ssmtp.conf

# If we have a CMS ALIAS environment variable, then configure that in our Apache conf.
# this must not be done in DEV mode, as it modifies the .htaccess file, which might then be committed by accident
if [ ! "$CMS_ALIAS" == "none" ]
then
	echo "Setting up CMS alias"
	/bin/sed -i "s|.*Alias.*$|Alias $CMS_ALIAS /var/www/cms/web|" /etc/apache2/conf.d/cms.conf
	/bin/sed -i "s|.*RewriteBase.*$|RewriteBase $CMS_ALIAS|" /var/www/cms/web/.htaccess
fi

# Configure PHP session.gc_maxlifetime
/bin/sed -i "s/session.gc_maxlifetime = .*$/session.gc_maxlifetime = $CMS_PHP_SESSION_GC_MAXLIFETIME/" /etc/php7/php.ini
/bin/sed -i "s/post_max_size = .*$/post_max_size = $CMS_PHP_POST_MAX_SIZE/" /etc/php7/php.ini
/bin/sed -i "s/upload_max_filesize = .*$/upload_max_filesize = $CMS_PHP_UPLOAD_MAX_FILESIZE/" /etc/php7/php.ini
/bin/sed -i "s/max_execution_time = .*$/max_execution_time = $CMS_PHP_MAX_EXECUTION_TIME/" /etc/php7/php.ini
/bin/sed -i "s/memory_limit = .*$/memory_limit = $CMS_PHP_MEMORY_LIMIT/" /etc/php7/php.ini

echo "Running maintenance"
cd /var/www/cms
su -s /bin/bash -c 'cd /var/www/cms && /usr/bin/php bin/run.php 1' apache

echo "Starting cron"
/usr/sbin/crond

echo "Starting webserver"
exec /usr/local/bin/httpd-foreground
