<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2012 Daniel Garner
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

class Data
{
	protected $db;
	private $error;
	private $errorNo;
	private $errorMessage;

        /**
         * Data Class
         * @param database $db
         */
	public function __construct(database $db = null)
	{
		if ($db != null)
			$this->db =& $db;
		
		$this->error		= false;
		$this->errorNo 		= 0;
		$this->errorMessage	= '';
	}
	
	/**
	 * Gets the error state
	 * @return bool
	 */
	public function IsError()
	{
		return $this->error;
	}
	
	/**
	 * Gets the Error Number
	 * @return int
	 */
	public function GetErrorNumber()
	{
		return $this->errorNo;
	}
	
	/**
	 * Gets the Error Message
	 * @return string
	 */
	public function GetErrorMessage()
	{
		return $this->errorMessage;
	}
	
	/**
	 * Sets the Error for this Data object
	 * @return bool
	 * @param $errNo mixed
	 * @param $errMessage string
	 */
	protected function SetError($errNo, $errMessage = '')
	{
		$this->error		= true;

		// Is an error No provided?
		if (!is_numeric($errNo)) {
			$errMessage = $errNo;
			$errNo = -1;
		}

		$this->errorNo 		= $errNo;
		$this->errorMessage	= $errMessage;
		
		Debug::LogEntry('audit', sprintf('Data Class: Error Number [%d] Error Message [%s]', $errNo, $errMessage), 'Data Module', 'SetError');

        // Return false so that we can use this method as the return call for parent methods
		return false;
	}

	protected function ThrowError($errNo, $errMessage = '') {
		$this->SetError($errNo, $errMessage);
		throw new Exception(sprintf('Data Class: Error Number [%d] Error Message [%s]', $errNo, $errMessage));
	}


    /**
     * Json Serialize
     * @return array
     */
    public function jsonSerialize()
    {
        $exclude = (property_exists($this, 'jsonExclude')) ? $this->jsonExclude : array();
        $exclude[] = 'jsonExclude';

        $properties = \Xibo\Helper\ObjectVars::getObjectVars($this);
        $json = array();
        foreach ($properties as $key => $value) {
            if (!in_array($key, $exclude)) {
                $json[$key] = $value;
            }
        }
        return $json;
    }
}
