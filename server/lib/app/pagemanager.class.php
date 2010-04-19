<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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

// I think this will be where the magic happens.
// This will control the callouts from the included page
class PageManager
{
	private $db;
	private $user;
	
	private $p;
	private $q;

	private $page;
	private $path;
	private $ajax;
	private $userid;
	private $authed;
	private $thePage;
	
	function __construct(database $db, user $user, $page)
	{
		$this->db 		=& $db;
		$this->user 	=& $user;
		$this->path 	= 'lib/pages/' . $page . '.class.php';
		$this->page 	= $page . 'DAO';
		$this->p	 	= $page;
		$this->authed 	= false;
		
		$this->ajax		= Kit::GetParam('ajax', _REQUEST, _BOOL, false);
		$this->q		= Kit::GetParam('q', _REQUEST, _WORD);
		$this->userid 	= Kit::GetParam('userid', _SESSION, _INT);
		
		if(!class_exists($this->page)) 
		{
			require_once($this->path);
		}
		
		return;
	}
	
	/**
	 * Checks the Security of the logged in user
	 * @return 
	 */
	public function Authenticate()
	{
		$db 		=& $this->db;
		$user 		=& $this->user;
		
		// create a user object (will try to login)
		// we must do this after executing any functions otherwise we will be logged
		// out again before exec any log in function calls		
		if ($this->q != 'login' && $this->q != 'logout' && $this->q != 'GetClock' && $this->q != 'About')
		{ 
			// Attempt a user login
			if (!$user->attempt_login($this->ajax))
			{
				return false;
			}
			
			$this->authed = $user->PageAuth($this->p);
		}
		else
		{
			// automatically have permission for the login / forgotten details functions
			// these are the only 2 functions at the moment that allow anonomous access
			$this->authed = true;
		}
		
		return true;
	}
	
	/**
	 * Renders this page
	 * @return 
	 */
	public function Render()
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
		
		if (!$this->authed)
		{
			// Output some message to say that we are not authed
			trigger_error(__("You do not have permission to access this page."), E_USER_ERROR);
			exit;
		}
		
		// Create the requested page
		$this->thePage = new $this->page($db, $user);
		
		if ($this->q != '') 
		{
			if (method_exists($this->thePage, $this->q)) 
			{
				$function		= $this->q;
				$reloadLocation = $this->thePage->$function();
			}
			else 
			{
				trigger_error($this->p . ' does not support the function: ' . $this->q, E_USER_ERROR);
			}
			
			if ($this->ajax) exit;
		
		    // once we have dealt with it, reload the page      	
		    Kit::Redirect($reloadLocation);
		}
		else 
		{
			// Display a page instead
			include("template/header.php");
			
			$this->thePage->displayPage();
			
			include("template/footer.php");
		}
		
		return;
	}
}
?>