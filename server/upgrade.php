<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2013 Alex Harrington
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

if (! checkPHP()) {
  die("Xibo requires PHP 5.3.3 or later");
}

error_reporting(0);
ini_set('display_errors', 0);

require_once("lib/app/pdoconnect.class.php");
include('lib/app/kit.class.php');
include("lib/data/data.class.php");
include('lib/app/debug.class.php');
include('config/db_config.php');
include('config/config.class.php');

// Setup the translations for gettext
require_once("lib/app/translationengine.class.php");

// Once we've calculated the upgrade in step 2 below, we need
// to have included the appropriate upgrade php files
// before we restore the session, so objects get recreated properly.
//
// Check to see if we've passed that point, and if so look at what was posted
// to include those classes.

if (Kit::GetParam("includes", _POST, _BOOL)) {
	for ($i=$_POST['upgradeFrom'] + 1; $i <= $_POST['upgradeTo']; $i++) {
		if (file_exists('install/database/' . $i . '.php')) {
			include_once('install/database/' . $i . '.php');
		}
	}
}

session_start();

Config::Load();

// create a database class instance
$db = new database();

if (!$db->connect_db($dbhost, $dbuser, $dbpass)) 
	reportError(0, __("Unable to connect to the MySQL database using the settings stored in settings.php.") . "<br /><br />" . __("MySQL Error:") . "<br />" . $db->error());
if (!$db->select_db($dbname)) 
	reportError(0, __("Unable to select the MySQL database using the settings stored in settings.php.") . "<br /><br />" . __("MySQL Error:") . "<br />" . $db->error());

// Initialise the Translations
set_error_handler(array(new Debug(), "ErrorHandler"));

TranslationEngine::InitLocale($db);

include('install/header_upgrade.inc');

if (!isset($_SESSION['step'])) {
	$_SESSION['step'] = 0;
}

if (Kit::GetParam('skipstep',_POST,_INT) == 1) {
	// Cheat the $_SESSION['step'] variable if required
	// Used if there are environment warnings and we want to retest.
	$_SESSION['step'] = 1;
}

if (Kit::GetParam('reset', _GET, _INT) == 1) {
	$_SESSION['step'] = 0;
	$_SESSION['auth'] = null;
}

