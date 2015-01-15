<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner
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
	
class FormManager
{
	private $db;
	private $user;
	
	public function __construct(database $db, user $user)
	{
		$this->db 	=& $db;
		$this->user =& $user;
	}
	
	/**
	 * Returns a drop down list based on the provided SQL - the ID should be the first field, and the name the second
	 * @return 
	 * @param $SQL Object
	 * @param $list_name Object
	 * @param $selected Object[optional]
	 * @param $callback Object[optional]
	 * @param $flat_list Object[optional]
	 * @param $checkPermissions Object[optional]
	 * @param $userid Object[optional]
	 * @param $permissionLevel Object[optional]
	 * @param $useQueryId Object[optional]
	 */
	public function DropDown($SQL, $list_name, $selected = "", $callback = "", $flat_list = false, $checkPermissions = false, $userid = "", $permissionLevel = "see", $useQueryId = false) 
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
	
		if (!$result = $db->query($SQL)) 
		{
			trigger_error($db->error());
			return __("Query Error");
		}
		
		if ($db->num_rows($result)==0) 
		{
			$list = __("No selections available");
			return $list;
		}
		
		if ($flat_list) 
		{
			//we want to generate a flat list of option | value pairs
			$list = "";
		
			while ($results = $db->get_row($result)) 
			{
				$col0 = $results[0];
				$col1 = $results[1];
				
				if ($checkPermissions) 
				{
					$permissionid = $results[2];
					$ownerid	  = $results[3];
					
					if ($useQueryId)
					{
						list($see_permissions , $edit_permissions) = $user->eval_permission($ownerid, $permissionid, $col0);					
					}
					else
					{
						list($see_permissions , $edit_permissions) = $user->eval_permission($ownerid, $permissionid, $userid);
					}
	
					if (($permissionLevel == "see" && $see_permissions) || $permissionLevel == "edit" && $edit_permissions) {
						$list .= "$col0|$col1,";
					}
				}
				else 
				{
					$list .= "$col0|$col1,";
				}
			}
			//trim the commas
			$list = rtrim($list,",");
		}
		else 
		{
			$list = <<<END
			<select name="$list_name" id="$list_name" $callback>
END;
			while ($results = $db->get_row($result)) 
			{
				$col0 = $results[0];
				$col1 = $results[1];
				
				if ($checkPermissions) 
				{
					$permissionid = $results[2];
					$ownerid	  = $results[3];
					
					if ($useQueryId)
					{
						list($see_permissions , $edit_permissions) = $user->eval_permission($ownerid, $permissionid, $col0);					
					}
					else
					{
						list($see_permissions , $edit_permissions) = $user->eval_permission($ownerid, $permissionid, $userid);
					}
	
					if (($permissionLevel == "see" && $see_permissions) || $permissionLevel == "edit" && $edit_permissions) 
					{
						if ($col0 == $selected) 
						{
							$list .= "<option value='" . $col0 . "' selected>" . $col1 . "</option>\n";
						}
						else 
						{
							$list .= "<option value='" . $col0 . "'>" . $col1 . "</option>\n";
						}
					}
				}
				else 
				{
					if ($col0 == $selected) 
					{
						$list .= "<option value='" . $col0 . "' selected>" . $col1 . "</option>\n";
					}
					else 
					{
						$list .= "<option value='" . $col0 . "'>" . $col1 . "</option>\n";
					}
				}
			}
			$list .= "</select>\n";
		}
		return $list;
	}
}
?>