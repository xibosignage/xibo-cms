<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
class powerpoint extends Module
{
    // Custom Media information
    protected $maxFileSize;
    protected $maxFileSizeBytes;

    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type= 'powerpoint';
        
        // Get the max upload size from PHP
        $this->maxFileSize 	= ini_get('upload_max_filesize');
        $this->maxFileSizeBytes = convertBytes($this->maxFileSize);

        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }
    
    /**
     * Sets the Layout and Region Information
     *  it will then fill in any blanks it has about this media if it can
     * @return
     * @param $layoutid Object
     * @param $regionid Object
     * @param $mediaid Object
     */
    public function SetRegionInformation($layoutid, $regionid) {
        $db =& $this->db;
        
        parent::SetRegionInformation($layoutid, $regionid);

        // Any Options
        $this->SetOption('uri', $this->storedAs);
        $this->existingMedia = false;

        return true;
    }

    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm()
    {
        return $this->AddFormForLibraryMedia();
    }

    public function AddMedia()
    {
        // Never called
    }

    /**
     * Return the Edit Form as HTML
     * @return
     */
    public function EditForm()
    {
        return $this->EditFormForLibraryMedia();
    }

    /**
     * Edit Media in the Database
     * @return
     */
    public function EditMedia()
    {
        return $this->EditLibraryMedia();
    }

    /**
     * Preview code for a module
     * @param int $width
     * @param int $height
     * @param int $scaleOverride The Scale Override
     * @return string The Rendered Content
     */
    public function Preview($width, $height, $scaleOverride = 0)
    {
        // PowerPoint cannot be previewed
        return '<div style="text-align:center;"><img alt="' . $this->type . ' thumbnail" src="theme/default/img/forms/' . $this->type . '.gif" /></div>';
    }
    
    public function IsValid() {
        // Client dependant
        return 2;
    }

    /**
     * Get Resource
     */
    public function GetResource($displayId = 0)
    {
        $this->ReturnFile();

        exit();

    }
}
?>