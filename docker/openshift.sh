#!/bin/sh

# Print some details
echo "MySQL Connection Details:"
echo "Username: cms"
echo "Password: $MYSQL_PASSWORD"
echo "Host: $MYSQL_HOST:$MYSQL_PORT"
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
  
  echo ""
  echo "Maintenance-Key: ${MAINTENANCE_KEY}"
  echo ""
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
  
  echo ""
  echo "Maintenance-Key: ${MAINTENANCE_KEY}"
  echo ""
fi

# Define the secret if not done already defined
SECRET_KEY=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 8) && \
/bin/sed -i "s/define('SECRET_KEY','');/define('SECRET_KEY','$SECRET_KEY');/" /var/www/cms/web/settings.php

# Update /etc/periodic/cms-db.env with current environment (for cron)
echo "MYSQL_USER=$MYSQL_USER"         >  /etc/periodic/cms-db.env
echo "MYSQL_PASSWORD=$MYSQL_PASSWORD" >> /etc/periodic/cms-db.env
echo "MYSQL_HOST=$MYSQL_HOST"         >> /etc/periodic/cms-db.env
echo "MYSQL_PORT=$MYSQL_PORT"         >> /etc/periodic/cms-db.env
echo "MYSQL_DATABASE=$MYSQL_DATABASE" >> /etc/periodic/cms-db.env

# Install apache Crontab
crontab -u apache /etc/periodic/apache

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

echo "Running maintenance"
cd /var/www/cms
/usr/bin/php bin/run.php

#echo "Starting cron"
#/usr/sbin/crond
echo ""
echo "IMPORTANT:"
echo "  -->> Add a Lineness Check in Openshift <<--"
echo "        - Start after: 60 Second"
echo "        - Timeout:     60 Seconds"
echo "        - Command lines: "
echo "            1: /bin/bash"
echo "            2: -c"
echo "            3: /openshift_liveness.sh"
echo ""

echo "Starting webserver"
exec /usr/local/bin/httpd-foreground