if ($_SESSION['step'] == 0) {

  $_SESSION['step'] = 1;

  # First step of the process.
  # Show a welcome screen and authenticate the user
  ?>
  <?php echo __("Welcome to the Xibo Upgrade!"); ?><br /><br />
  <?php echo __("The upgrade program will take you through the process one step at a time."); ?><br /><br />
  <?php echo __("Lets get started!"); ?><br /><br />
  <?php echo __("Please enter your xibo_admin password:"); ?><br /><br />
  <form action="upgrade.php" method="POST">
    <div class="install_table">
	<input type="password" name="password" length="12" />
    </div>
    <div class="loginbutton"><button type="submit"><?php echo __("Next"); ?> ></button></div>
  </form>
  <?php
}
elseif ($_SESSION['step'] == 1) {
  	$_SESSION['step'] = 2;
  
  	if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {

		# Check password
		$username = 'xibo_admin';
		$password = Kit::GetParam('password', _POST, _PASSWORD);

		// Decide what user authentication mode to use depending on the current version.
		if (Config::Version('DBVersion') < 62) {

			// Old auth
			$password_hash = md5($password);
			
			$SQL = sprintf("SELECT `UserID` FROM `user` WHERE UserPassword='%s' AND UserName='xibo_admin'", $db->escape_string($password_hash));

	    	if (! $result = $db->query($SQL)) {
	      		reportError("0", __("An error occured checking your password.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());    
	    	}
	 
			if ($db->num_rows($result) == 0) {	
	      		$_SESSION['auth'] = false;
	       		reportError("0", __("Password incorrect. Please try again."));
	   		}
	   		else {
				$_SESSION['auth'] = true;
				$_SESSION['db'] = $db;
	    	}
		}
		else {
			// New auth
			Kit::ClassLoader('userdata');
			
			// Get the SALT for this username
			if (!$userInfo = $db->GetSingleRow(sprintf("SELECT UserID, UserName, UserPassword, UserTypeID, CSPRNG FROM `user` WHERE UserName = '%s'", $db->escape_string($username)))) {
				$_SESSION['auth'] = false;
		   		reportError("0", __("Password incorrect. Please try again."));
			}

			// User Data Object to check the password
			$userData = new Userdata($db);

			// Is SALT empty
			if ($userInfo['CSPRNG'] == 0) {

				// Check the password using a MD5
				if ($userInfo['UserPassword'] != md5($password)) {
					$_SESSION['auth'] = false;
		   			reportError("0", __("Password incorrect. Please try again."));
				}

				// Now that we are validated, generate a new SALT and set the users password.
				$userData->ChangePassword(Kit::ValidateParam($userInfo['UserID'], _INT), null, $password, $password, true /* Force Change */);
			}
			else {
				
				// Check the users password using the random SALTED password
		        if ($userData->validate_password($password, $userInfo['UserPassword']) === false) {
		        	$_SESSION['auth'] = false;
		   			reportError("0", __("Password incorrect. Please try again."));
		        }
			}

			$_SESSION['auth'] = true;
			$_SESSION['db'] = $db;
		}
   	}
## Check server meets specs (as specs might have changed in this release)
  ?>
  <p><?php echo __("First we need to check if your server meets Xibo's requirements."); ?></p>
  <div class="checks">
  <?php
    $db = new Database();
    $cObj = new Config();
    echo $cObj->CheckEnvironment();
    if ($cObj->EnvironmentFault()) {
	$_SESSION['step'] = 1;
    ?>
      <form action="upgrade.php" method="POST">
        <div class="loginbutton"><button type="submit"><?php echo __("Retest"); ?></button></div>
      </form>
    <?php
    }
    else if ($cObj->EnvironmentWarning()) {
    ?>
      <form action="upgrade.php" method="POST">
	<input type="hidden" name="stepskip" value="1">
        <div class="loginbutton"><button type="submit"><?php echo __("Retest"); ?></button></div>
      </form>
      <form action="upgrade.php" method="POST">
        <div class="loginbutton"><button type="submit"><?php echo __("Next"); ?> ></button></div>
      </form>
    <?php
    }
    else {
    ?>
      <form action="upgrade.php" method="POST">
        <div class="loginbutton"><button type="submit"><?php echo __("Next"); ?> ></button></div>
      </form>
    <?php
    }    
}
elseif ($_SESSION['step'] == 2) {
	# Calculate the upgrade
	checkAuth();
      
	$_SESSION['upgradeFrom'] = Config::Version('DBVersion');

	if ($_SESSION['upgradeFrom'] < 1) {
		$_SESSION['upgradeFrom'] = 1;
	}

	// Get a list of .sql and .php files for the upgrade
	$sql_files = ls('*.sql','install/database',false,array('return_files'));
	$php_files = ls('*.php','install/database',false,array('return_files'));
    
	// Sort by natural filename (eg 10 is bigger than 2)
	natcasesort($sql_files);
	natcasesort($php_files);

	$_SESSION['phpFiles'] = $php_files;
	$_SESSION['sqlFiles'] = $sql_files;

	$max_sql = Kit::ValidateParam(substr(end($sql_files),0,-4),_INT);
	$max_php = Kit::ValidateParam(substr(end($php_files),0,-4),_INT);
	$_SESSION['upgradeTo'] = max($max_sql, $max_php);

	if (! $_SESSION['upgradeTo']) {
		reportError("2", __("Unable to calculate the upgradeTo value. Check for non-numeric SQL and PHP files in the 'install/database' directory."), "Retry"); // Fixme : translate Retry ?
	}

	if ($_SESSION['upgradeTo'] < $_SESSION['upgradeFrom'])
		$_SESSION['upgradeTo'] = $_SESSION['upgradeFrom'];

	echo '<div class="info">';
	echo '<p>Upgrading from database version ' . $_SESSION['upgradeFrom'] . ' to ' . $_SESSION['upgradeTo'];
	echo '</p></div><hr />';
	echo '<form action="upgrade.php" method="POST">';

	// Loop for $i between upgradeFrom + 1 and upgradeTo.
	// If a php file exists for that upgrade, make an instance of it and call Questions so we can
	// Ask the user for input.
	for ($i=$_SESSION['upgradeFrom'] + 1; $i <= $_SESSION['upgradeTo']; $i++) {
		if (file_exists('install/database/' . $i . '.php')) {
			include_once('install/database/' . $i . '.php');
			$stepName = 'Step' . $i;
			
			// Check that a class called Step$i exists
			if (class_exists($stepName)) {
				$_SESSION['Step' . $i] = new $stepName($db);
				// Call Questions on the object and send the resulting hash to createQuestions routine
				createQuestions($i, $_SESSION['Step' . $i]->Questions());
			}
			else {
				print __("Warning: We included ") . $i . ".php, " . __("but it did not include a class of appropriate name.");
			}						
		}
	}

    echo '<div class="info"><p>';
	echo __("I agree I have a valid database backup and can restore it should the upgrade process fail:");
	echo '</p></div><div class="install-table">';
    echo '<input type="checkbox" name="doBackup" />';
	echo '</div><hr />';

	$_SESSION['step'] = 3;
	echo '<input type="hidden" name="includes" value="true" />';
	echo '<input type="hidden" name="upgradeFrom" value="' . $_SESSION['upgradeFrom'] . '" />';
	echo '<input type="hidden" name="upgradeTo" value="' . $_SESSION['upgradeTo'] . '" />';
	echo '<p><input type="submit" value="' . __("Next") . ' >" /></p>';
	echo '</form>';

?>
  <?php
}
elseif ($_SESSION['step'] == 3) {
	// $_SESSION['step'] = 0;
	$fault = false;
	$fault_string = "";

	foreach ($_POST as $key => $post) {
		// $key should be like 1-2, 1-3 etc
		// Split $key on - character.

		$parts = explode('-', $key);
		if (count($parts) == 2) {
			$step_num = 'Step' . $parts[0];
			include_once('install/database/' . $parts[0] . '.php');
			// $_SESSION['q'][$step_num] = unserialize($_SESSION['q'][$step_num]);

			$response = $_SESSION[$step_num]->ValidateQuestion($parts[1], $post);
			if (! $response == true) {
				// The upgrade routine for this step wasn't happy.
				$fault = true;
				$fault_string .= $response . "<br />\n";
			}
		}
	}

	if ($fault) {
		// Report the error, and a back button
		echo __("FAIL:") . " " . $fault_string;
	}
	else {

		$doBackup = Kit::GetParam("doBackup", $_POST, _BOOL);

		set_time_limit(0);
		// Backup the database
		echo '<div class="info"><p>';
        if (! $doBackup) {
            echo __('You MUST have a valid database backup to continue. Please take and verify a backup and upgrade again.');
            echo '</p>';
            echo '</div>';
            include('install/footer.inc');
            session_destroy();
            exit();
        }
		echo '</p>';

		// Now loop over the entire upgrade. Run the SQLs and PHP interleaved.
		for ($i=$_SESSION['upgradeFrom'] + 1; (($i <= $_SESSION['upgradeTo']) && ($fault==false)) ; $i++) {
			if (file_exists('install/database/' . $i . '.sql')) {
				echo '<p>' . $i . '.sql ';
				flush();

				$delimiter = ';';
			        $sql_file = @file_get_contents('install/database/' . $i . '.sql');
			        $sql_file = remove_remarks($sql_file);
			        $sql_file = split_sql_file($sql_file, $delimiter);
    
			        foreach ($sql_file as $sql) {
			          print ".";
				  $sqlStatementCount++;
			          flush();

			          if (! $db->query($sql)) {
			 	    $fault = true;
			            reportError("0", __("An error occured populating the database.") . "<br /><br />" . __("MySQL Error:") . "<br />" . $db->error() . "<br /><br />SQL executed:<br />" . $sql . "<br /><br />Statement number: " . $sqlStatementCount);
			          }
			        }
				echo '</p>';
			}
			if (file_exists('install/database/' . $i . '.php')) {
				$stepName = 'Step' . $i;
				echo '<p>' . $i . '.php ';
				flush();
				if (! $_SESSION[$stepName]->Boot()) {
					$fault = true;
				}
				echo '</p>';
			}
		}
		echo '</div>';
		if (! $fault) {
			if (! unlink('install.php')) {
    				echo __("Unable to delete install.php. Please remove this file manually.");
  			}
			if (! unlink('upgrade.php')) {
				echo __("Unable to delete upgrade.php. Please remove this file manually.");
			}

			echo '<b>' . __("Upgrade is complete!") . '</b><br /><br />';
			echo '<form method="POST" action="index.php">';
			echo '<input type="submit" value="' . __("Login") . '" />';
			echo '</form>';
		}
		else {
			echo '<b>' . __("There was an error during the upgrade. Please take a screenshot of this page and seek help!") . '</b>';
		}
		session_destroy();
	}
}
else {
  reportError("0", __("A required parameter was missing. Please go through the installer sequentially!"),"Start Again"); // Fixme : Translate Start Again ?
}
 
include('install/footer.inc');

# Functions
function checkPHP() {
  # Check PHP version > 5
  return (version_compare("5.3.3",phpversion(), "<="));
}

function checkMySQL() {
  # Check PHP has MySQL module installed
  return extension_loaded("mysql");
}

function checkJson() {
  # Check PHP has JSON module installed
  return extension_loaded("json");
}

function checkGd() {
  # Check PHP has JSON module installed
  return extension_loaded("gd");
}

function checkCal() {
  # Check PHP has JSON module installed
  return extension_loaded("calendar");
}
 
function reportError($step, $message, $button_text="&lt; Back") { // Fixme : Translate Back ?
	$_SESSION['step'] = $step;
?>
    <div class="info">
      <?php print $message; ?>
    </div>
    <form action="upgrade.php" method="POST">
      <button type="submit"><?php print $button_text; ?></button>
    </form>
  <?php
  include('install/footer.inc');
  die();
} 

function checkAuth() {
	if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
		reportError(1, __("You must authenticate to run the upgrade."));
	}
}

// Taken from http://forums.devshed.com/php-development-5/php-wont-load-sql-from-file-515902.html
// By Crackster 
/**
 * remove_remarks will strip the sql comment lines out of an uploaded sql file
 */
function remove_remarks($sql){
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
function split_sql_file($sql, $delimiter){
  $sql = str_replace("\r" , '', $sql);
  $data = preg_split('/' . preg_quote($delimiter, '/') . '$/m', $sql);
  $data = array_map('trim', $data);
  // The empty case
  $end_data = end($data);
  if (empty($end_data))
  {
    unset($data[key($data)]);
  }
  return $data;
}
 
/**
 * This funtion will take a pattern and a folder as the argument and go thru it(recursivly if needed)and return the list of 
 *               all files in that folder.
 * Link             : http://www.bin-co.com/php/scripts/filesystem/ls/
 * License	: BSD
 * Arguments     :  $pattern - The pattern to look out for [OPTIONAL]
 *                    $folder - The path of the directory of which's directory list you want [OPTIONAL]
 *                    $recursivly - The funtion will traverse the folder tree recursivly if this is true. Defaults to false. [OPTIONAL]
 *                    $options - An array of values 'return_files' or 'return_folders' or both
 * Returns       : A flat list with the path of all the files(no folders) that matches the condition given.
 */
function ls($pattern="*", $folder="", $recursivly=false, $options=array('return_files','return_folders')) {
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
                $deep_items = ls($pattern, $this_folder, $recursivly, $options); # :RECURSION:
                foreach ($deep_items as $item) {
                    array_push($all, $this_folder . $item);
                }
            }
        }
    }
    
    if($folder) chdir($current_folder);
    return $all;
}

