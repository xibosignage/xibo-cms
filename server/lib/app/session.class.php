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

class Session 
{
	
	private $db;
	private $max_lifetime;
	private $key;
	
	public $isExpired = 1;

	function __construct(database $db) {
		$this->db =& $db;
		
		session_set_save_handler(array(&$this, 'open'),
                             array(&$this, 'close'),
                             array(&$this, 'read'),
                             array(&$this, 'write'),
                             array(&$this, 'destroy'),
                             array(&$this, 'gc'));
    	register_shutdown_function('session_write_close');
    	
    	
		session_start();
	}
	
	function open($save_path, $session_name) 
	{
		$db =& $this->db;
		
		$this->max_lifetime = ini_get('session.gc_maxlifetime');
		return true;
	}

	function close() 
	{
		$db =& $this->db;
		
		$this->gc($this->max_lifetime);
		return true;
	}

	function read($key) 
	{
		$db =& $this->db;
		
		$userAgent		= $_SERVER['HTTP_USER_AGENT'];
		$remoteAddr		= $_SERVER['REMOTE_ADDR'];
		
		$this->key 		= $key;
		$newExp 		= time() + $this->max_lifetime;
		
		$this->gc($this->max_lifetime);
		
		if(isset($_POST['SecurityToken'])) 
		{		
			$securityToken = Kit::GetParam('SecurityToken', _POST, _STRING);
			
			if (!$securityToken)
			{
				log_entry($db, "error", "Invalid Security Token");
				$securityToken = null;
			}
		}
		else
		{
			$securityToken = null;
		}
		
		$SQL  = " SELECT session_data, IsExpired, SecurityToken FROM session ";
		$SQL .= " WHERE session_id = '$key' ";
		$SQL .= " AND RemoteAddr = '$remoteAddr' ";
		
		if (!$result = $db->query($SQL));
		
		if ($db->num_rows($result) != 0) 
		{
			
			$row = $db->get_row($result);
			
			// We have the Key and the Remote Address.
			if ($securityToken == null)
			{			
				// If there is no security token then obey the IsExpired
				$this->isExpired = $row[1];	
			}
			elseif ($securityToken == $row[2])
			{
				// We have a security token, so dont require a login
				$this->isExpired = 0;
				
				if (!$db->query("UPDATE session SET session_expiration = $newExp, isExpired = 0 WHERE session_id = '$key' "))
				{
					log_entry($db, "error", $db->error());
				}			
			}
			else
			{
				// Its set - but its wrong - not good
				log_entry($db, "error", "Incorrect SecurityToken from " . $remoteAddr);
				
				$this->isExpired = 1;
			}
			
			// Either way - update this SESSION so that the security token is NULL
			$db->query("UPDATE session SET SecurityToken = NULL WHERE session_id = '$key' ");
			
			return($row[0]);
		}
		else 
		{
			$empty = '';
			return settype($empty, "string");
		}
	}
	
	function write($key, $val) 
	{
		$db 			=& $this->db;
		
		$val 			= addslashes($val);
		
		$newExp 		= time() + $this->max_lifetime;
		$lastaccessed 	= date("Y-m-d H:i:s");
		$userAgent		= $_SERVER['HTTP_USER_AGENT'];
		$remoteAddr		= $_SERVER['REMOTE_ADDR'];
		
		$result = $db->query("SELECT session_id FROM session WHERE session_id = '$key'");
		
		if ($db->num_rows($result) == 0) 
		{
			//INSERT
			$SQL = "INSERT INTO session (session_id, session_data, session_expiration, LastAccessed, LastPage, userID, IsExpired, UserAgent, RemoteAddr) 
					VALUES ('$key','$val',$newExp,'$lastaccessed','login', NULL, 0, '$userAgent', '$remoteAddr')";
		}
		else 
		{
			//UPDATE
			
			// Punch a very small hole in the authentication system
			// we do not want to update the expiry time of a session if it is the Clock Timer going off
			$page	= Kit::GetParam('p', _REQUEST, _WORD);
			$query	= Kit::GetParam('q', _REQUEST, _WORD);
			
			if ($page == 'clock' && $query == 'GetClock') return true;
			if ($page == 'index' && $query == 'PingPong') return true;
			
			$SQL = "UPDATE session SET ";
			$SQL .= " session_data = '$val', ";
			$SQL .= " session_expiration = '$newExp', ";
			$SQL .= " lastaccessed 	= '$lastaccessed' ";
			$SQL .= " WHERE session_id = '$key' ";
		}
		
		if(!$db->query($SQL)) 
		{
			log_entry($db, "error", $db->error());
			return(false);
		}
		return true;
	}

	function destroy($key) 
	{
		$db =& $this->db;
		
		$SQL = "UPDATE session SET IsExpired = 1 WHERE session_id = '$key'";
		
		$result = $db->query("$SQL"); 
		
		if (!$result) log_entry($db,'audit',$db->error());
		
		return $result;
	}

	function gc($max_lifetime) 
	{
		$db =& $this->db;
		
		return $db->query("UPDATE session SET IsExpired = 1 WHERE session_expiration < ".time());
	}
	
	function set_user($key, $userid) 
	{
		$db =& $this->db;
		
		$SQL = "UPDATE session SET userID = $userid WHERE session_id = '$key' ";
		
		if(!$db->query($SQL)) {
			trigger_error($db->error(), E_USER_NOTICE);
			return(false);
		}
		return true;
	}
	
	// Update the session (after login)
	static function RegenerateSessionID() 
	{
	    $old_sess_id = session_id();
		
	    session_regenerate_id(false);
		
	    $new_sess_id = session_id();
	       
	    $query = "UPDATE `session` SET `session_id` = '$new_sess_id' WHERE session_id = '$old_sess_id'";
	        mysql_query($query);
	}
	
	function set_page($key, $lastpage) 
	{
		$db =& $this->db;
		
		$_SESSION['pagename'] = $lastpage;
		
		$SQL = "UPDATE session SET LastPage = '$lastpage' WHERE session_id = '$key' ";
		
		if(!$db->query($SQL)) {
			trigger_error($db->error(), E_USER_NOTICE);
			return(false);
		}
		return true;
	}
	
	function setIsExpired($isExpired)
	{
		$db =& $this->db;

		$this->isExpired = $isExpired;
		
		$SQL = "UPDATE session SET IsExpired = $this->isExpired WHERE session_id = '$this->key'";
		
		if (!$db->query($SQL))
		{
			log_entry($db, "error", $db->error());
		}
	}
	
	public function setSecurityToken($token)
	{
		$db =& $this->db;
		
		$SQL = "UPDATE session SET securityToken = '$token' WHERE session_id = '$this->key'";
		
		if (!$db->query($SQL))
		{
			log_entry($db, "error", $db->error());
		}
	}
	
	public static function Set($key, $value)
	{
		$_SESSION[$key] = $value;
	}
}
?>