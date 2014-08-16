<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2014 Alex Harrington and Daniel Garner
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
DEFINE('XIBO', true);

error_reporting(E_ALL);
ini_set('display_errors', 1);

include('lib/app/kit.class.php');
include('config/config.class.php');
include('config/db_config.php');
require_once("lib/app/pdoconnect.class.php");
require_once("lib/app/translationengine.class.php");
require_once("lib/app/thememanager.class.php");
require_once("lib/app/helpmanager.class.php");
require_once("lib/app/datemanager.class.php");
require_once("lib/app/formmanager.class.php");
require_once('modules/module_user_general.php');
require_once('install/install.class.php');

// Create a theme
new Theme(new User(new Database()), 'default');
Theme::SetPagename('install');


$config = new Config();
if (!$config->CheckPHP())
    die(sprintf('Xibo required PHP version %s.', Config::$VERSION_REQUIRED));

// Set-up the translations for get text
TranslationEngine::InitLocale('en_GB');

$fault = false;
$xibo_step = Kit::GetParam('step', _REQUEST, _INT, 1);

$content = '';
$install = new Install();

switch ($xibo_step) {

    case 1:
        // Welcome to the installer (this should only show once)
        // Checks environment
        $content = $install->Step1();
        break;

    case 2:
        // Collect details about the database
        $content = $install->Step2();
        break;

    case 3:
        // Check and validate DB details
        try {
            $install->Step3();

            // Redirect to step 4
            header('Location: install.php?step=4');
        }
        catch (Exception $e) {
            $install->errorMessage = $e->getMessage();

            // Reload step 2
            $content = $install->Step2();
        }
        break;

    case 4:
        // DB installed and we are ready to collect some more details
        // We should get the admin username and password
        $content = $install->Step4();
        break;

    case 5:
        // Create a user account
        include_once('settings.php');
        try {
            $install->Step5();

            // Redirect to step 6
            header('Location: install.php?step=6');
        }
        catch (Exception $e) {
            $install->errorMessage = $e->getMessage();

            // Reload step 4
            $content = $install->Step4();
        }
        break;

    case 6:
        $content = $install->Step6();
        break;

    case 7:
        // Create a user account
        include_once('settings.php');
        try {
            $install->Step7();

            // Redirect to step 6
            header('Location: install.php?step=8');
        }
        catch (Exception $e) {
            $install->errorMessage = $e->getMessage();

            // Reload step 4
            $content = $install->Step6();
        }
        break;

    case 8:
        include_once('settings.php');
        // Step 8 ends the execution
        $install->Step8();
        break;
}

Theme::Set('step', $xibo_step);
Theme::Set('stepContent', $content);

// Include the header
Theme::Render('install_header');
Theme::Render('install_footer');
?>