// Taken from http://davidwalsh.name/backup-mysql-database-php
// No explicit license. Assumed public domain.
// Ammended to use a database object by Alex Harrington.
// If this is your code, and wish for us to remove it, please contact
// info@xibo.org.uk
/* backup the db OR just a table */
function backup_tables($db,$tables = '*')
{
	//get all of the tables
	if($tables == '*')
	{
		$tables = array();
		$result = $db->query('SHOW TABLES');
		while($row = $db->get_row($result))
		{
			$tables[] = $row[0];
		}
	}
	else
	{
		$tables = is_array($tables) ? $tables : explode(',',$tables);
	}

	// Open file for writing at length 0.
	$handle = fopen(Config::GetSetting('LIBRARY_LOCATION') . 'db-backup-'.time().'-'.(md5(implode(',',$tables))).'.sql','w+');
	
	fwrite($handle,"SET FOREIGN_KEY_CHECKS=0;\n");
	fwrite($handle,"SET UNIQUE_CHECKS=0;\n");
	
	//cycle through
	foreach($tables as $table)
	{
		echo '.';
		flush();
		$result = $db->query('SELECT * FROM `'.$table .'`');
		$num_fields = $db->num_fields($result);
		
		$return = 'DROP TABLE IF EXISTS `'.$table.'`;';
		fwrite($handle, $return);

		$row2 = $db->get_row($db->query('SHOW CREATE TABLE `'.$table.'`'));
		$return = "\n\n".$row2[1].";\n\n";
		fwrite($handle,$return);
		
		for ($i = 0; $i < $num_fields; $i++) 
		{
			while($row = $db->get_row($result))
			{
				$return = 'INSERT INTO `'.$table.'` VALUES(';
				fwrite($handle, $return);
				for($j=0; $j<$num_fields; $j++) 
				{
					$return = '';
					$row[$j] = addslashes($row[$j]);
					$row[$j] = preg_replace("/\n/","\\n",$row[$j]);
					if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= 'NULL'; }
					if ($j<($num_fields-1)) { $return.= ','; }
					fwrite($handle, $return);
				}
				$return = ");\n";
				fwrite($handle, $return);
			}
		}
		$return ="\n\n\n";
		fwrite ($handle, $return);
	}
	
	
	fwrite($handle,"SET FOREIGN_KEY_CHECKS=1;\n");
	fwrite($handle,"SET UNIQUE_CHECKS=1;\n");
	
	fclose($handle);
}


