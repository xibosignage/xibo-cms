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
class image extends Module
{
    // Custom Media information
    protected $maxFileSize;
    protected $maxFileSizeBytes;

    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type= 'image';

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
    public function EditForm() {

        $this->response = new ResponseManager();

        // Provide some extra form fields
        $formFields = array();

        $formFields[] = FormManager::AddCheckbox('replaceBackgroundImages', __('Replace background images?'), 
                0, 
                __('If the current image is used as a background, should the new image replace it?'), 
                '', 'replacement-controls');

        if ($this->layoutid != '' && $this->regionid != '') {

            $formFields[] = FormManager::AddCombo(
                        'scaleTypeId', 
                        __('Scale Type'), 
                        $this->GetOption('scaleType'),
                        array(array('scaleTypeId' => 'center', 'scaleType' => __('Center')), array('scaleTypeId' => 'stretch', 'scaleType' => __('Stretch'))),
                        'scaleTypeId',
                        'scaleType',
                        __('How should this image be scaled?'), 
                        's');

            $formFields[] = FormManager::AddCombo(
                        'alignId', 
                        __('Align'), 
                        $this->GetOption('align', 'center'),
                        array(array('alignId' => 'left', 'align' => __('Left')), array('alignId' => 'center', 'align' => __('Centre')), array('alignId' => 'right', 'align' => __('Right'))),
                        'alignId',
                        'align',
                        __('How should this image be aligned?'), 
                        'a', 'align-fields');

            $formFields[] = FormManager::AddCombo(
                        'valignId', 
                        __('Vertical Align'), 
                        $this->GetOption('valign', 'middle'),
                        array(array('valignId' => 'top', 'valign' => __('Top')), array('valignId' => 'middle', 'valign' => __('Middle')), array('valignId' => 'bottom', 'valign' => __('Bottom'))),
                        'valignId',
                        'valign',
                        __('How should this image be vertically aligned?'), 
                        'v', 'align-fields');

            // Set some field dependencies
            $this->response->AddFieldAction('scaleTypeId', 'init', 'center', array('.align-fields' => array('display' => 'block')));
            $this->response->AddFieldAction('scaleTypeId', 'change', 'center', array('.align-fields' => array('display' => 'block')));
            $this->response->AddFieldAction('scaleTypeId', 'init', 'center', array('.align-fields' => array('display' => 'none')), 'not');
            $this->response->AddFieldAction('scaleTypeId', 'change', 'center', array('.align-fields' => array('display' => 'none')), 'not');
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
        if ($this->layoutid != '' && $this->regionid != '') {
            // Set the properties specific to Images
            $this->SetOption('scaleType', Kit::GetParam('scaleTypeId', _POST, _WORD, 'center'));
            $this->SetOption('align', Kit::GetParam('alignId', _POST, _WORD, 'center'));
            $this->SetOption('valign', Kit::GetParam('valignId', _POST, _WORD, 'middle'));
        }
        
        return $this->EditLibraryMedia();
    }

    public function Preview($width, $height, $scaleOverride = 0)
    {
        if ($this->previewEnabled == 0)
            return parent::Preview ($width, $height);
        
        $proportional = ($this->GetOption('scaleType') == 'stretch') ? 'false' : 'true';
        $align = $this->GetOption('align', 'center');
        $valign = $this->GetOption('valign', 'middle');
 
        $html = '<div style="display:table; width:100%%; height: %dpx">
            <div style="text-align:%s; display: table-cell; vertical-align: %s;">
                <img src="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=%d&lkid=%d&width=%d&height=%d&dynamic=true&proportional=%s" />
            </div>
        </div>';

        // Show the image - scaled to the aspect ratio of this region (get from GET)
        return sprintf($html, $height, $align, $valign, $this->mediaid, $this->lkid, $width, $height, $proportional);
    }

    public function HoverPreview()
    {
        // Default Hover window contains a thumbnail, media type and duration
        $output = parent::HoverPreview();
        $output .= '<div class="hoverPreview">';
        $output .= '    <img src="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $this->mediaid . '&width=200&height=200&dynamic=true" alt="Hover Preview">';
        $output .= '</div>';

        return $output;
    }
    
    /**
     * Get Resource
     */
    public function GetResource($displayId = 0)
    {
        $proportional = Kit::GetParam('proportional', _GET, _BOOL, true);
        $thumb = Kit::GetParam('thumb', _GET, _BOOL, false);
        $dynamic = isset($_REQUEST['dynamic']);
        $file = $this->storedAs;
        $width = intval(Kit::GetParam('width', _REQUEST, _DOUBLE, 80));
        $height = intval(Kit::GetParam('height', _REQUEST, _DOUBLE, 80));

        // File upload directory.. get this from the settings object
        $library = Config::GetSetting("LIBRARY_LOCATION");
        $fileName = $library . $file;

        Debug::Audit(sprintf('Image Request %dx%d %s. Thumb: %s', $width, $height, $fileName, $thumb));

        // If we are a thumb request then output the cached thumbnail
        if ($thumb) {
            $fileName = $library . sprintf('tn_%dx%d_%s', $width, $height, $file);

            // If the thumbnail doesn't exist then create one
            if (!file_exists($fileName)) {
                Debug::LogEntry('audit', 'File doesnt exist, creating a thumbnail for ' . $fileName);

                if (!$info = getimagesize($library . $file))
                    die($library . $file . ' is not an image');

                ResizeImage($library . $file, $fileName, $width, $height, $proportional, 'file');
            }
        }
        
        // Get the info for this new temporary file
        if (!$info = getimagesize($fileName)) {
            $fileName = 'theme/default/img/forms/filenotfound.png';
            $this->ReturnFile($fileName);
            exit;
        }

        if ($dynamic && !$thumb && $info[2])
        {
            $width = intval(Kit::GetParam('width', _REQUEST, _DOUBLE, 80));
            $height = intval(Kit::GetParam('height', _REQUEST, _DOUBLE, 80));

            // dynamically create an image of the correct size - used for previews
            ResizeImage($fileName, '', $width, $height, $proportional, 'browser');

            exit;
        }

        if (!file_exists($fileName)) {
            //not sure
            Debug::LogEntry('audit', "Cant find: $uid", 'module', 'GetResource');

            $fileName = 'theme/default/img/forms/filenotfound.png';
        }
        
    	$this->ReturnFile($fileName);
        
        exit();
    }
}
?>
