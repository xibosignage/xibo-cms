<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2010-14 Daniel Garner
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

class Tag extends Data
{
	public function add($tag)
	{
		try {
		    $dbh = PDOConnect::init();
		
		    // See if it exists
		    $sth = $dbh->prepare('SELECT * FROM `tag` WHERE tag = :tag');
		    $sth->execute(array('tag' => $tag));

		    if ($row = $sth->fetch()) {
		    	return Kit::ValidateParam($row['tagId'], _INT);
		    }
		    
		    // Insert if not
		    $sth = $dbh->prepare('INSERT INTO `tag` (tag) VALUES (:tag)');
		    $sth->execute(array(
		            'tag' => $tag
		        ));
		  
		  	return $dbh->lastInsertId();
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
		
		    if (!$this->IsError())
		        $this->SetError(1, __('Unknown Error'));
		
		    return false;
		}
	}
}
?>
