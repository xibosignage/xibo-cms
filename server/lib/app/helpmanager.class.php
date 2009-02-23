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

class HelpManager
{
	private $db;
	private $user;
	
	/**
	 * Constructs the Module Manager.
	 * @return 
	 * @param $db Object
	 * @param $user Object
	 */
	public function __construct(database $db, User $user)
	{
		$this->db 		=& $db;
		$this->user 	=& $user;
	}
	
	/**
	 * Help Button
	 * @return 
	 * @param $location Object
	 * @param $return Object[optional]
	 */
	public function HelpButton($location, $return = false) 
	{
		$db 	=& $this->db;
		
		$helpBase = Config::GetSetting($db, 'HELP_BASE');
		
		$link = $helpBase . "?p=$location";
		
		$button = <<<END
		<input type="button" onclick="window.open('$link')" value="Help" />
END;
	
		if ($return)
		{
			return $button;
		}
		else
		{
			echo $button;
			return true;
		}
	}
	
	/**
	 * Help Icon
	 * @return 
	 * @param $title Object
	 * @param $return Object[optional]
	 * @param $image Object[optional]
	 * @param $alt Object[optional]
	 */
	public function HelpIcon($title, $return = false, $image = "img/forms/info_icon.gif", $alt = "Hover for more info")
	{
		$button = <<<END
		<img src="$image" alt="$alt" title="$title">
END;
		
		if ($return)
		{
			return $button;
		}
		else
		{
			echo $button;
			return true;
		}
	}
}
?>
