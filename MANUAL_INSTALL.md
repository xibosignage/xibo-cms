# Manual Installation

> **Docker is the only officially supported installation method for Xibo.**
> The following instructions cover self-hosted, manual installations that do not use Docker. Installations without Docker are community-supported only and are not covered by the official administration manual.

---

## Prerequisites

**PHP 8.1+** with the following extensions enabled:

| Extension | Notes |
|---|---|
| pdo + pdo_mysql | Database access |
| json | Required |
| soap | Required |
| gd | Image processing |
| fileinfo | Media type detection |
| pcre | Pattern matching |
| gettext | Translations |
| dom | XML handling |
| simplexml | XML handling |
| curl | HTTP client |
| openssl | Encryption |
| zip | Archive handling |

**Other requirements:**

- MySQL 8.0+
- Composer 2.x
- Node.js 12+ and npm (for building frontend assets)
- Apache with `mod_rewrite` (`.htaccess` is provided), or Nginx

**Recommended PHP configuration** (`php.ini`):

```ini
post_max_size = 128M
upload_max_filesize = 128M
max_execution_time = 120
allow_url_fopen = On
```

## 1. Get the code

```bash
git clone https://github.com/xibosignage/xibo-cms.git xibo-cms
cd xibo-cms

composer install --no-dev
npm install && npm run publish
```

## 2. Create writable directories

Choose a library path **outside** the web root. The web root is the `web/` subdirectory.

```bash
# Media library (outside web root)
mkdir -p /var/lib/xibo/library/fonts
chmod 755 /var/lib/xibo/library

# Application cache (inside repo, outside web root)
mkdir cache
chmod 755 cache
```

## 3. Create the MySQL database

```sql
CREATE DATABASE xibo CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'xibouser'@'localhost' IDENTIFIED BY 'strongpassword';
GRANT ALL PRIVILEGES ON xibo.* TO 'xibouser'@'localhost';
FLUSH PRIVILEGES;
```

---

## 4. Create `web/settings.php`

Create the file `web/settings.php` with your database credentials:

```php
<?php
defined('XIBO') or die('Direct access not permitted');

$dbhost = 'localhost';
$dbuser = 'xibouser';
$dbpass = 'strongpassword';
$dbname = 'xibo';
$dbport = '3306';

define('SECRET_KEY', 'REPLACE_WITH_RANDOM_12_CHARS');

if (file_exists(dirname(__FILE__) . '/settings-custom.php')) {
    include_once dirname(__FILE__) . '/settings-custom.php';
}
```

Replace the `SECRET_KEY` with a random 12-character alphanumeric string. This key signs inter-service communication and must be kept secret.

---

## 5. Run database migrations

```bash
vendor/bin/phinx migrate -c phinx.php
```

---

## 6. Set admin credentials

Connect to your MySQL database and run:

```sql
UPDATE `user`
   SET UserName    = 'xibo_admin',
       UserPassword = MD5('yourpassword'),
       UserEmail   = 'admin@example.com'
 WHERE UserID = 1 LIMIT 1;

UPDATE `group` SET `group` = 'xibo_admin' WHERE groupId = 3 LIMIT 1;
```

Replace `xibo_admin`, `yourpassword`, and `admin@example.com` with your chosen values.

---

## 7. Configure CMS settings

```sql
-- Path to the media library directory (trailing slash required)
UPDATE `setting` SET `value` = '/var/lib/xibo/library/'
 WHERE `setting` = 'LIBRARY_LOCATION';

-- Server key shared with media players during registration
UPDATE `setting` SET `value` = 'your_server_key'
 WHERE `setting` = 'SERVER_KEY';

-- Timezone for the CMS (PHP timezone identifier)
UPDATE `setting` SET `value` = 'Europe/London'
 WHERE `setting` = 'defaultTimezone';
```

`SERVER_KEY` is shared with media players during registration. Choose a short, memorable value, which is distinct from `SECRET_KEY`. 
Valid PHP timezone identifiers are listed at https://www.php.net/manual/en/timezones.php.

---

## 8. Configure the web server

Point the document root at the `web/` subdirectory, not the repository root.

### Apache

A `.htaccess` file is included in `web/`. Ensure `AllowOverride All` is set:

```apache
<VirtualHost *:80>
    ServerName xibo.example.com
    DocumentRoot /path/to/xibo-cms/web

    <Directory /path/to/xibo-cms/web>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 80;
    server_name xibo.example.com;
    root /path/to/xibo-cms/web;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## 9. Security settings

Set these in `web/settings-custom.php`, which the `settings.php` from step 4 includes. [SECURITY.md](SECURITY.md) has the reasoning for each.

| Setting | |
|---|---|
| `$whitelistHosts` | The hostnames the CMS is reachable under. Required in production. |
| `$trustedProxyIps` | The address your reverse proxy connects to the CMS from. Needed for per-client rate limiting, correct audit IPs, and `X-Forwarded-Proto` to be trusted. Never a wildcard or public range. |
| `$allowLocalNetworkRequests` | Leave unset. `true` disables SSRF protection on all outbound HTTP. |

```php
<?php
$whitelistHosts = 'xibo.example.com';
$trustedProxyIps = '10.0.0.5';
```

A reverse proxy terminating TLS must set `X-Forwarded-For` (its own value, not a relayed one) and `X-Forwarded-Proto`. Neither is trusted until the proxy's source address is in `$trustedProxyIps`. Get that address wrong with `FORCE_HTTPS` on and the CMS redirect-loops.

---

## Accessing the CMS

Navigate to your configured domain and log in with the admin credentials setup in step 6 of these instructions.

---

## Upgrading

1. Pull or unpack the new release over the existing files.
2. Run `composer install --no-dev` and `npm run publish` to update dependencies and assets.
3. Run `vendor/bin/phinx migrate -c phinx.php` to apply database migrations.
4. Clear the application cache: `rm -rf cache/*`
