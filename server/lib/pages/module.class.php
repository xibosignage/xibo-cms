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

class moduleDAO 
{
	private $db;
	private $user;
	private $module;

    /**
     * Module constructor.
     * @return
     * @param $db Object
     */
    function __construct(database $db, user $user)
    {
        $this->db 	=& $db;
        $this->user =& $user;

        $mod = Kit::GetParam('mod', _REQUEST, _WORD);

        // If we have the module - create an instance of the module class
        // This will only be true when we are displaying the Forms
        if ($mod != '')
        {
            require_once("modules/$mod.module.php");

            // Try to get the layout, region and media id's
            $layoutid   = Kit::GetParam('layoutid', _REQUEST, _INT);
            $regionid   = Kit::GetParam('regionid', _REQUEST, _STRING);
            $mediaid    = Kit::GetParam('mediaid', _REQUEST, _STRING);
            $lkid       = Kit::GetParam('lkid', _REQUEST, _INT);

            Debug::LogEntry($db, 'audit', 'Creating new module with MediaID: ' . $mediaid . ' LayoutID: ' . $layoutid . ' and RegionID: ' . $regionid);

            $this->module = new $mod($db, $user, $mediaid, $layoutid, $regionid, $lkid);
        }

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
	
    /**
     * What action to perform?
     * @return
     */
    public function Exec()
    {
        // What module has been requested?
        $method	= Kit::GetParam('method', _REQUEST, _WORD);
        $raw = Kit::GetParam('raw', _REQUEST, _WORD);

        if (method_exists($this->module,$method))
        {
            $response = $this->module->$method();
        }
        else
        {
            // Set the error to display
            trigger_error(__('This Module does not exist'), E_USER_ERROR);
        }

        if ($raw == 'true')
        {
            echo $response;
        }
        else
        {
            $response->Respond();
        }
    }

	/**
	 * Returns an image stream to the browser - for the mediafile specified.
	 * @return 
	 */
	function GetImage()
	{
            $db         =& $this->db;

            $mediaID 	= Kit::GetParam('id', _GET, _INT, 0);
            $proportional = Kit::GetParam('proportional', _GET, _BOOL, true);
            $thumb = Kit::GetParam('thumb', _GET, _BOOL, false);
            $dynamic	= isset($_REQUEST['dynamic']);

            if ($mediaID == 0)
                die ('No media ID provided');

            // Get the file URI
            $SQL = sprintf("SELECT StoredAs FROM media WHERE MediaID = %d", $mediaID);

            if (!$file = $db->GetSingleValue($SQL, 'StoredAs', _STRING))
                die ('No media found for that media ID');

            //File upload directory.. get this from the settings object
            $library 	= Config::GetSetting($db, "LIBRARY_LOCATION");
            $fileName 	= $library . $file;

            // If we are a thumb request then output the cached thumbnail
            if ($thumb)
                $fileName = $library . 'tn_' . $file;

            // If the thumbnail doesnt exist then create one
            if (!file_exists($fileName))
            {
                Debug::LogEntry($db, 'audit', 'File doesnt exist, creating a thumbnail for ' . $fileName);

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

                // dynamically create an image of the correct size - used for previews
                ResizeImage($fileName, '', $width, $height, $proportional, 'browser');

                exit;
            }

            if (!$image = file_get_contents($fileName))
            {
                //not sure
                Debug::LogEntry($db, 'audit', "Cant find: $uid", 'module', 'GetImage');

                $fileName = 'img/forms/filenotfound.png';
                $image 	= file_get_contents($fileName);
            }

            $size = getimagesize($fileName);

            //Output the image header
            header("Content-type: {$size['mime']}");

            echo $image;
            exit;
	}
}
?>