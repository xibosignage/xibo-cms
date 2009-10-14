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

class helpDAO
{
	private $db;
	private $user;
	private $helpLink;

	function __construct(database $db, user $user)
	{
		$this->db 	=& $db;
		$this->user =& $user;

		$topic	 	= Kit::GetParam('Topic', _REQUEST, _WORD);
		$category 	= Kit::GetParam('Category', _REQUEST, _WORD, 'General');

		if ($topic != '')
		{
			Debug::LogEntry($db, 'audit', 'Help requested for Topic = ' . $topic);

			// Look up this help topic / category in the db
			$SQL = "SELECT Link FROM help WHERE Topic = '%s' and Category = '%s'";
			$SQL = sprintf($SQL, $db->escape_string($topic), $db->escape_string($category));

			Debug::LogEntry($db, 'audit', $SQL);

			if(!$results = $db->query($SQL))
			{
				trigger_error($db->error());
				trigger_error(__('Error getting Help Link'), E_USER_ERROR);
			}

			if ($db->num_rows($results) != 0)
			{
				$row 	= $db->get_row($results);
				$link 	= $row[0];

				// Store the link for the requested help page
				$this->helpLink = $link;
			}
			else
			{
				trigger_error(sprintf(__('No help file found for Topic %s and Category %s.'), $topic, $category), E_USER_ERROR);
			}
		}
		else
		{
			trigger_error(__('You must specify a help page.'), E_USER_ERROR);
		}

		return true;
	}

	/**
	 * Displays the particular help subject / page
	 * @return
	 */
	function Display()
	{
		$response	= new ResponseManager();
		$helpLink 	= $this->helpLink;
                $width          = 1000;
                $height         = 650;

		$out 		= '<iframe src="' . $helpLink . '" width="' . ($width - 35) . '" height="' . ($height - 60) . '"></iframe>';

		$response->SetFormRequestResponse($out, __('Help'), $width, $height);
		$response->Respond();

		return true;
	}

	/**
	 * No display page functionaility
	 * @return
	 */
	function displayPage()
	{
		return false;
	}

	/**
	 * No onload
	 * @return
	 */
	function on_page_load()
	{
		return '';
	}

	/**
	 * No page heading
	 * @return
	 */
	function echo_page_heading()
	{
		return true;
	}
}
?>