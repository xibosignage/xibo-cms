<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class Install {

    public $errorMessage;

    // DB Details
    private $db_create;
    private $db_admin_user;
    private $db_admin_pass;
    private $new_db_host;
    private $new_db_user;
    private $new_db_pass;
    private $new_db_name;
    private $existing_db_host;
    private $existing_db_user;
    private $existing_db_pass;
    private $existing_db_name;

    public function Step1() {
        Theme::Set('form_action', 'install.php');
        // Check environment
        $config = new Config();

        $environment = $config->CheckEnvironment();

        $formFields = array();
        $formButtons = array();
        $formFields[] = FormManager::AddMessage(sprintf(__("First we need to check if your server meets %s's requirements."), Theme::GetConfig('app_name')));

        $formFields[] = FormManager::AddRaw($environment);

        if ($config->EnvironmentFault()) {
            $formFields[] = FormManager::AddHidden('step', 1);
            $formButtons[] = FormManager::AddButton(__('Retest'));
        }
        else if ($config->EnvironmentWarning()) {
            $formFields[] = FormManager::AddHidden('step', 2);
            $formButtons[] = FormManager::AddButton(__('Retest'), 'link', 'install.php?step=1');
            $formButtons[] = FormManager::AddButton(__('Next'));
        }
        else {
            $formFields[] = FormManager::AddHidden('step', 2);
            $formButtons[] = FormManager::AddButton(__('Next'));
        }

        // Return a rendered form
        Theme::Set('form_fields', $formFields);
        Theme::Set('form_buttons', $formButtons);
        return Theme::RenderReturn('form_render');
    }

    public function Step2() {
        Theme::Set('form_action', 'install.php');
        // Choice of new or existing database
        // Tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('new', __('Create a new database'));
        $tabs[] = FormManager::AddTab('existing', __('Use an existing database'));
        Theme::Set('form_tabs', $tabs);

        $formFields = array();

        // Set some defaults
        $this->db_create = ($this->db_create == '') ? 1 : $this->db_create;
        $this->new_db_host = ($this->new_db_host == '') ? 'localhost' : $this->new_db_host;
        $this->db_admin_user = ($this->db_admin_user == '') ? 'root' : $this->db_admin_user;

        // New DB tab
        $formFields['new'][] = FormManager::AddHidden('step', 3);

        $formFields['new'][] = FormManager::AddMessage(sprintf(__("%s needs to set-up a connection to your MySQL database."), Theme::GetConfig('app_name')));

        $formFields['new'][] = FormManager::AddMessage(__('If you have not yet created an empty database and database user for Xibo to use, and know the user name / password of a MySQL administrator stay on this tab, otherwise click "Use Existing".'));

        $formFields['new'][] = FormManager::AddRadio('db_create', 'db_create1', __('Create a new database'), $this->db_create, 1,
            __('Select to create a new database'), 'c');

        $formFields['new'][] = FormManager::AddText('host', __('Host'), $this->new_db_host, 
            __('Please enter the hostname for the MySQL server. This is usually localhost.'), 'h');

        $formFields['new'][] = FormManager::AddText('admin_username', __('Admin Username'), $this->db_admin_user, 
            __('Please enter the user name of an account that has administrator privileges on the MySQL server.'), 'h');

        $formFields['new'][] = FormManager::AddPassword('admin_password', __('Admin Password'), $this->db_admin_pass, 
            __('Please enter password for the Admin account.'), 'h');

        $formFields['new'][] = FormManager::AddText('db_name', __('Database Name'), $this->new_db_name, 
            __('Please enter the name of the database that should be created.'), 'h');

        $formFields['new'][] = FormManager::AddText('db_username', __('Database Username'), $this->new_db_user, 
            __('Please enter the name of the database user that should be created.'), 'h');

        $formFields['new'][] = FormManager::AddPassword('db_password', __('Database Password'), $this->new_db_pass, 
            __('Please enter a password for this user.'), 'h');

        // Existing DB tab
        $formFields['existing'][] = FormManager::AddRadio('db_create', 'db_create2', __('Use an existing database'), $this->db_create, 2,
            __('Select to use an existing database. Please note that when you use an existing database it must be empty of all other contents.'), 'e');

        $formFields['existing'][] = FormManager::AddText('existing_host', __('Host'), $this->existing_db_host, 
            __('Please enter the hostname for the MySQL server. This is usually localhost.'), 'h');

        $formFields['existing'][] = FormManager::AddText('existing_db_name', __('Database Name'), $this->existing_db_name, 
            __('Please enter the name of the database that should be created.'), 'h');

        $formFields['existing'][] = FormManager::AddText('existing_db_username', __('Database Username'), $this->existing_db_user, 
            __('Please enter the name of the database user that should be created.'), 'h');

        $formFields['existing'][] = FormManager::AddPassword('existing_db_password', __('Database Password'), $this->existing_db_pass, 
            __('Please enter a password for this user.'), 'h');

        // Put up an error message if one has been set (and then unset it)
        if ($this->errorMessage != '') {
            Theme::Set('message', $this->errorMessage);
            Theme::Set('prepend', Theme::RenderReturn('message_box'));
            $this->errorMessage == '';
        }

        // Return a rendered form
        Theme::Set('form_fields_new', $formFields['new']);
        Theme::Set('form_fields_existing', $formFields['existing']);
        Theme::Set('form_buttons', array(FormManager::AddButton(__('Next'))));
        return Theme::RenderReturn('form_render');
    }

    public function Step3() {

        // Have we been told to create a new database
        $this->db_create = Kit::GetParam('db_create', _POST, _INT);

        // Check all parameters have been specified
        $this->db_admin_user = Kit::GetParam('admin_username', _POST, _PASSWORD);
        $this->db_admin_pass = Kit::GetParam('admin_password', _POST, _PASSWORD);

        $this->new_db_host = Kit::GetParam('host', _POST, _STRING);
        $this->new_db_user = Kit::GetParam('db_username', _POST, _PASSWORD);
        $this->new_db_pass = Kit::GetParam('db_password', _POST, _PASSWORD);
        $this->new_db_name = Kit::GetParam('db_name', _POST, _PASSWORD);

        $this->existing_db_host = Kit::GetParam('existing_host', _POST, _STRING);
        $this->existing_db_user = Kit::GetParam('existing_db_username', _POST, _PASSWORD);
        $this->existing_db_pass = Kit::GetParam('existing_db_password', _POST, _PASSWORD);
        $this->existing_db_name = Kit::GetParam('existing_db_name', _POST, _PASSWORD);

        // If an administrator user name / password has been specified then we should create a new DB
        if ($this->db_create == 1) {
            // Check details for a new database
            if ($this->new_db_host == '')
                throw new Exception(__('Please provide a database host. This is usually localhost.'));
            
            if ($this->new_db_user == '')
                throw new Exception(__('Please provide a user for the new database.'));

            if ($this->new_db_pass == '')
                throw new Exception(__('Please provide a password for the new database.'));

            if ($this->new_db_name == '')
                throw new Exception(__('Please provide a name for the new database.'));

            if ($this->db_admin_user == '')
                throw new Exception(__('Please provide an admin user name.'));

            // Try to create the new database
            // Try and connect using these details and create the new database
            try {
                $dbh = PDOConnect::connect($this->new_db_host, $this->db_admin_user, $this->db_admin_pass);             
            }
            catch (Exception $e) {
                throw new Exception(sprintf(__('Could not connect to MySQL with the administrator details. Please check and try again. Error Message = [%s]'), $e->getMessage()));
            }

            // Try to create the new database
            try {
                $dbh = PDOConnect::init();
                $dbh->exec(sprintf('CREATE DATABASE `%s`', $this->new_db_name));
            }
            catch (Exception $e) {
                throw new Exception(sprintf(__('Could not create a new database with the administrator details [%s]. Please check and try again. Error Message = [%s]'), $this->db_admin_user, $e->getMessage()));
            }

            // Try to create the new user
            try {
                $dbh = PDOConnect::init();
            
                // Create the user and grant privileges
                if ($this->new_db_host == 'localhost') {
                    $dbh->exec(sprintf('GRANT ALL PRIVILEGES ON `%s`.* to %s@%s IDENTIFIED BY %s',
                        $this->new_db_name,
                        $dbh->quote($this->new_db_user),
                        $dbh->quote($this->new_db_host),
                        $dbh->quote($this->new_db_pass))
                    );
                }
                else {
                    $dbh->exec(sprintf("GRANT ALL PRIVILEGES ON `%s`.* to %s@%% IDENTIFIED BY %s",
                        $this->new_db_name,
                        $dbh->quote($this->new_db_user),
                        $dbh->quote($this->new_db_pass))
                    );
                }

                // Flush
                $dbh->exec('FLUSH PRIVILEGES');
            }
            catch (Exception $e) {
                throw new Exception(sprintf(__('Could not create a new user with the administrator details. Please check and try again. Error Message = [%s]'), $e->getMessage()));
            }

            // Set our DB details
            $this->existing_db_host = $this->new_db_host;
            $this->existing_db_user = $this->new_db_user;
            $this->existing_db_pass = $this->new_db_pass;
            $this->existing_db_name = $this->new_db_name;

            // Close the connection
            PDOConnect::close();
        }
        else {
            // Check details for a new database
            if ($this->existing_db_host == '')
                throw new Exception(__('Please provide a database host. This is usually localhost.'));
            
            if ($this->existing_db_user == '')
                throw new Exception(__('Please provide a user for the existing database.'));

            if ($this->existing_db_pass == '')
                throw new Exception(__('Please provide a password for the existing database.'));

            if ($this->existing_db_name == '')
                throw new Exception(__('Please provide a name for the existing database.'));
        }

        // Try and make a connection with this database
        try {
            $dbh = PDOConnect::connect($this->existing_db_host, $this->existing_db_user, $this->existing_db_pass, $this->existing_db_name);             
        }
        catch (Exception $e) {
            throw new Exception(sprintf(__('Could not connect to MySQL with the administrator details. Please check and try again. Error Message = [%s]'), $e->getMessage()));
        }

        // We should have a database that we can access and populate with our tables.
        $sql_files = array('structure.sql', 'data.sql');
        $sqlStatementCount = 0;
        $sql_file = '';
        $sql = '';

        try {
            $dbh = PDOConnect::init();
        
            foreach ($sql_files as $filename) {
                $delimiter = ';';
                $sql_file = @file_get_contents('install/master/' . $filename);
                $sql_file = Install::remove_remarks($sql_file);
                $sql_file = Install::split_sql_file($sql_file, $delimiter);
                
                foreach ($sql_file as $sql) {
                    $sqlStatementCount++;

                    $dbh->exec($sql);
                }
            }
        }
        catch (Exception $e) {
            throw new Exception(sprintf(__('An error occurred populating the database. Statement number: %d. Error Message = [%s]. File = [%s]. SQL = [%s].'), $sqlStatementCount, $e->getMessage(), $sql_file, $sql));
        }
        
        // Write out a new settings.php
        $fh = fopen('settings.php', 'wt');

        if (!$fh)
            throw new Exception(__('Unable to write to settings.php. We already checked this was possible earlier, so something changed.'));

        // Generate a secret key for various reasons
        $secretKey = Install::gen_secret();

        // Escape the password before we write it to disk
        $dbh = PDOConnect::init();
        $existing_db_pass = addslashes($this->existing_db_pass);

        $settings = <<<END
<?php

/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo - and is automatically generated by the installer
 *
 * You should not need to edit this file, unless your SQL connection details have changed.
 */

defined('XIBO') or die(__("Sorry, you are not allowed to directly access this page.") . "<br />" . __("Please press the back button in your browser."));

global \$dbhost;
global \$dbuser;
global \$dbpass;
global \$dbname;

\$dbhost = '$this->existing_db_host';
\$dbuser = '$this->existing_db_user';
\$dbpass = '$existing_db_pass';
\$dbname = '$this->existing_db_name';

define('SECRET_KEY', '$secretKey');

END;

        if (!fwrite($fh, $settings))
            throw new Exception(__('Unable to write to settings.php. We already checked this was possible earlier, so something changed.'));

        fclose($fh);

        // If we get here, we want to move on to the next step. 
        // This is handled by the calling function (i.e. there is no output from this call, we just reload and move on)
    }

    public function Step4() {
        // Form to collect an admin user account and password.
        $formFields = array();

        $formFields[] = FormManager::AddHidden('step', 5);

        $formFields[] = FormManager::AddMessage(sprintf(__("%s needs an administrator user account to be the first user account that has access to the CMS. Please enter your chosen details below."), Theme::GetConfig('app_name')));

        // User name and password
        $formFields[] = FormManager::AddText('admin_username', __('Admin Username'), NULL, 
            __('Please enter a user name for the first administrator account.'), 'n');

        $formFields[] = FormManager::AddPassword('admin_password', __('Admin Password'), NULL, 
            __('Please enter a password for this user. This user will have full access to the system'), 'p');

        // Put up an error message if one has been set (and then unset it)
        if ($this->errorMessage != '') {
            Theme::Set('message', $this->errorMessage);
            Theme::Set('prepend', Theme::RenderReturn('message_box'));
            $this->errorMessage == '';
        }

        // Return a rendered form
        Theme::Set('form_action', 'install.php');
        Theme::Set('form_fields', $formFields);
        Theme::Set('form_buttons', array(FormManager::AddButton(__('Next'))));
        return Theme::RenderReturn('form_render');
    }

    public function Step5() {
        // Configure the user account
        $username = Kit::GetParam('admin_username', _POST, _STRING);
        $password = Kit::GetParam('admin_password', _POST, _PASSWORD);

        if ($username == '')
            throw new Exception(__('Missing the admin username.'));

        if ($password == '')
            throw new Exception(__('Missing the admin password.'));

        // Update user id 1 with these details.
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('UPDATE `user` SET UserName = :username, UserPassword = :password WHERE UserID = 1 LIMIT 1');
            $sth->execute(array(
                    'username' => $username,
                    'password' => md5($password)
                ));

            // Update group ID 3 with the user name
            $sth = $dbh->prepare('UPDATE `group` SET `group` = :username WHERE groupId = 3 LIMIT 1');
            $sth->execute(array(
                    'username' => $username
                ));
        }
        catch (Exception $e) {
            throw new Exception(sprintf(__('Unable to set the user details. This is an unexpected error, please contact support. Error Message = [%s]'), $e->getMessage()));
        }
    }

    public function Step6() {
        // Form to collect the library location and server key
        $formFields = array();
        $formFields[] = FormManager::AddHidden('step', 7);

        $formFields[] = FormManager::AddText('library_location', __('Library Location'), NULL, 
            sprintf(__('%s needs somewhere to store the things you upload to be shown. Ideally, this should be somewhere outside the root of your web server - that is such that is not accessible by a web browser. Please input the full path to this folder. If the folder does not already exist, we will attempt to create it for you.'), Theme::GetConfig('app_name')), 'n');

        $formFields[] = FormManager::AddText('server_key', __('Server Key'), Install::gen_secret(6), 
            sprintf(__('%s needs you to choose a "key". This will be required each time you set-up a new client. It should be complicated, and hard to remember. It is visible in the CMS interface, so it need not be written down separately.'), Theme::GetConfig('app_name')), 'n');

        $formFields[] = FormManager::AddCheckbox('stats', __('Statistics'), 1, 
            sprintf(__('We\'d love to know you\'re running %s. If you\'re happy for us to collect anonymous statistics (version number, number of displays) then please leave the box ticked. Please un tick the box if your server does not have direct access to the internet.'), Theme::GetConfig('app_name')), 'n');

        // Put up an error message if one has been set (and then unset it)
        if ($this->errorMessage != '') {
            Theme::Set('message', $this->errorMessage);
            Theme::Set('prepend', Theme::RenderReturn('message_box'));
            $this->errorMessage == '';
        }

        // Return a rendered form
        Theme::Set('form_action', 'install.php');
        Theme::Set('form_fields', $formFields);
        Theme::Set('form_buttons', array(FormManager::AddButton(__('Next'))));
        return Theme::RenderReturn('form_render');
    }

    public function Step7() {
        $server_key = Kit::GetParam('server_key', _POST, _STRING);
        $library_location = Kit::GetParam('library_location', _POST, _STRING);
        $stats = Kit::GetParam('stats', _POST, _CHECKBOX);

        if ($server_key == '')
            throw new Exception(__('Missing the server key.'));

        if ($library_location == '')
            throw new Exception(__('Missing the library location.'));

        // Remove trailing white space from the path given.
        $library_location = trim($library_location);

        if (! is_dir($library_location)) {
            // Make sure they haven't given a file as the library location
            if (is_file($library_location))
                throw new Exception(__('A file exists with the name you gave for the Library Location. Please choose another location'));

            // Directory does not exist. Attempt to make it
            // Using mkdir recursively, so it will attempt to make any
            // intermediate folders required.
            if (!mkdir($library_location, 0755, true)) {
                throw new Exception(__('Could not create the Library Location directory for you. Please ensure the webserver has permission to create a folder in this location, or create the folder manually and grant permission for the webserver to write to the folder.'));
            }
        }

        // Is library_location writable?
        if (!is_writable($library_location))
            throw new Exception(__('The Library Location you gave is not writable by the webserver. Please fix the permissions and try again.'));

        // Is library_location empty?
        if (count(Install::ls("*",$library_location,true)) > 0)
            throw new Exception(__('The Library Location you gave is not empty. Please give the location of an empty folder'));

        // Check if the user has added a trailing slash. If not, add one.
        if (!((substr($library_location, -1) == '/') || (substr($library_location, -1) == '\\'))) {
            $library_location = $library_location . '/';
        }

        try {
            $dbh = PDOConnect::init();
        
            // Library Location
            $sth = $dbh->prepare('UPDATE `setting` SET `value` = :value WHERE `setting`.`setting` = \'LIBRARY_LOCATION\' LIMIT 1');
            $sth->execute(array('value' => $library_location));
            
            // Server Key
            $sth = $dbh->prepare('UPDATE `setting` SET `value` = :value WHERE `setting`.`setting` = \'SERVER_KEY\' LIMIT 1');
            $sth->execute(array('value' => $server_key));

            // Default Time zone
            $sth = $dbh->prepare('UPDATE `setting` SET `value` = :value WHERE `setting`.`setting` = \'defaultTimezone\' LIMIT 1');
            $sth->execute(array('value' => date_default_timezone_get()));

            // Phone Home
            $sth = $dbh->prepare('UPDATE `setting` SET `value` = :value WHERE `setting`.`setting` = \'PHONE_HOME\' LIMIT 1');
            $sth->execute(array('value' => (($stats == 1) ? 'On' : 'Off')));

            // Phone Home Key
            $sth = $dbh->prepare('UPDATE `setting` SET `value` = :value WHERE `setting`.`setting` = \'PHONE_HOME_KEY\' LIMIT 1');
            $sth->execute(array('value' => md5(uniqid(rand(), true))));
        }
        catch (Exception $e) {
            throw new Exception(sprintf(__('An error occurred updating these settings. This is an unexpected error, please contact support. Error Message = [%s]'), $e->getMessage()));
        }
    }

    public function Step8() {

        PDOConnect::init();

        // Define the VERSION
        Config::Version();
        
        Theme::Set('form_action', 'index.php?q=login');
        Theme::Set('about_url', 'index.php?p=index&q=About');
        Theme::Set('source_url', Theme::SourceLink());

        // Message (either from the URL or the session)
        Theme::Set('login_message', sprintf(__("%s was successfully installed. Please log-in with the user details you chose earlier."), Theme::GetConfig('app_name')));

        Theme::Render('login_page');

        // Install files
        Media::installAllModuleFiles();

        // Delete install
        if (!unlink('install.php'))
            throw new Exception(__("Unable to delete install.php. Please ensure the webserver has permission to unlink this file and retry"));

        exit();
    }

    /*
     *  Third party classes
     */

    // Taken from http://forums.devshed.com/php-development-5/php-wont-load-sql-from-file-515902.html
    // By Crackster 
    /**
     * remove_remarks will strip the sql comment lines out of an uploaded sql file
     */
    public static function remove_remarks($sql){
        $sql = preg_replace('/\n{2,}/', "\n", preg_replace('/^[-].*$/m', "\n", $sql));
        $sql = preg_replace('/\n{2,}/', "\n", preg_replace('/^#.*$/m', "\n", $sql));
        return $sql;
    }

    // Taken from http://forums.devshed.com/php-development-5/php-wont-load-sql-from-file-515902.html
    // By Crackster              
    /**
     * split_sql_file will split an uploaded sql file into single sql statements.
     * Note: expects trim() to have already been run on $sql.
     */
    public static function split_sql_file($sql, $delimiter){
        $sql = str_replace("\r" , '', $sql);
        $data = preg_split('/' . preg_quote($delimiter, '/') . '$/m', $sql);
        $data = array_map('trim', $data);
        // The empty case
        $end_data = end($data);
        if (empty($end_data)) {
            unset($data[key($data)]);
        }
        return $data;
    }
     
    /**
     * This function will take a pattern and a folder as the argument and go thru it(recursively if needed)and return the list of 
     *               all files in that folder.
     * Link             : http://www.bin-co.com/php/scripts/filesystem/ls/
     * License  : BSD
     * Arguments     :  $pattern - The pattern to look out for [OPTIONAL]
     *                    $folder - The path of the directory of which's directory list you want [OPTIONAL]
     *                    $recursivly - The funtion will traverse the folder tree recursivly if this is true. Defaults to false. [OPTIONAL]
     *                    $options - An array of values 'return_files' or 'return_folders' or both
     * Returns       : A flat list with the path of all the files(no folders) that matches the condition given.
     */
    public static function ls($pattern="*", $folder="", $recursivly=false, $options=array('return_files','return_folders')) {
        if($folder) {
            $current_folder = realpath('.');
            if(in_array('quiet', $options)) { // If quiet is on, we will suppress the 'no such folder' error
                if(!file_exists($folder)) return array();
            }
            
            if(!chdir($folder)) return array();
        }
        
        
        $get_files    = in_array('return_files', $options);
        $get_folders= in_array('return_folders', $options);
        $both = array();
        $folders = array();
        
        // Get the all files and folders in the given directory.
        if($get_files) $both = glob($pattern, GLOB_BRACE + GLOB_MARK);
        if($recursivly or $get_folders) $folders = glob("*", GLOB_ONLYDIR + GLOB_MARK);
        
        //If a pattern is specified, make sure even the folders match that pattern.
        $matching_folders = array();
        if($pattern !== '*') $matching_folders = glob($pattern, GLOB_ONLYDIR + GLOB_MARK);
        
        //Get just the files by removing the folders from the list of all files.
        $all = array_values(array_diff($both,$folders));
            
        if($recursivly or $get_folders) {
            foreach ($folders as $this_folder) {
                if($get_folders) {
                    //If a pattern is specified, make sure even the folders match that pattern.
                    if($pattern !== '*') {
                        if(in_array($this_folder, $matching_folders)) array_push($all, $this_folder);
                    }
                    else array_push($all, $this_folder);
                }
                
                if($recursivly) {
                    // Continue calling this function for all the folders
                    $deep_items = Install::ls($pattern, $this_folder, $recursivly, $options); # :RECURSION:
                    foreach ($deep_items as $item) {
                        array_push($all, $this_folder . $item);
                    }
                }
            }
        }
        
        if($folder) chdir($current_folder);
        return $all;
    }

    public static function gen_secret($length = 12) {
      # Generates a random 12 character alphanumeric string to use as a salt
      mt_srand((double)microtime()*1000000);
      $key = "";
      for ($i=0; $i < $length; $i++) {
        $c = mt_rand(0,2);
        if ($c == 0) {
          $key .= chr(mt_rand(65,90));
        }
        elseif ($c == 1) {
          $key .= chr(mt_rand(97,122));
        }
        else {
          $key .= chr(mt_rand(48,57));
        }
      } 
      
      return $key;
    }
}