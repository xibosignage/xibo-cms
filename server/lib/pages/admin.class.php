<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
	
	function displayPage() 
	{
	
		include("template/pages/settings_view.php");
		
		return false;
	}
	
	function on_page_load() 
	{
		return '';
	}
	
	function echo_page_heading() 
	{
		echo __('Settings');
		return true;
	}

	function modify() 
	{
		$db =& $this->db;

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
					trigger_error(__('The Library Location you have picked is not writable to the Xibo Server.'), E_USER_ERROR);
				}
			}
			
			$SQL = sprintf("UPDATE setting SET value = '%s' WHERE settingid = %d ", $db->escape_string($value), $id);

			if(!$db->query($SQL)) 
			{
				trigger_error($db->error());
				trigger_error(__('Update of settings failed.'), E_USER_ERROR);
			}
		}

		setMessage(__("Settings changed"));

		return $refer;
	}
	
	function display_settings() 
	{
		$db 			=& $this->db;
		$user			=& $this->user;
		
		$helpObject		= new HelpManager($db, $user);
			
		$helpButton 	= $helpObject->HelpButton("content/config/settings", true);
		
		//one giant form, split into tabs
		$form = '<form method="post" action="index.php?p=admin&q=modify">';
		$tabs = '';
		$pages = '';
		
		//get all the tabs, ordered by catagory
		$SQL = "SELECT DISTINCT cat FROM setting WHERE userChange = 1 ORDER BY cat";
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__('Can\'t get the setting catagories'), E_USER_ERROR);
		}
		
		while ($row = $db->get_row($results)) 
		{
			$cat = $row[0];
			$ucat = ucfirst($cat);
			$cat_tab = $cat."_tab";
			
			// generate the li and a for this tab
			$tabs .= "<li><a href='#$cat_tab'><span>$ucat</span></a></li>";
		
			// for each one, call display_cat to get the settings specific to that cat
			$cat_page = $this->display_cat($cat);
			
			$pages .= <<<PAGES
			<div id="$cat_tab">
				$cat_page
			</div>
PAGES;
		}
		
		$msgSave = __('Save');

		// Output it all
		$form .= <<<FORM
		<div id="tabs">
			<ul class="tabs-nav">
				$tabs
			</ul>
			$pages
		</div>
		<input type="hidden" name="location" value="index.php?p=admin&q=modify">
		<input type="hidden" name="refer" value="index.php?p=admin">
		<input type="submit" value="$msgSave" />
		$helpButton
FORM;
	
		//end the form and output
		$form .= "</form>";
		
		echo $form;
		
		return false;
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
                    // Display the file size
                    $fileSize = $this->db->GetSingleValue('SELECT SUM(FileSize) AS SumSize FROM media', 'SumSize', _INT);

                    $sz = 'BKMGTP';
                    $factor = floor((strlen($fileSize) - 1) / 3);
                    $fileSize = sprintf('%.2f', $fileSize / pow(1024, $factor)) . @$sz[$factor];

                    $output .= sprintf('<p>You have %s of media in the library.</p>', $fileSize);
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
		$SQL.= sprintf("SELECT settingid, setting, value, helptext, options FROM setting WHERE type = 'dropdown' AND cat='%s' AND userChange = 1", $cat);

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
			
			// Add UTC to the list of options as a last resort option.
			$zonen[$i]['continent'] = "General";
			$zonen[$i]['city'] = "UTC";

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
        $mail_to        = Kit::ValidateParam(Config::GetSetting($db, "mail_to"),_PASSWORD);
        $mail_from      = Kit::ValidateParam(Config::GetSetting($db, "mail_from"),_PASSWORD);
        $subject        = __('Email Test');
        $body           = __('Test email sent from Xibo');
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

}
?>
