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
	private $display_page = true;

	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
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
		echo "Settings";
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
			setMessage("Only admin users are allowed to modify settings");
			return $refer;
		}
		
		// Get the SettingId for LIBRARY_LOCATION
		$SQL = sprintf("SELECT settingid FROM setting WHERE setting = '%s'", 'LIBRARY_LOCATION');
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error('Cannot find the Library Location Setting - this is serious.', E_USER_ERROR);
		}
		
		if ($db->num_rows($result) == 0)
		{
			trigger_error('Cannot find the Library Location Setting - this is serious.', E_USER_ERROR);
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
					trigger_error('The Library Location you have picked is not writable to the Xibo Server.', E_USER_ERROR);
				}
			}
			
			$SQL = sprintf("UPDATE setting SET value = '%s' WHERE settingid = %d ", $db->escape_string($value), $id);

			if(!$db->query($SQL)) trigger_error("Update of settings failed".$db->error(), E_USER_ERROR);
		}

		setMessage("Settings changed!");

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
			trigger_error("Can't get the setting catagories", E_USER_ERROR);
		}
		
		while ($row = $db->get_row($results)) 
		{
			$cat = $row[0];
			$ucat = ucfirst($cat);
			$cat_tab = $cat."_tab";
			
			//generate the li and a for this tab
			$tabs .= "<li><a href='#$cat_tab'><span>$ucat</span></a></li>";
		
			//for each one, call display_cat to get the settings specific to that cat
			$cat_page = $this->display_cat($cat);
			
			$pages .= <<<PAGES
			<div id="$cat_tab">
				$cat_page
			</div>
PAGES;
		}

		//output it all
		$form .= <<<FORM
		<div id="tabs">
			<ul class="tabs-nav">
				$tabs
			</ul>
			$pages
		</div>
		<input type="hidden" name="location" value="index.php?p=admin&q=modify">
		<input type="hidden" name="refer" value="index.php?p=admin">
		<input type="submit" value="Save" />
		$helpButton
FORM;
	
		//end the form and output
		$form .= "</form>";
		
		echo $form;
		
		return false;
	}
	
	function detect_install_issues() 
	{
		$issues = "";

		//Check that PHP modules are enabled... what do we need? MySQL, XSLT
		$extensions = get_loaded_extensions();

		if (!(array_search('mysql', $extensions) || array_search('mysqli', $extensions))) {
			$issues .= "<p>MySQL must be enabled to Run Xibo</p>";
		}

		if (!array_search('SimpleXML', $extensions)) {
			$issues .= "<p>SimpleXML must be enabled to Run Xibo</p>";
		}
		
		$allow_url_fopen = ini_get("allow_url_fopen");
		if ($allow_url_fopen != 1) {
			$issues .= "<p>You must have allow_url_fopen = On in your PHP.ini file for RSS to function</p>";
		}
		
		return $issues;
	}
	
	function send_email() 
	{
		$db =& $this->db;
		
		$to = Config::GetSetting($db, "mail_to");
		if ($to == "") return true; //they might not have an email recipient set
		
		$from = Config::GetSetting($db, "mail_from");
		if ($from == "") return true;

		$headers = "From: $from" . "\r\n" . "Reply-To: $from" . "\r\n" .
			"X-Mailer: PHP/" . phpversion();
		
		if(mail("$to", "Test Email from Xibo", "This is a test email sent from the Xibo Settings page.", $headers)) 
		{
			//success
			echo "1|The email was accepted for sending";
		}
		else 
		{
			//failure
			echo "0|The email failed to send. To[$to] From[$from]";
		}
		
		exit;
	}

	function display_cat($cat) 
	{
		$db =& $this->db;
		
		$output = "";
		
		$title = ucfirst($cat);
		$output .= "<h3>$title Settings</h3>";
			
		/*
			Firstly we want to individually get the user module
		*/
		if($cat=='user') 
		{
		
			$SQL = "";
			$SQL.= "SELECT settingid, setting, value, helptext FROM setting WHERE setting = 'userModule'";
	
			if(!$results = $db->query($SQL)) trigger_error("Can not get settings:".$db->error(), E_USER_ERROR);
	
			$row = $db->get_row($results);
			$settingid 	= Kit::ValidateParam($row[0], _INT);
			$setting 	= Kit::ValidateParam($row[1], _STRING);
			$value 		= Kit::ValidateParam($row[2], _STRING);
			$helptext	= Kit::ValidateParam($row[3], _HTMLSTRING);
			
			$output .= <<<END
			<h5>$setting</h5>
			<p>$helptext</p>
END;

			//we need to make a drop down out of the files that match a string, in a directory
			$files = scandir("modules/");
			$select = "";
			foreach ($files as $file) {
				$selected = "";
				if($file == $value) $selected = "selected";
				
				if(preg_match("^module_user^", $file)) {
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
		
		/* 
		 * Need to now get all the Misc settings 
		 *
		 */
		$SQL = "";
		$SQL.= sprintf("SELECT settingid, setting, value, helptext FROM setting WHERE type = 'text' AND cat='%s' AND userChange = 1", $cat);

		if(!$results = $db->query($SQL)) trigger_error("Can not get settings:".$db->error(), E_USER_ERROR);

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
				//show another form here, for test
				$output .= <<<END
				<a id="test_email" href="index.php?p=admin&q=send_email">Test Email </a>
END;
			}
		}	
			
		//Drop downs
		$SQL = "";
		$SQL.= sprintf("SELECT settingid, setting, value, helptext, options FROM setting WHERE type = 'dropdown' AND cat='%s' AND userChange = 1", $cat);

		if(!$results = $db->query($SQL)) trigger_error("Can not get settings:".$db->error(), E_USER_ERROR);

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
		return $output;
	}
}
?>