function gen_secret() {
  # Generates a random 12 character alphanumeric string to use as a salt
  mt_srand((double)microtime()*1000000);
  $key = "";
  for ($i=0; $i < 12; $i++) {
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

function createQuestions($step, $questions) {
	// Takes a multi-dimensional array eg:
	// $q[0]['question'] = "May we collect anonymous usage statistics?";
	// $q[0]['type'] = _CHECKBOX;
	// $q[0]['default'] = true;
	//
	// And turns it in to an HTML form for the user to complete.
	foreach ($questions as $qnum => $question) {
		echo '<div class="info"><p>';
		echo $question['question'];
		echo '</p></div><div class="install-table">';

		if (($question['type'] == _INPUTBOX) || ($question['type'] == _PASSWORD)) {
			echo '<input type="';
			if ($question['type'] == _INPUTBOX) {
				echo 'text';
			}
			else {
				echo 'password';
			}
			echo '" name="' . $step . '-' . $qnum .'" value="'. $question['default'] .'" length="12" />';
		}
		elseif ($question['type'] == _CHECKBOX) {
			echo '<input type="checkbox" name="' . $step . '-' . $qnum . '" ';
			if ($question['default']) {
				echo 'checked ';
			}
			echo '/>';
		}
		echo '</div><hr width="25%" />';
	}
}

class UpgradeStep 
{
	protected $db;
	protected $q;
	protected $a;

	public function __construct($db)
	{
		$this->db 	=& $db;
		$this->q	= array();
		$this->a	= array();
	}

	public function Boot()
	{

	}

	public function Questions()
	{
		return array();
	}

	public function ValidateQuestion($questionNumber,$response)
	{
		return true;
	}
}

?>
