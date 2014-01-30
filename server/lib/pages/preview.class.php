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
        // Render a specific layout in the previewer
        // layoutid must be provided
        $output = <<<EOT
            <!DOCTYPE html>
            <html>
                <head>
                    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
                    <title>Preview for Layout $this->layoutid</title> 
                    <link rel="stylesheet" type="text/css" href="modules/preview/html-preview.css" />
                    <script type="text/JavaScript" src="theme/default/libraries/jquery/jquery-1.9.1.js"></script>
                    <script type="text/JavaScript" src="modules/preview/html5Preloader.js"></script>
                    <script type="text/JavaScript" src="modules/preview/html-preview.js"></script>
                </head>
                <body onload="dsInit($this->layoutid)">
                    <div id="player">
                        <div id="info"></div>
                        <div id="log"></div>
                        <div id="screen">
                            <div id="splash"></div>
                            <div id="end"><a href="javascript:history.go(0)" style="text-decoration: none; color: #ffffff">Play again?</a></div>
                        </div>
                        
                    </div>
                </body>
            </html>
EOT;
        print $output;
    }

    function getXlf()
    {
        print $this->xml;
    }

    /**
     * Returns an image stream to the browser - for the mediafile specified.
     * @return 
     */
    function GetImage()
    {
        $db =& $this->db;

        $mediaID = Kit::GetParam('id', _GET, _INT, 0);
        $proportional = Kit::GetParam('proportional', _GET, _BOOL, true);
        $thumb = Kit::GetParam('thumb', _GET, _BOOL, false);
        $dynamic = isset($_REQUEST['dynamic']);

        if ($mediaID == 0)
            die ('No media ID provided');

        // Get the file URI
        $SQL = sprintf("SELECT StoredAs FROM media WHERE MediaID = %d", $mediaID);

        if (!$file = $db->GetSingleValue($SQL, 'StoredAs', _STRING))
            die ('No media found for that media ID');

        //File upload directory.. get this from the settings object
        $library = Config::GetSetting("LIBRARY_LOCATION");
        $fileName = $library . $file;

        // If we are a thumb request then output the cached thumbnail
        if ($thumb)
            $fileName = $library . 'tn_' . $file;

        // If the thumbnail doesnt exist then create one
        if (!file_exists($fileName))
        {
            Debug::LogEntry('audit', 'File doesnt exist, creating a thumbnail for ' . $fileName);

            if (!$info = getimagesize($library . $file))
                die($library . $file . ' is not an image');

            ResizeImage($library . $file, $fileName, 80, 80, $proportional, 'file');
        }
        
        // Get the info for this new temporary file
        if (!$info = getimagesize($fileName))
        {
            echo $fileName . ' is not an image';
            exit;
        }

        if ($dynamic && $info[2])
        {
            $width  = Kit::GetParam('width', _GET, _INT);
            $height = Kit::GetParam('height', _GET, _INT);
            
            header('Pragma: public');
            header('Cache-Control: max-age=86400');
            header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));

            // dynamically create an image of the correct size - used for previews
            ResizeImage($fileName, '', $width, $height, $proportional, 'browser');

            exit;
        }

        if (!$image = file_get_contents($fileName))
        {
            //not sure
            Debug::LogEntry('audit', "Cant find: $uid", 'module', 'GetImage');

            $fileName = 'theme/default/img/forms/filenotfound.png';
            $image  = file_get_contents($fileName);
        }

        $size = getimagesize($fileName);

        //Output the image header
        header("Content-Type: {$size['mime']}");

        echo $image;
        exit;
    }

}
?>
