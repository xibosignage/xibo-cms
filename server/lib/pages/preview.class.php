<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014 Alex Harrington
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

class previewDAO 
{
    private $db;
    private $user;
    private $auth;
	private $has_permissions = true;
	
	private $layoutid;
	private $layout;
	private $retired;
	private $description;
	private $tags;
	
	private $xml;

    function __construct(database $db, user $user) 
    {
        $this->db   =& $db;
        $this->user =& $user;
        $this->layoutid = Kit::GetParam('layoutid', _REQUEST, _INT);

        // Include the layout data class
        include_once("lib/data/layout.data.class.php");

        //if we have modify selected then we need to get some info
        if ($this->layoutid != '')
        {
            // get the permissions
            Debug::LogEntry('audit', 'Loading permissions for layoutid ' . $this->layoutid);

            $this->auth = $user->LayoutAuth($this->layoutid, true);

            if (!$this->auth->view)
                trigger_error(__("You do not have permissions to view this layout"), E_USER_ERROR);

            $sql  = " SELECT layout, description, userid, retired, tags, xml FROM layout ";
            $sql .= sprintf(" WHERE layoutID = %d ", $this->layoutid);

            if (!$results = $db->query($sql))
            {
                trigger_error($db->error());
                trigger_error(__("Cannot retrieve the Information relating to this layout. The layout may be corrupt."), E_USER_ERROR);
            }

            if ($db->num_rows($results) == 0)
                $this->has_permissions = false;

            while($aRow = $db->get_row($results))
            {
                $this->layout = Kit::ValidateParam($aRow[0], _STRING);
                $this->description 	= Kit::ValidateParam($aRow[1], _STRING);
                $this->retired = Kit::ValidateParam($aRow[3], _INT);
                $this->tags = Kit::ValidateParam($aRow[4], _STRING);
                $this->xml = $aRow[5];
            }
        }
    }
	
    function displayPage() 
    {
        return false;
    }

    function render()
    {
        $favicon = Theme::ImageUrl('favicon.ico');
        
        // Render a specific layout in the previewer
        // layoutid must be provided
        $pfl = __('Preview for Layout');
        
        $output = <<<EOT
            <!DOCTYPE html>
            <html>
                <head>
                    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
                    <title>$pfl $this->layoutid</title> 
                    <link rel="stylesheet" type="text/css" href="modules/preview/html-preview.css" />
                    <script type="text/JavaScript" src="theme/default/libraries/jquery/jquery-1.9.1.js"></script>
                    <script type="text/JavaScript" src="modules/preview/html5Preloader.js"></script>
                    <script type="text/JavaScript" src="modules/preview/html-preview.js"></script>
                    <link rel="shortcut icon" href="$favicon" />
                </head>
                <body onload="dsInit($this->layoutid)">
                    <div id="player">
                        <div id="info"></div>
                        <div id="log"></div>
                        <div id="screen">
                            <div id="splash">
                                <div id="loader"></div>
                                <div id="loaderCaption"><p>
EOT;

        $output .= __("Loading layout...");
        $output .= "</p></div>";
        $output .= "</div>";
        $output .= '<div id="end"><a href="javascript:history.go(0)" style="text-decoration: none; color: #ffffff">';
        $output .= __("Play again?");
        $output .= "</a></div></div></div></body></html>";

        print $output;
    }

    function getXlf()
    {
        print $this->xml;
    }
}
?>
