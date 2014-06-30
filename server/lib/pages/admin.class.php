<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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

class adminDAO 
{
	private $db;
	private $user;

	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
		
		require_once('lib/data/setting.data.class.php');
	}
	
	function displayPage() {
	
		// Set some information about the form
        Theme::Set('form_id', 'SettingsForm');
        Theme::Set('form_action', 'index.php?p=group&q=Delete');
		Theme::Set('settings_help_button_url', HelpManager::Link('Content', 'Config'));
		Theme::Set('settings_form', $this->display_settings());

		// Render the Theme and output
        Theme::Render('settings_page');
	}

	function modify() 
	{
		$db =& $this->db;

		// Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);

		$refer 		= Kit::GetParam('refer', _POST, _STRING);
		$usertype 	= Kit::GetParam('usertype', _SESSION, _INT);
		
		$ids		= Kit::GetParam('id', _POST, _ARRAY);
		$values		= Kit::GetParam('value', _POST, _ARRAY);
		
		$size 		= count($ids);
		
		if ($usertype != 1) 
		{
			setMessage(__("Only admin users are allowed to modify settings"));
			return $refer;
		}
		
		// Get the SettingId for LIBRARY_LOCATION
		$SQL = sprintf("SELECT settingid FROM setting WHERE setting = '%s'", 'LIBRARY_LOCATION');
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Cannot find the Library Location Setting - this is serious.'), E_USER_ERROR);
		}
		
		if ($db->num_rows($result) == 0)
		{
			trigger_error(__('Cannot find the Library Location Setting - this is serious.'), E_USER_ERROR);
		}
		
		$row 				= $db->get_row($result);
		$librarySettingId 	= $row[0];
	
		// Loop through and modify the settings
		for ($i=0; $i<$size; $i++) 
		{
			$value = Kit::ValidateParam($values[$i], _STRING);
			$id = $ids[$i];
			
			// Is this the library location setting
			if ($id == $librarySettingId)
			{
				// Check for a trailing slash and add it if its not there
				$value = rtrim($value, '/') . '/';
				
				// Attempt to add the directory specified
				if (!file_exists($value . 'temp'))
				{
					// Make the directory with broad permissions recursively (so will add the whole path)
					mkdir($value . 'temp', 0777, true);
				}
				
				if (!is_writable($value . 'temp'))
				{
					trigger_error(__('The Library Location you have picked is not writable'), E_USER_ERROR);
				}
			}
			
			$SQL = sprintf("UPDATE setting SET value = '%s' WHERE settingid = %d ", $db->escape_string($value), $id);

			if(!$db->query($SQL)) 
			{
				trigger_error($db->error());
				trigger_error(__('Update of settings failed.'), E_USER_ERROR);
			}
		}

		$response = new ResponseManager();
		$response->SetFormSubmitResponse(__('Settings Updated'), false);
        $response->Respond();
	}
	
	function display_settings() 
	{
		$db 			=& $this->db;
		$user			=& $this->user;
		
		$helpObject		= new HelpManager($db, $user);
			
		$helpButton 	= $helpObject->HelpButton("content/config/settings", true);
		
		//one giant form, split into tabs
		$form = '<form id="SettingsForm" method="post" class="XiboForm" action="index.php?p=admin&q=modify">' . Kit::Token();
		$tabs = '';
		$pages = '';
		
		//get all the tabs, ordered by catagory
		$SQL = "SELECT DISTINCT cat FROM setting WHERE userChange = 1 ORDER BY cat";
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__("Can't get the setting catagories"), E_USER_ERROR);
		}
		
		while ($row = $db->get_row($results)) 
		{
			$cat = $row[0];
			$ucat = ucfirst($cat);
			$cat_tab = $cat."_tab";
			
			// generate the li and a for this tab
			$tabs .= "<li><a href='#$cat_tab'><span>$ucat</span><i class='icon-chevron-right pull-right'></i></a></li>";
		
			// for each one, call display_cat to get the settings specific to that cat
			$cat_page = $this->display_cat($cat);
			
			$pages .= <<<PAGES
			<div id="$cat_tab">
				$cat_page
			</div>
PAGES;
		}
		
		$msgSave = __('Save');
		$msgCategories = __('Categories');

		// Output it all
		$form .= <<<FORM
		<div class="span2">
			<div class="well affix">
				<ul class="nav nav-list ">
					<li class="nav-header">$msgCategories</li>
					$tabs
				</ul>
			</div>
		</div>
		<div class="span10">
			$pages
		</div>
