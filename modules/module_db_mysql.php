<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2012 Daniel Garner
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
    private $connection;
    public $error_text;
    
    function connect_db($dbhost, $dbuser, $dbpass) 
    {
        //open the db link
        if (!$this->connection = @mysql_connect($dbhost, $dbuser, $dbpass))
            return false;

        return true;
    }
    
    function select_db($dbname) 
    {
    	//select out the correct db name
        if (!mysql_select_db($dbname, $this->connection))
            return false;

        return $this->query("SET NAMES 'utf8'", $this->connection);
    }

    /**
     * Runs a query on the database
     * @param <string> $SQL
     * @param <args> $args (for sprintf)
     * @return <type>
     */
    function query($SQL) 
    {
        if ($SQL == '')
        {
            $this->error_text = 'No SQL provided';
            return false;
        }

        // sprintf and escape the string as necessary using the arguments provided
        $args = func_get_args();

        if (count($args) > 1)
            $SQL = $this->SqlPrintF($args);

        // Run the query
        if(!$result = mysql_query($SQL, $this->connection))
	{
            $this->error_text = 'The query [' . $SQL . '] failed to execute';
            return false;
        }

        return $result;
    }
    
    function insert_query($SQL) 
    {
    	//executes a SQL query and returns the ID of the insert
    	if(!$result = mysql_query($SQL, $this->connection))
	{
            $this->error_text="The query [".$SQL."] failed to execute";
            return false;
        }
        else 
	{
            return mysql_insert_id();
        }
    }

    // gets the current row from the result object
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

    // Gets the number of fields
    function num_fields($result) 
    {
        return mysql_num_fields($result);
    }
    
    function escape_string($string) 
    {
    	return mysql_real_escape_string($string);
    }

    /**
     * Gets a Single row using the provided SQL
     * Returns false if SQL error or no records found
     * @param <string> $SQL
     * @param <bool> $assoc
     */
    public function GetSingleRow($SQL, $assoc = true)
    {
        if (!$result = $this->query($SQL))
            return false;

        if ($this->num_rows($result) == 0)
        {
            $this->error_text = 'No results returned';
            return false;
        }

        if ($assoc)
        {
            return $this->get_assoc_row($result);
        }
        else
        {
            return $this->get_row($result);
        }
    }

    /**
     * Gets a single value from the provided SQL
     * @param <string> $SQL
     * @param <string> $columnName
     * @param <int> $dataType
     * @return <type>
     */
    public function GetSingleValue($SQL, $columnName, $dataType)
    {
        if (!$row = $this->GetSingleRow($SQL))
            return false;

        if (!isset($row[$columnName]))
        {
            $this->error_text = 'No such column or column is null';
            return false;
        }

        return Kit::ValidateParam($row[$columnName], $dataType);
    }

    /**
     * Gets a count of rows returned by the specified SQL
     * @param <type> $SQL
     */
    public function GetCountOfRows($SQL)
    {
        if (!$result = $this->query($SQL))
            return 0;
            
        return $this->num_rows($result);
    }

    /**
     * Get an Array of Results
     * @param <type> $SQL
     * @return <type>
     */
    public function GetArray($SQL, $assoc = true)
    {
        if (!$result = $this->query($SQL))
            return false;

        $array = array();

        if ($assoc)
        {
            while ($row = $this->get_assoc_row($result))
                $array[] = $row;
        }
        else
        {
            while ($row = $this->get_row($result))
                $array[] = $row;
        }

        return $array;
    }

    /**
     * Returns the Error to display
     * @return <type>
     */
    function error() 
    {
        try 
	{
            $this->error_text .= mysql_error($this->connection);
            
            return $this->error_text;
        }
        catch (Exception $e) 
	{
            return $e->getMessage();
        }
    }
    
    /**
     * Runs sprintf over a SQL string and a list of args
     * @param <string> $SQL
     * @param <type> $args (for sprintf)
     * @return <type>
     */
    public function SqlPrintF($args)
    {
        $sql  = array_shift($args);
        
        if (count($args) == 1 && is_array($args[0]))
        {
            $args = $args[0];
        }
        
        $args = array_map(array($this, 'SqlEscapeString'), $args);
        
        return vsprintf($sql, $args);
    }

    private function SqlEscapeString($s)
    {
        if (is_string($s))
        {
            return mysql_real_escape_string($s, $this->connection);
        }
        else if (is_null($s))
        {
            return NULL;
        }
        else if (is_bool($s))
        {
            return intval($s);
        }
        else if (is_int($s) || is_float($s))
        {
            return $s;
        }
        else
        {
            return mysql_real_escape_string(strval($s), $this->connection);
        }
    }
}

?>
