<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2010 Daniel Garner
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
 *
 *
 * OAuth-php include file.
 * Here we setup the XRDS header and initialize OAuth.
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

if (Debug::getLevel(Config::GetSetting('audit')) == 10)
    DEFINE('OAUTH_LOG_REQUEST', true);

// Output a discovery header
header('X-XRDS-Location:' . $serviceLocation . '/service.php?xrds');

require_once('3rdparty/oauth-php/library/OAuthServer.php');
require_once('3rdparty/oauth-php/library/OAuthStore.php');

OAuthStore::instance('PDO', array('conn' => PDOConnect::init()));

?>