FORM;
	
		//end the form and output
		$form .= "</form>";
		
		return $form;
	}

	function display_cat($cat) 
	{
		$db =& $this->db;
		
		$output = "";
		
		$title 	 = ucfirst($cat);
		$output .= '<h3>' . __($title) . ' ' . __('Settings') . '</h3>';
			
		/*
			Firstly we want to individually get the user module
		*/
		if ($cat == 'user')
		{
		
			$SQL = "";
			$SQL.= "SELECT settingid, setting, value, helptext FROM setting WHERE setting = 'userModule'";
	
			if(!$results = $db->query($SQL))
			{
				trigger_error($db->error());
				trigger_error(__('Can not get settings'), E_USER_ERROR);				
			}
	
			$row 		= $db->get_row($results);
			$settingid 	= Kit::ValidateParam($row[0], _INT);
			$setting 	= Kit::ValidateParam($row[1], _STRING);
			$setting 	= __($setting);
			$value 		= Kit::ValidateParam($row[2], _STRING);
			$helptext	= Kit::ValidateParam($row[3], _HTMLSTRING);
			
			$output .= <<<END
			<h5>$setting</h5>
			<p>$helptext</p>
END;

			// we need to make a drop down out of the files that match a string, in a directory
			$files 	= scandir("modules/");
			$select = "";
			
			foreach ($files as $file) 
			{
                            $selected = "";
                            if($file == $value) $selected = "selected";

                            if(preg_match("^module_user^", $file))
                            {
                                    //var for drop down
                                    $select.= "<option value='$file' $selected>$file</option>";
                            }
			}
				
			$output .=  <<<END
			<input type="hidden" name="id[]" value="$settingid">
			<select name="value[]">
				$select
			</select>
END;
		}

                if ($cat == 'content')
                {
                    $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

                    // Library Size in Bytes
                    $fileSize = $this->db->GetSingleValue('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', 'SumSize', _INT);
                    $limitPcnt = ($libraryLimit > 0) ? (($fileSize / ($libraryLimit * 1024)) * 100) : '';

                    $output .= '<p>' . sprintf(__('You have %s of media in the library.'), $this->FormatByteSize($fileSize)) . (($libraryLimit > 0) ? sprintf(__(' This is %d %% of your %s limit.'), $limitPcnt, $this->FormatByteSize($libraryLimit * 1024)) : '') . '</p>';
                
                    // Monthly bandwidth - optionally tested against limits
                    $xmdsLimit = Config::GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');
                    $startOfMonth = strtotime(date('m').'/01/'.date('Y').' 00:00:00');

                    $sql = sprintf('SELECT IFNULL(SUM(Size), 0) AS BandwidthUsage FROM `bandwidth` WHERE Month = %d', $startOfMonth);
                    $bandwidthUsage = $this->db->GetSingleValue($sql, 'BandwidthUsage', _INT);

                    Debug::LogEntry('audit', $sql);
                    
                    $usagePcnt = ($xmdsLimit > 0) ? (($bandwidthUsage / ($xmdsLimit * 1024)) * 100) : '';
                    
                    $output .= '<p>' . sprintf(__('You have used %s of bandwidth this month.'), $this->FormatByteSize($bandwidthUsage)) . (($xmdsLimit > 0) ? sprintf(__(' This is %d %% of your %s limit.'), $usagePcnt, $this->FormatByteSize($xmdsLimit * 1024)) : '') . '</p>';
                }

                if ($cat == 'general')
                {
                    $output .= '<p>' . __('Import / Export Database') . '</p>';

                    if (Config::GetSetting('SETTING_IMPORT_ENABLED') == 'On')
                    	$output .= '<button class="XiboFormButton" href="index.php?p=admin&q=RestoreForm">' . __('Import') . '</button>';
                    
                    $output .= '<button class="XiboFormButton" href="index.php?p=admin&q=BackupForm">' . __('Export') . '</button>';
                    
                    if (Config::GetSetting('SETTING_LIBRARY_TIDY_ENABLED') == 'On')
                    	$output .= '<button class="XiboFormButton" href="index.php?p=admin&q=TidyLibrary">' . __('Tidy Library') . '</button>';
                }
		
		// Need to now get all the Misc settings 
		$SQL = "";
		$SQL.= sprintf("SELECT settingid, setting, value, helptext FROM setting WHERE type = 'text' AND cat='%s' AND userChange = 1", $cat);

		if (!$results = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Can not get settings'), E_USER_ERROR);				
		}

		while($row = $db->get_row($results)) 
		{
			$settingid 	= Kit::ValidateParam($row[0], _INT);
			$setting 	= Kit::ValidateParam($row[1], _STRING);
			$value 		= Kit::ValidateParam($row[2], _STRING);
			$helptext	= Kit::ValidateParam($row[3], _HTMLSTRING);

			$output .=  <<<END
			<h5>$setting</h5>
			<p>$helptext</p>
			<input type="hidden" name="id[]" value="$settingid">
			<input type="text" name="value[]" value="$value">
END;
			if($setting == "mail_to") 
			{
				$msgTestEmail = __('Test Email');
				//show another form here, for test
				$output .= <<<END
				<a id="test_email" href="index.php?p=admin&q=SendEmail" class="XiboFormButton">$msgTestEmail</a>
END;
			}
		}	
			
		// Drop downs
		$SQL = "";
		$SQL.= sprintf("SELECT settingid, setting, value, helptext, options FROM setting WHERE type = 'dropdown' AND cat='%s' AND userChange = 1", $db->escape_string($cat));

		if (!$results = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Can not get settings'), E_USER_ERROR);				
		}

		while($row = $db->get_row($results)) 
		{
			$settingid 	= Kit::ValidateParam($row[0], _INT);
			$setting 	= Kit::ValidateParam($row[1], _STRING);
			$value 		= Kit::ValidateParam($row[2], _STRING);
			$helptext	= Kit::ValidateParam($row[3], _HTMLSTRING);
			$options	= Kit::ValidateParam($row[4], _STRING);

			$select = "";
			
			$options = explode("|", $options);
			foreach ($options as $option) 
			{
				if($option == $value) 
				{
					$select.="<option value='$option' selected>$option</option>";
				}
				else 
				{
					$select.="<option value='$option'>$option</option>";
				}
			}
			
			$output .= <<<END
			<h5>$setting</h5>
			<p>$helptext</p>
			<input type="hidden" name="id[]" value="$settingid">
			<select name="value[]">$select</select>
END;
		}
		
		// Also deal with the timezone setting type
		$SQL = "";
		$SQL.= sprintf("SELECT settingid, setting, value, helptext FROM setting WHERE type = 'timezone' AND cat='%s' AND userChange = 1", $cat);

		if (!$results = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Can not get settings'), E_USER_ERROR);				
		}

		while($row = $db->get_row($results)) 
		{
			$settingid 	= Kit::ValidateParam($row[0], _INT);
			$setting 	= Kit::ValidateParam($row[1], _STRING);
			$selectedzone = Kit::ValidateParam($row[2], _STRING);
			$helptext	= Kit::ValidateParam($row[3], _HTMLSTRING);
			$options	= $this->TimeZoneIdentifiersList();
			
			$structure 	= '';
			$i 			= 0;
			
			// Create a Zone array containing the timezones
			// From: http://php.oregonstate.edu/manual/en/function.timezone-identifiers-list.php
			foreach($options as $zone) 
			{
				$zone 					= explode('/',$zone);
				$zonen[$i]['continent'] = isset($zone[0]) ? $zone[0] : '';
				$zonen[$i]['city'] 		= isset($zone[1]) ? $zone[1] : '';
				$zonen[$i]['subcity'] 	= isset($zone[2]) ? $zone[2] : '';
				$i++;
			}
			
			// Sort them
			asort($zonen);
			
			foreach($zonen as $zone) 
			{
				extract($zone);
				
				if($continent == 'Africa' || $continent == 'America' || $continent == 'Antarctica' || $continent == 'Arctic' || $continent == 'Asia' || $continent == 'Atlantic' || $continent == 'Australia' || $continent == 'Europe' || $continent == 'Indian' || $continent == 'Pacific' || $continent == 'General') 
				{
					if(!isset($selectcontinent)) 
					{
						$structure .= '<optgroup label="'.$continent.'">'; // continent
					} 
					elseif($selectcontinent != $continent) 
					{
						$structure .= '</optgroup><optgroup label="'.$continent.'">'; // continent
					}
			
					if(isset($city) != '')
					{
						if (!empty($subcity) != '')
						{
							$city = $city . '/'. $subcity;
						}
						$structure .= "<option ".((($continent.'/'.$city)==$selectedzone)?'selected="selected "':'')." value=\"".($continent.'/'.$city)."\">".str_replace('_',' ',$city)."</option>"; //Timezone
					} 
					else 
					{
						if (!empty($subcity) != '')
						{
							$city = $city . '/'. $subcity;
						}
						$structure .= "<option ".(($continent==$selectedzone)?'selected="selected "':'')." value=\"".$continent."\">".$continent."</option>"; //Timezone
					}
			
					$selectcontinent = $continent;
				}
			}
			$structure .= '</optgroup>';
			
			// End
			
			$output .= <<<END
			<h5>$setting</h5>
			<p>$helptext</p>
			<input type="hidden" name="id[]" value="$settingid">
			<select name="value[]">$structure</select>
END;
		}
		
		return $output;
	}
	
	/**
	 * Timezone functionality
	 * @return 
	 */
	private function TimeZoneIdentifiersList()
	{
		if (function_exists('timezone_identifiers_list')) 
		{
			return timezone_identifiers_list();
		}

		$list[] = 'Europe/London';
		$list[] = 'America/New_York';
		$list[] = 'Europe/Paris';
		$list[] = 'America/Los_Angeles';
		$list[] = 'America/Puerto_Rico';
		$list[] = 'Europe/Moscow';
		$list[] = 'Europe/Helsinki';
		$list[] = 'Europe/Warsaw';
		$list[] = 'Asia/Singapore';
		$list[] = 'Asia/Dubai';
		$list[] = 'Asia/Baghdad';
		$list[] = 'Asia/Shanghai';
		$list[] = 'Indian/Mauritius';
		$list[] = 'Australia/Melbourne';
		$list[] = 'Australia/Sydney';
		$list[] = 'Arctic/Longyearbyen';
		$list[] = 'Antarctica/South_Pole';
		
		return $list;
	}
	
	/**
	 * Sets all debugging to maximum
	 * @return 
	 */
	public function SetMaxDebug()
	{
		$db			=& $this->db;
		$response	= new ResponseManager();
		$setting 	= new Setting($db);
		
		if (!$setting->Edit('debug', 'On'))
		{
			trigger_error(__('Cannot set debug to On'));
		}
		
		if (!$setting->Edit('audit', 'On'))
		{
			trigger_error(__('Cannot set audit to On'));
		}
		
		$response->SetFormSubmitResponse(__('Debugging switched On.'));
		$response->Respond();
	}
	
	/**
	 * Turns off all debugging
	 * @return 
	 */
	public function SetMinDebug()
	{
		$db			=& $this->db;
		$response	= new ResponseManager();
		$setting 	= new Setting($db);
		
		if (!$setting->Edit('debug', 'Off'))
		{
			trigger_error(__('Cannot set debug to Off'), E_USER_ERROR);
		}
		
		if (!$setting->Edit('audit', 'Off'))
		{
			trigger_error(__('Cannot set audit to Off'), E_USER_ERROR);
		}
		
		$response->SetFormSubmitResponse(__('Debugging switched Off.'));
		$response->Respond();
	}
	
	/**
	 * Puts the Server in Production Mode
	 * @return 
	 */
	public function SetServerProductionMode()
	{
		$db			=& $this->db;
		$response	= new ResponseManager();
		$setting 	= new Setting($db);
		
		if (!$setting->Edit('SERVER_MODE', 'Production'))
		{
			trigger_error(__('Cannot switch modes.'), E_USER_ERROR);
		}
		
		$response->SetFormSubmitResponse(__('Server switched to Production Mode'));
		$response->Respond();
	}
	
	/**
	 * Puts the Server in Test Mode
	 * @return 
	 */
	public function SetServerTestMode()
	{
		$db			=& $this->db;
		$response	= new ResponseManager();
		$setting 	= new Setting($db);
		
		if (!$setting->Edit('SERVER_MODE', 'Test'))
		{
			trigger_error(__('Cannot switch modes.'), E_USER_ERROR);
		}
		
		$response->SetFormSubmitResponse(__('Server switched to Test Mode'));
		$response->Respond();
    }

    public function SendEmail()
    {
        $db				=& $this->db;
        $response		= new ResponseManager();
        $mail_to        = Kit::ValidateParam(Config::GetSetting("mail_to"),_PASSWORD);
        $mail_from      = Kit::ValidateParam(Config::GetSetting("mail_from"),_PASSWORD);
        $subject        = __('Email Test');
        $body           = __('Test email sent');
        $headers        = sprintf("From: %s",$mail_from);
        		
        $output  = sprintf(__('Sending test email to %s.'),$mail_to);
        $output  .= "<br/><br/>";
        
        if(mail($mail_to, $subject, $body, $headers))
        {
            $output .= __("Mail sent OK");
        }
        else
        {
            $output .= __("Mail sending FAILED");
        }
	
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->SetFormRequestResponse($output, __('Email Test'), '280px', '140px');
        $response->Respond();
    }

    /**
     * Backup Form
     */
    public function BackupForm()
    {
        $response = new ResponseManager();

        // Check we have permission to do this
        if ($this->user->usertypeid != 1)
            trigger_error(__('Only an adminitrator can export a database'));

        $form = '';
        $form .= '<p>' . __('This will create a dump file of your database that you can restore later using the import functionality.') . '</p>';
        $form .= '<p>' . __('You should also manually take a backup of your library.') . '</p>';
        $form .= '<p>' . __('Please note: The folder location for mysqldump must be available in your path environment variable for this to work and the php "exec" command must be enabled.') . '</p>';
        $form .= '<a href="index.php?p=admin&q=BackupDatabase" title="' . __('Export Database. Right click to save as.') . '">' . __('Click here to Export') . '</a>';
        
        $response->SetFormRequestResponse($form, __('Export Database Backup'), '550px', '275px');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }

    /**
     * Backup Data and Return a file
     */
    public function BackupDatabase()
    {
        // We want to output a load of stuff to the browser as a text file.
        Kit::ClassLoader('maintenance');
        $maintenance = new Maintenance($this->db);

        $dump = $maintenance->BackupDatabase();

        if ($dump == '')
            trigger_error(__('Unable to export database'), E_USER_ERROR);

        header('Content-Type: text/plaintext');
        header('Content-Disposition: attachment; filename="' . date('Y-m-d H:i:s') . '.bak"');
        header("Content-Transfer-Encoding: binary");
        header('Accept-Ranges: bytes');
        echo $dump;
        exit;
    }

    /**
     * Show an upload form to restore a database dump file
     */
    public function RestoreForm()
    {
        $response = new ResponseManager();

        if (Config::GetSetting('SETTING_IMPORT_ENABLED') != 'On')
        	trigger_error(__('Sorry this function is disabled.'), E_USER_ERROR);

        // Check we have permission to do this
        if ($this->user->usertypeid != 1)
            trigger_error(__('Only an adminitrator can import a database'));

        $msgDumpFile = __('Backup File');
        $msgWarn = __('Warning: Importing a file here will overwrite your existing database. This action cannot be reversed.');
        $msgMore = __('Select a file to import and then click the import button below. You will be taken to another page where the file will be imported.');
        $msgInfo = __('Please note: The folder location for mysqldump must be available in your path environment variable for this to work and the php "exec" command must be enabled.');

        $form = <<<FORM
        <p>$msgWarn</p>
        <p>$msgInfo</p>
        <form id="file_upload" method="post" action="index.php?p=admin&q=RestoreDatabase" enctype="multipart/form-data">
            <table>
                <tr>
                    <td><label for="file">$msgDumpFile<span class="required">*</span></label></td>
                    <td>
                        <input type="file" name="dumpFile" />
                    </td>
                </tr>
            </table>
        </form>
        <p>$msgMore</p>
FORM;
        $response->SetFormRequestResponse($form, __('Import Database Backup'), '550px', '375px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Import'), '$("#file_upload").submit()');
        $response->Respond();
    }

    /**
     * Restore the Database
     */
    public function RestoreDatabase()
    {
        $db =& $this->db;

        if (Config::GetSetting('SETTING_IMPORT_ENABLED') != 'On')
        	trigger_error(__('Sorry this function is disabled.'), E_USER_ERROR);

        include('install/header.inc');
        echo '<div class="info">';
        
        // Expect a file upload
        // Check we got a valid file
        if (isset($_FILES['dumpFile']) && is_uploaded_file($_FILES['dumpFile']['tmp_name']) && $_FILES['dumpFile']['error'] == 0)
        {
            echo 'Restoring Database</br>';
            Debug::LogEntry('audit', 'Valid Upload', 'Backup', 'RestoreDatabase');

            // Directory location
            $fileName = Kit::ValidateParam($_FILES['dumpFile']['tmp_name'], _STRING);

            if (is_uploaded_file($fileName))
            {
                // Move the uploaded file to a temporary location in the library
                $destination = tempnam(Config::GetSetting('LIBRARY_LOCATION'), 'dmp');
                move_uploaded_file($fileName, $destination);
                
                Kit::ClassLoader('maintenance');
                $maintenance = new Maintenance($this->db);

                // Use the maintenance class to restore the database
                if (!$maintenance->RestoreDatabase($destination))
                    trigger_error($maintenance->GetErrorMessage(), E_USER_ERROR);

                unlink($destination);
            }
            else
                trigger_error(__('Not a valid uploaded file'), E_USER_ERROR);
        }
        else
        {
            trigger_error(__('Unable to upload file'), E_USER_ERROR);
        }

        echo '</div>';
        echo '<a href="index.php?p=admin">' . __('Database Restored. Click here to continue.') . '</a>';

        include('install/footer.inc');

        die();
    }

    /**
     * Friendly format for file size
     * @param <type> $fileSize
     * @return <type>
     */
    private function FormatByteSize($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Tidies up the library
     */
    public function TidyLibrary()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        if (Config::GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 'On')
        	trigger_error(__('Sorry this function is disabled.'), E_USER_ERROR);

        // Also run a script to tidy up orphaned media in the library
        $library = Config::GetSetting('LIBRARY_LOCATION');
	    $library = rtrim($library, '/') . '/';

        Debug::LogEntry('audit', 'Library Location: ' . $library);

        // Dump the files in the temp folder
        foreach (scandir($library . 'temp') as $item)
        {
            if ($item == '.' || $item == '..')
                continue;

            Debug::LogEntry('audit', 'Deleting temp file: ' . $item);

            unlink($library . 'temp' . DIRECTORY_SEPARATOR . $item);
        }

        // Get a list of all media files
        foreach(scandir($library) as $file)
        {
            Debug::LogEntry('audit', 'Checking file: ' . $file);

            if ($file == '.' || $file == '..')
                continue;

	        if (is_dir($library . $file))
		        continue;

            $rowCount = $db->GetCountOfRows("SELECT * FROM media WHERE storedAs = '" . $file . "'");

            Debug::LogEntry('audit', 'Media count for file: ' . $file . ' is ' . $rowCount);
            
            // For each media file, check to see if the file still exists in the library
            if ($rowCount == 0)
            {
                Debug::LogEntry('audit', 'Deleting file: ' . $file);

                // If not, delete it
                unlink($library . $file);

                if (file_exists($library . 'tn_' . $file))
                {
                    unlink($library . 'tn_' . $file);
                }

                if (file_exists($library . 'bg_' . $file))
                {
                    unlink($library . 'bg_' . $file);
                }
            }
        }

        trigger_error(__('Library Tidy Complete'));
    }
}
?>
