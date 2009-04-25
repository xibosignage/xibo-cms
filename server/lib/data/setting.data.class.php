<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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

class Setting extends Data
{
	public function Edit($setting, $value)
	{
		$db =& $this->db;
		
		$SQL = sprintf("UPDATE setting SET value = '%s' WHERE setting = '%s' ", $db->escape_string($value), $db->escape_string($setting));

		if(!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25000, __('Update of settings failed'));
			
			return false;
		}
		
		return true;
	}
}
?>