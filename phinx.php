<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

// We need to define XIBO as the settings.php file is protected with a !defined check.
if (!defined('XIBO')) {
    DEFINE('XIBO', true);
}

// Return the PHINX configuration object
// this should be based on our settings.php file
// in the case of a Docker installation the settings.php file will contain $_SERVER vars which we can use directly
// as they are environment variables in the container (and the phinx phar file will have them)
// in the case of a manual installation, these will be hard-coded in the settings file - in which case we can still
// use them.
require('web/settings.php');

// Our settings file exposes HOST:PORT rather that separate variables
if (strstr($dbhost, ':')) {
    $hostParts = explode(':', $dbhost);
    $dbhost = $hostParts[0];
    $dbport = $hostParts[1];
} else {
    $dbport = 3306;
}

$db = [
    'adapter' => 'mysql',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'host' => $dbhost,
    'port' => $dbport,
    'name' => $dbname,
    'user' => $dbuser,
    'pass' => $dbpass,
];

if (!empty($dbssl) && $dbssl !== 'none') {
    $db['mysql_attr_ssl_ca'] = $dbssl;
    $db['mysql_attr_ssl_verify_server_cert'] = $dbsslverify;
}

// Phinx formatted config array using the settings we've harvested from our settings.php file
return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations'
    ],
    'environments' => [
        'default_environment' => 'production',
        'production' => $db
    ],
    'feature_flags' => [
        'unsigned_primary_keys' => false,
        'column_null_default' => false,
    ],
];
