#!/bin/bash

#
# Copyright (C) 2026 Xibo Signage Ltd
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

# Container setup shared by entrypoint.sh and the Swarm scripts in pro/, which source it.

# Escapes a value for the replacement side of a sed s command.
sed_quote() {
  printf '%s' "$1" | sed -e 's/[\\/&|]/\\&/g'
}

# Escapes a value for a single-quoted SQL string.
sql_quote() {
  local s=${1//\\/\\\\}
  printf '%s' "${s//\'/\'\'}"
}

# Imports the ca-certs files into the system's trust, for example for a proxy or a database.
import_ca_certs() {
  echo "Importing ca-certs"
  local cert
  for cert in /var/www/cms/ca-certs/*.pem /var/www/cms/ca-certs/*.crt
  do
    if [ -e "$cert" ]
    then
      cp -v "$cert" /usr/local/share/ca-certificates || echo "WARNING: could not import $cert" >&2
    fi
  done
  /usr/sbin/update-ca-certificates || echo "WARNING: update-ca-certificates failed" >&2
}

# True if $target is absent, matches its recorded stock hash, or is untracked on a non-white-label install.
is_stock_or_absent() {
  local target="$1" hash_file="$2"
  [ ! -f "$target" ] && return 0
  if [ -f "$hash_file" ]; then
    [ "$(sha256sum "$target" | awk '{print $1}')" == "$(cat "$hash_file")" ]
    return $?
  fi
  [ "$brand_is_wl" = false ]
}

# Copies $source over $target and records its hash, unless $target was customised.
refresh_stock_file() {
  local target="$1" source="$2" hash_file="$3"
  is_stock_or_absent "$target" "$hash_file" || return 0
  cp "$source" "$target"
  sha256sum "$source" | awk '{print $1}' > "$hash_file"
}

# Never seeds the stock .svg beside a white-label .png logo, which wins.
refresh_stock_logo_asset() {
  local base="$1" hash_file="$2"
  local png="/var/www/cms/library/brand/$base.png"
  if [ -f "$png" ]; then
    return 0
  fi
  refresh_stock_file "/var/www/cms/library/brand/$base.svg" "/brand/$base.svg" "$hash_file"
}

# The Default Layout is built from layout-logo.png before logo-dark.png/logo.png, and layout-icon.png before
# logo-icon.png, and shows layout-thumbnail.png, so the stock (Xibo) layout image is only seeded for the stock brand. On a white-label brand, or one
# with its own PNG logo, it isn't seeded, and a stock copy seeded earlier is removed so the brand's logo is used.
refresh_stock_layout_image() {
  local base="$1" stock_hash_dir="$2"
  shift 2
  local target="/var/www/cms/library/brand/$base.png" hash_file="$stock_hash_dir/$base.png.sha256"

  local branded="$brand_is_wl" alt
  for alt in "$@"; do
    if [ -f "/var/www/cms/library/brand/$alt.png" ]; then
      branded=true
    fi
  done

  if [ "$branded" = true ]; then
    if [ -f "$target" ] && [ -f "$hash_file" ] \
        && [ "$(sha256sum "$target" | awk '{print $1}')" == "$(cat "$hash_file")" ]; then
      rm -f "$target" "$hash_file"
    fi
    return 0
  fi
  refresh_stock_file "$target" "/brand/$base.png" "$hash_file"
}

# Seeds library/brand from the image's /brand and refreshes the stock files nobody edited, going by their recorded
# hashes in library/.brand-stock, outside the publicly served /brand alias.
refresh_brand_files() {
  mkdir -p /var/www/cms/library/brand/layouts
  local stock_hash_dir=/var/www/cms/library/.brand-stock
  mkdir -p "$stock_hash_dir"

  # config.json marks a white-label install, whose untracked files count as customised.
  local brand_is_wl=false
  if [ -f /var/www/cms/library/brand/config.json ]; then
    brand_is_wl=true
  fi

  # Checked before the refresh, so the logo-dark decision sees the logo as it was.
  local main_logo_customised=true
  if [ ! -f /var/www/cms/library/brand/logo.png ] \
      && is_stock_or_absent /var/www/cms/library/brand/logo.svg "$stock_hash_dir/logo.sha256"; then
    main_logo_customised=false
  fi

  refresh_stock_logo_asset logo "$stock_hash_dir/logo.sha256"
  refresh_stock_logo_asset logo-icon "$stock_hash_dir/logo-icon.sha256"

  # Seeds the stock dark logo only while the main logo is stock.
  if [ "$main_logo_customised" = false ]; then
    refresh_stock_logo_asset logo-dark "$stock_hash_dir/logo-dark.sha256"
  fi

  local f
  for f in favicon.ico 192x192.png 512x512.png; do
    refresh_stock_file "/var/www/cms/library/brand/$f" "/brand/$f" "$stock_hash_dir/$f.sha256"
  done

  refresh_stock_layout_image layout-logo "$stock_hash_dir" logo-dark logo
  refresh_stock_layout_image layout-icon "$stock_hash_dir" logo-icon
  # The stock thumbnail shows the stock logo, so it goes with the stock layout logo.
  refresh_stock_layout_image layout-thumbnail "$stock_hash_dir" logo-dark logo

  # Seeds theme.css once, then leaves it to white-label builds.
  if [ ! -f /var/www/cms/library/brand/theme.css ]; then
    cp /brand/theme.css /var/www/cms/library/brand/theme.css
  fi

  if [ ! -f "/var/www/cms/library/brand/config.json" ]; then
    for f in full-screen.zip l-shape-left.zip l-shape-right.zip; do
      if [ ! -f "/var/www/cms/library/brand/layouts/$f" ]; then
        cp "/brand/layouts/$f" "/var/www/cms/library/brand/layouts/$f"
      fi
    done
  fi
  chown -R www-data:www-data /var/www/cms/library/brand
  chown -R www-data:www-data "$stock_hash_dir"
}

create_database() {
  mysql -e "CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE//\`/\`\`}\`;"
}

# Points an imported database at this library and protects its maintenance page, returning 1 if any update failed.
apply_import_settings() {
  local failed=0 maintenance_key
  mysql -D "$MYSQL_DATABASE" -e "UPDATE \`setting\` SET \`value\`='/var/www/cms/library/', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='LIBRARY_LOCATION' LIMIT 1" || failed=1
  mysql -D "$MYSQL_DATABASE" -e "UPDATE \`setting\` SET \`value\`='Apache', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='SENDFILE_MODE' LIMIT 1" || failed=1
  mysql -D "$MYSQL_DATABASE" -e "UPDATE \`setting\` SET \`value\`='Protected' WHERE \`setting\`='MAINTENANCE_ENABLED' LIMIT 1" || failed=1
  maintenance_key=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
  mysql -D "$MYSQL_DATABASE" -e "UPDATE \`setting\` SET \`value\`='$maintenance_key' WHERE \`setting\`='MAINTENANCE_KEY' LIMIT 1" || failed=1
  return "$failed"
}

# Creates and migrates a new install's database and writes its first settings, with $1 as the XMR public address.
# Returns 1 at once if the database can't be created or migrated, otherwise runs every setting and returns 1 if any failed.
provision_database() {
  local xmr_public_address=$1 failed=0 cms_key maintenance_key
  if ! create_database
  then
    echo "ERROR: could not create the database $MYSQL_DATABASE" >&2
    return 1
  fi
  if ! php /var/www/cms/vendor/bin/phinx migrate -c /var/www/cms/phinx.php
  then
    echo "ERROR: the database migrations failed" >&2
    return 1
  fi

  cms_key=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 8)

  # mysql doesn't print a failed statement, which matters here as they hold generated keys.
  echo "Configuring Database Settings"
  mysql -D "$MYSQL_DATABASE" -e "UPDATE \`setting\` SET \`value\`='/var/www/cms/library/', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='LIBRARY_LOCATION' LIMIT 1" || failed=1
  mysql -D "$MYSQL_DATABASE" -e "UPDATE \`setting\` SET \`value\`='Apache', \`userChange\`=0, \`userSee\`=0 WHERE \`setting\`='SENDFILE_MODE' LIMIT 1" || failed=1
  mysql -D "$MYSQL_DATABASE" -e "UPDATE \`user\` SET \`UserName\`='xibo_admin', \`UserPassword\`='5f4dcc3b5aa765d61d8327deb882cf99' WHERE \`UserID\` = 1 LIMIT 1" || failed=1
  mysql -D "$MYSQL_DATABASE" -e "UPDATE \`setting\` SET \`value\`='$(sql_quote "$xmr_public_address")' WHERE \`setting\`='XMR_PUB_ADDRESS' LIMIT 1" || failed=1
  mysql -D "$MYSQL_DATABASE" -e "UPDATE \`setting\` SET \`value\`='$cms_key' WHERE \`setting\`='SERVER_KEY' LIMIT 1" || failed=1

  echo "Setting up Maintenance"
  if [ "$CMS_DEV_MODE" == "false" ]
  then
    echo "Protected Maintenance"
    mysql -D "$MYSQL_DATABASE" -e "UPDATE \`setting\` SET \`value\`='Protected' WHERE \`setting\`='MAINTENANCE_ENABLED' LIMIT 1" || failed=1
  fi
  maintenance_key=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
  mysql -D "$MYSQL_DATABASE" -e "UPDATE \`setting\` SET \`value\`='$maintenance_key' WHERE \`setting\`='MAINTENANCE_KEY' LIMIT 1" || failed=1

  return "$failed"
}

# Writes /root/.my.cnf for the mysql and mysqldump clients.
write_my_cnf() {
  echo "Configuring MySQL cnf file"
  # Double-quoted with \ and " escaped, as an option file otherwise reads # as the start of a comment.
  local password=${MYSQL_PASSWORD//\\/\\\\}
  password=${password//\"/\\\"}
  {
    echo "[client]"
    echo "host = $MYSQL_HOST"
    echo "port = $MYSQL_PORT"
    echo "user = $MYSQL_USER"
    echo "password = \"$password\""
  } > /root/.my.cnf

  if [ ! "$MYSQL_ATTR_SSL_CA" == "none" ]
  then
    echo "ssl_ca = $MYSQL_ATTR_SSL_CA" >> /root/.my.cnf

    if [ "$MYSQL_ATTR_SSL_VERIFY_SERVER_CERT" == "true" ]
    then
      echo "ssl_mode = VERIFY_IDENTITY" >> /root/.my.cnf
    fi
  else
    # Without a CA the MariaDB 11 client refuses MySQL's self-signed default certificate unless verification is off.
    echo "ssl_verify_server_cert = 0" >> /root/.my.cnf
  fi
  chmod 0600 /root/.my.cnf
}

# Configures msmtp in /etc/msmtprc for outgoing email.
configure_msmtp() {
  local host_port
  # shellcheck disable=SC2207
  host_port=($(echo "$CMS_SMTP_SERVER" | tr ":" "\n"))

  /bin/sed -i "s/^host .*$/host ${host_port[0]}/" /etc/msmtprc
  /bin/sed -i "s/^port .*$/port ${host_port[1]}/" /etc/msmtprc

  if [ -z "$CMS_SMTP_USERNAME" ] || [ "$CMS_SMTP_USERNAME" == "none" ]
  then
    /bin/sed -i "s/^auth .*$/auth off/" /etc/msmtprc
  else
    if [ -z "$CMS_SMTP_OAUTH_CLIENT_ID" ] || [ "$CMS_SMTP_OAUTH_CLIENT_ID" == "none" ]
    then
      /bin/sed -i "s/^auth .*$/auth on/" /etc/msmtprc
      # Also matches the #user and passwordeval lines an earlier OAuth run left.
      /bin/sed -i "s/^#\?user\( .*\)\?$/user $(sed_quote "$CMS_SMTP_USERNAME")/" /etc/msmtprc
      /bin/sed -i "s/^password\(eval\)\? .*$/password $(sed_quote "$CMS_SMTP_PASSWORD")/" /etc/msmtprc
    else
      /bin/sed -i "s/^auth .*$/auth oauthbearer/" /etc/msmtprc
      /bin/sed -i "s/^user .*$/#user/" /etc/msmtprc
      # Uses '|' as the delimiter, because the replacement holds a path.
      /bin/sed -i "s|^password\(eval\)\? .*$|passwordeval \"/usr/bin/oauth2.py --quiet --user=$(sed_quote "$CMS_SMTP_USERNAME") --client_id=$(sed_quote "$CMS_SMTP_OAUTH_CLIENT_ID") --client_secret=$(sed_quote "$CMS_SMTP_OAUTH_CLIENT_SECRET") --refresh_token=$(sed_quote "$CMS_SMTP_OAUTH_CLIENT_REFRESH")\"|" /etc/msmtprc
    fi
  fi

  if [ "$CMS_SMTP_USE_TLS" == "YES" ]
  then
    /bin/sed -i "s/^tls .*$/tls on/" /etc/msmtprc
  else
    /bin/sed -i "s/^tls .*$/tls off/" /etc/msmtprc
  fi

  if [ "$CMS_SMTP_USE_STARTTLS" == "YES" ]
  then
    /bin/sed -i "s/^tls_starttls .*$/tls_starttls on/" /etc/msmtprc
  else
    /bin/sed -i "s/^tls_starttls .*$/tls_starttls off/" /etc/msmtprc
  fi

  # Anchored, as "domain" would also match the maildomain line.
  /bin/sed -i "s/^maildomain .*$/maildomain $(sed_quote "$CMS_SMTP_REWRITE_DOMAIN")/" /etc/msmtprc
  /bin/sed -i "s/^domain .*$/domain $(sed_quote "$CMS_SMTP_HOSTNAME")/" /etc/msmtprc

  if [ "$CMS_SMTP_FROM" == "none" ]
  then
    /bin/sed -i "s/^from .*$/from cms@$(sed_quote "$CMS_SMTP_REWRITE_DOMAIN")/" /etc/msmtprc
  else
    /bin/sed -i "s/^from .*$/from $(sed_quote "$CMS_SMTP_FROM")/" /etc/msmtprc
  fi
}

# Points Apache and .htaccess at CMS_ALIAS, unless it is none.
configure_cms_alias() {
  if [ ! "$CMS_ALIAS" == "none" ]
  then
    # Replaces only the web alias, keeping the /brand alias.
    echo "Setting up CMS alias"
    /bin/sed -i \
      "/Alias.*\/brand/! s|.*Alias.*$|Alias $(sed_quote "$CMS_ALIAS") /var/www/cms/web|" \
      /etc/apache2/sites-enabled/000-default.conf

    echo "Settings up htaccess"
    /bin/cp /tmp/.htaccess /var/www/cms/web/.htaccess
    /bin/sed -i "s|REPLACE_ME|$(sed_quote "$CMS_ALIAS")|" /var/www/cms/web/.htaccess
  fi
}

# Applies the CMS_PHP_* settings to the Apache and CLI php.ini.
configure_php() {
  echo "Configure PHP"
  local ini
  for ini in /etc/php/8.4/apache2/php.ini /etc/php/8.4/cli/php.ini
  do
    local max_execution_time=$CMS_PHP_MAX_EXECUTION_TIME memory_limit=$CMS_PHP_MEMORY_LIMIT
    if [ "$ini" == "/etc/php/8.4/cli/php.ini" ]
    then
      max_execution_time=$CMS_PHP_CLI_MAX_EXECUTION_TIME
      memory_limit=$CMS_PHP_CLI_MEMORY_LIMIT
    fi
    sed -i "s/session.gc_maxlifetime = .*$/session.gc_maxlifetime = $CMS_PHP_SESSION_GC_MAXLIFETIME/" "$ini"
    sed -i "s/post_max_size = .*$/post_max_size = $CMS_PHP_POST_MAX_SIZE/" "$ini"
    sed -i "s/upload_max_filesize = .*$/upload_max_filesize = $CMS_PHP_UPLOAD_MAX_FILESIZE/" "$ini"
    sed -i "s/max_execution_time = .*$/max_execution_time = $max_execution_time/" "$ini"
    sed -i "s/memory_limit = .*$/memory_limit = $memory_limit/" "$ini"
    sed -i "s/session.cookie_httponly =.*$/session.cookie_httponly = $CMS_PHP_COOKIE_HTTP_ONLY/" "$ini"
    sed -i "s/session.cookie_samesite =.*$/session.cookie_samesite = $CMS_PHP_COOKIE_SAMESITE/" "$ini"
    sed -i "s/;session.cookie_secure =.*$/session.cookie_secure = $CMS_PHP_COOKIE_SECURE/" "$ini"
  done
}

# Applies the CMS_APACHE_* settings.
configure_apache() {
  echo "Configure Apache"

  sed -i "s/\bTimeout\b .*$/Timeout $CMS_APACHE_TIMEOUT/" /etc/apache2/apache2.conf

  if [ "$CMS_APACHE_OPTIONS_INDEXES" == "true" ]
  then
    sed -i "s/\-Indexes/\+Indexes/" /etc/apache2/sites-enabled/000-default.conf
  fi

  if [ "$CMS_APACHE_SERVER_TOKENS" == "Prod" ]
  then
    sed -i "s/ServerTokens.*$/ServerTokens Prod/" /etc/apache2/sites-enabled/000-default.conf
  fi

  if [ "$CMS_APACHE_LOG_REQUEST_TIME" == "true" ]
  then
    sed -i '/combined/s/^/#/' /etc/apache2/sites-enabled/000-default.conf
  else
    sed -i '/requesttime/s/^/#/' /etc/apache2/sites-enabled/000-default.conf
  fi
}
