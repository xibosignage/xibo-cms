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
DEFINE('MAX_EXECUTION', true);

error_reporting(0);
ini_set('display_errors', 0);

require_once('lib/app/kit.class.php');
require_once('config/config.class.php');
require_once('config/db_config.php');
require_once("lib/app/app_functions.php");
require_once('lib/app/debug.class.php');
require_once("lib/app/pdoconnect.class.php");
require_once("lib/app/translationengine.class.php");
require_once("lib/app/thememanager.class.php");
require_once("lib/app/helpmanager.class.php");
require_once("lib/app/datemanager.class.php");
require_once("lib/app/formmanager.class.php");
require_once("lib/pages/base.class.php");
require_once('lib/pages/install.class.php');
require_once("lib/data/data.class.php");
require_once("lib/modules/module.interface.php");
require_once("lib/modules/module.class.php");
require_once("lib/modules/modulefactory.class.php");
require_once('modules/module_user_general.php');

// Create a theme
new Theme(new User(new Database()), 'default');
Theme::SetPagename('install');

// Does the settings file exist
$settingsExists = false;

if (file_exists('settings.php')) {
    include_once('settings.php');
    // Set-up the translations for get text
    TranslationEngine::InitLocale('en_GB');

    $settingsExists = true;
}
else {
    TranslationEngine::InitLocale();
}

// Define an auto-load function
spl_autoload_register(function ($class) {
    Kit::ClassLoader($class);
});

$xibo_step = Kit::GetParam('step', _REQUEST, _INT, 1);

$content = '';
$install = new Install();

switch ($xibo_step) {

    case 1:
        if ($settingsExists)
            die(__('The CMS has already been installed. Please contact your system administrator.'));

        // Welcome to the installer (this should only show once)
        // Checks environment
        $content = $install->Step1();
        break;

    case 2:
        if ($settingsExists)
            die(__('The CMS has already been installed. Please contact your system administrator.'));

        // Collect details about the database
        $content = $install->Step2();
        break;

    case 3:
        if ($settingsExists)
            die(__('The CMS has already been installed. Please contact your system administrator.'));

        // Check and validate DB details
        if (defined('MAX_EXECUTION') && MAX_EXECUTION)
            set_time_limit(0);

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
        require_once('lib/app/session.class.php');
        // Create a Session
        $session = new Session();
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
