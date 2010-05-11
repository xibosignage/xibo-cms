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

	function __construct(database $db) 
	{
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
		
		$userAgent	= Kit::GetParam('HTTP_USER_AGENT', $_SERVER, _STRING, 'No user agent');
                $userAgent      = substr($userAgent, 0, 253);
		$remoteAddr	= Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING);
		$securityToken	= Kit::GetParam('SecurityToken', _POST, _STRING, null);
		
		$this->key 		= $key;
		$newExp 		= time() + $this->max_lifetime;
		
		$this->gc($this->max_lifetime);
		
		// Get this session		
		$SQL  = " SELECT session_data, IsExpired, SecurityToken FROM session ";
		$SQL .= " WHERE session_id = '%s' ";
		$SQL .= " AND UserAgent = '%s' ";
		
		$SQL 	= sprintf($SQL, $db->escape_string($key), $db->escape_string($userAgent));
		
		$result = $db->query($SQL);
		
		if ($db->num_rows($result) != 0) 
		{
			// Get the row
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
				
				if (!$db->query(sprintf("UPDATE session SET session_expiration = $newExp, isExpired = 0 WHERE session_id = '%s' ", $db->escape_string($key))))
				{
					Debug::LogEntry($db, "error", $db->error());
				}
			}
			else
			{
				// Its set - but its wrong - not good
				Debug::LogEntry($db, "error", "Incorrect SecurityToken from " . $remoteAddr);
				
				$this->isExpired = 1;
			}
			
			// Either way - update this SESSION so that the security token is NULL
			$db->query(sprintf("UPDATE session SET SecurityToken = NULL WHERE session_id = '%s' ", $db->escape_string($key)));
			
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
		$db 		=& $this->db;

		$newExp 	= time() + $this->max_lifetime;
		$lastaccessed 	= date("Y-m-d H:i:s");
		$userAgent	= Kit::GetParam('HTTP_USER_AGENT', $_SERVER, _STRING, 'No user agent');
                $userAgent      = substr($userAgent, 0, 253);
		$remoteAddr	= Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING);
		
		$result = $db->query(sprintf("SELECT session_id FROM session WHERE session_id = '%s'", $db->escape_string($key)));
		
		if ($db->num_rows($result) == 0) 
		{
			//INSERT
			$SQL = "INSERT INTO session (session_id, session_data, session_expiration, LastAccessed, LastPage, userID, IsExpired, UserAgent, RemoteAddr) 
					VALUES ('%s', '%s', %d, '%s', 'login', NULL, 0, '%s', '%s')";
					
			$SQL = sprintf($SQL, $db->escape_string($key), $db->escape_string($val), $newExp, $db->escape_string($lastaccessed), $db->escape_string($userAgent), $db->escape_string($remoteAddr));
		}
		else 
		{
			// UPDATE
                        //
                        // Punch a very small hole in the authentication system
			// we do not want to update the expiry time of a session if it is the Clock Timer going off
			$page	= Kit::GetParam('p', _REQUEST, _WORD);
			$query	= Kit::GetParam('q', _REQUEST, _WORD);

			if ($page == 'clock' && $query == 'GetClock') return true;
			if ($page == 'index' && $query == 'PingPong') return true;

			$SQL = "UPDATE session SET ";
			$SQL .= " session_data = '%s', ";
			$SQL .= " session_expiration = %d, ";
			$SQL .= " lastaccessed 	= '%s', ";
			$SQL .= " RemoteAddr 	= '%s' ";
			$SQL .= " WHERE session_id = '%s'";
			
			$SQL = sprintf($SQL, $db->escape_string($val), $newExp, $db->escape_string($lastaccessed), $db->escape_string($remoteAddr), $db->escape_string($key));
		}
		
		if(!$db->query($SQL)) 
		{
			Debug::LogEntry($db, "error", $db->error());
			return(false);
		}
		
		return true;
	}

	function destroy($key) 
	{
		$db =& $this->db;
		
		$SQL = sprintf("UPDATE session SET IsExpired = 1 WHERE session_id = '%s'", $db->escape_string($key));
		
		$result = $db->query("$SQL"); 
		
		if (!$result) Debug::LogEntry($db,'audit',$db->error());
		
		return $result;
	}

	function gc($max_lifetime)
	{
            $db =& $this->db;

            // Delete sessions older than 10 times the max lifetime
            $SQL = sprintf("DELETE FROM session WHERE IsExpired = 1 AND session_expiration < %d", time() - ($max_lifetime * 10));
            $db->query($SQL);

            return $db->query(sprintf("UPDATE session SET IsExpired = 1 WHERE session_expiration < %d", time()));
	}
	
	function set_user($key, $userid) 
	{
		$db =& $this->db;
		
		$SQL = sprintf("UPDATE session SET userID = %d WHERE session_id = '%s' ",$userid, $db->escape_string($key));
		
		if(!$db->query($SQL)) 
		{
			trigger_error($db->error(), E_USER_NOTICE);
			return(false);
		}
		return true;
	}
	
	/**
	 * Updates the session ID with a new one
	 * @return 
	 */
	public function RegenerateSessionID($oldSessionID)
        {
            $db =& $this->db;

            session_regenerate_id(false);

            $new_sess_id = session_id();

            $this->key = $new_sess_id;

            $query = sprintf("UPDATE session SET session_id = '%s' WHERE session_id = '%s'", $db->escape_string($new_sess_id), $db->escape_string($oldSessionID));
            $db->query($query);
        }
	
	function set_page($key, $lastpage) 
	{
		$db =& $this->db;
		
		$_SESSION['pagename'] = $lastpage;
		
		$SQL = sprintf("UPDATE session SET LastPage = '%s' WHERE session_id = '%s' ", $db->escape_string($lastpage), $db->escape_string($key));
		
		if(!$db->query($SQL)) 
		{
			trigger_error($db->error(), E_USER_NOTICE);
			return(false);
		}
		return true;
	}
	
	function setIsExpired($isExpired)
	{
		$db =& $this->db;

		$this->isExpired = $isExpired;
		
		$SQL = sprintf("UPDATE session SET IsExpired = $this->isExpired WHERE session_id = '%s'", $db->escape_string($this->key));
		
		if (!$db->query($SQL))
		{
			Debug::LogEntry($db, "error", $db->error());
		}
	}
	
	public function setSecurityToken($token)
	{
		$db =& $this->db;
		
		$SQL = sprintf("UPDATE session SET securityToken = '%s' WHERE session_id = '%s'", $db->escape_string($token), $db->escape_string($this->key));
		
		if (!$db->query($SQL))
		{
			Debug::LogEntry($db, "error", $db->error());
		}
	}
	
	public static function Set($key, $value)
	{
		$_SESSION[$key] = $value;
	}
}
?>