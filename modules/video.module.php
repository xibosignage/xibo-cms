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
class video extends Module
{
    // Custom Media information
    protected $maxFileSize;
    protected $maxFileSizeBytes;

    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type= 'video';

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

    /**
     * Return the Edit Form as HTML
     * @return
     */
    public function EditForm()
    {
        $this->response = new ResponseManager();
        $formFields = array();

        if ($this->layoutid != '' && $this->regionid != '') {
            $formFields[] = FormManager::AddCheckbox('loop', __('Loop?'), 
                $this->GetOption('loop', 0), __('Should the video loop if it finishes before the provided duration?'), 
                'l', 'loop-fields');

            $formFields[] = FormManager::AddCheckbox('mute', __('Mute?'), 
                $this->GetOption('mute', 1), __('Should the video be muted?'), 
                'm', 'mute-fields');

            $this->response->AddFieldAction('duration', 'init', '0', array('.loop-fields' => array('display' => 'none')));
            $this->response->AddFieldAction('duration', 'change', '0', array('.loop-fields' => array('display' => 'none')));
            $this->response->AddFieldAction('duration', 'init', '0', array('.loop-fields' => array('display' => 'block')), 'not');
            $this->response->AddFieldAction('duration', 'change', '0', array('.loop-fields' => array('display' => 'block')), 'not');
        }

        return $this->EditFormForLibraryMedia($formFields);
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        return $this->AddLibraryMedia();
    }

    /**
     * Edit Media in the Database
     * @return
     */
    public function EditMedia()
    {
        // Set the properties specific to Images
        $this->SetOption('loop', Kit::GetParam('loop', _POST, _CHECKBOX));
        $this->SetOption('mute', Kit::GetParam('mute', _POST, _CHECKBOX));

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
        // Videos are never previewed in the browser.
        return '<div style="text-align:center;"><img alt="' . $this->type . ' thumbnail" src="theme/default/img/forms/' . $this->type . '.gif" /></div>';
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
