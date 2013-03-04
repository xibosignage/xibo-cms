<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2012 Daniel Garner
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

class Help extends Data
{
    public function __construct(database $db)
    {
        parent::__construct($db);
    }

    /**
     * Add a new Help Link
     * @param <string> $topic
     * @param <string> $category
     * @param <string> $link
     */
    public function Add($topic, $category, $link)
    {
        // Validation
        if ($topic == '')
            return $this->SetError(__('Topic is a required field. It must be between 1 and 254 characters.'));

        if ($category == '')
            return $this->SetError(__('Category is a required field. It must be between 1 and 254 characters.'));

        if ($link == '')
            return $this->SetError(__('Link is a required field. It must be between 1 and 254 characters.'));

        $SQL = "INSERT INTO `help` (Topic, Category, Link) VALUES ('%s', '%s', '%s') ";
        $SQL = sprintf($SQL, $this->db->escape_string($topic), $this->db->escape_string($category), $this->db->escape_string($link));

        if (!$this->db->query($SQL))
        {
            trigger_error($this->db->error());
            return $this->SetError(25000, __('Unable to Add Help record'));
        }

        return true;
    }

    /**
     * Edit an existing Help Link
     * @param <int> $helpId
     * @param <string> $topic
     * @param <string> $category
     * @param <string> $link
     */
    public function Edit($helpId, $topic, $category, $link)
    {
        // Validation
        if ($helpId == 0)
            return $this->SetError(__('Help Link not selected'));

        if ($topic == '')
            return $this->SetError(__('Topic is a required field. It must be between 1 and 254 characters.'));

        if ($category == '')
            return $this->SetError(__('Category is a required field. It must be between 1 and 254 characters.'));

        if ($link == '')
            return $this->SetError(__('Link is a required field. It must be between 1 and 254 characters.'));

        $SQL = "UPDATE `help` SET Topic = '%s', Category = '%s', Link = '%s' WHERE HelpID = %d ";
        $SQL = sprintf($SQL, $this->db->escape_string($topic), $this->db->escape_string($category), $this->db->escape_string($link), $helpId);

        if (!$this->db->query($SQL))
        {
            trigger_error($this->db->error());
            return $this->SetError(25000, __('Unable to Edit Help record'));
        }

        return true;
    }

    /**
     * Delete a Help Link
     * @param <int> $helpId
     */
    public function Delete($helpId)
    {
        // Validation
        if ($helpId == 0)
            return $this->SetError(__('Help Link not selected'));

        $SQL = "DELETE FROM `help` WHERE HelpID = %d ";
        $SQL = sprintf($SQL, $helpId);

        if (!$this->db->query($SQL))
        {
            trigger_error($this->db->error());
            return $this->SetError(25000, __('Unable to Delete Help record'));
        }

        return true;
    }
}
?>