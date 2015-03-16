<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

class HelpManager
{
    /**
     * Outputs a help link
     * @return 
     * @param $topic Object[optional]
     * @param $category Object[optional]
     */
    public static function Link($topic = "", $category = "General")
    {
        // if topic is empty use the page name
        $topic  = ($topic == '') ? Kit::GetParam('p', _REQUEST, _WORD) : $topic;
        $topic  = ucfirst($topic);

        // Get the link
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT Link FROM help WHERE Topic = :topic and Category = :cat');
            $sth->execute(array('topic' => $topic, 'cat' => $category));

            if (!$link = $sth->fetchColumn(0)) {
                $sth->execute(array('topic' => $topic, 'cat' => 'General'));
                $link = $sth->fetchColumn(0);
            }

            return Config::GetSetting('HELP_BASE') . $link;
        }
        catch (Exception $e) {
            return false;
        }
    }

    public static function rawLink($link)
    {
        return Config::GetSetting('HELP_BASE') . $link;
    }
}
?>
