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
 
class database 
{

    public $error_text;

    //connects to the database
    function __construct() 
	{
		
    }
    
    function connect_db($dbhost, $dbuser, $dbpass) 
	{
    	//open the db link
        $dblink = mysql_connect($dbhost, $dbuser, $dbpass);
        
        if(!$dblink) 
		{
        	return false;
        }
		
        return true;	
    }
    
    function select_db($dbname) 
	{
    	//select out the correct db name
        if(!mysql_select_db($dbname)) return false;
        
        return true;
    }

    //performs a query lookup and returns the result object
    function query($SQL, $nolog = false) 
	{
		//if (!$nolog) Debug::LogEntry($this, 'audit', 'Running SQL: [' . $SQL . ']', '', 'query');
		// creates a loop!
		  			
        if(!$result = mysql_query($SQL)) 
		{
            $this->error_text="The query [".$SQL."] failed to execute";
            return false;
        }
        else 
		{
            return $result;
        }
    }
    
    function insert_query($SQL) 
	{
    	//executes a SQL query and returns the ID of the insert
    	if(!$result = mysql_query($SQL)) 
		{
            $this->error_text="The query [".$SQL."] failed to execute";
            return false;
        }
        else 
		{
            return mysql_insert_id();
        }
    }

    //gets the current row from the result object
    function get_row($result) 
	{
        return mysql_fetch_row($result);
    }
	
	function get_assoc_row($result) 
	{
        return mysql_fetch_assoc($result);
    }


    //gets the number of rows
    function num_rows($result) 
	{
        return mysql_num_rows($result);
    }
    
    function escape_string($string) 
	{
    	return mysql_real_escape_string($string);
    }

    //returns the error text to display
    function error() 
	{
        try 
		{
            $this->error_text .= "<br />MySQL error: ".mysql_error();
            return $this->error_text;
        }
        catch (Exception $e) 
		{
            echo $e->getMessage();
        }
    }
}

?>