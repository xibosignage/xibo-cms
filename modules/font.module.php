<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014 Daniel Garner
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
class font extends Module
{
    // Custom Media information
    protected $maxFileSize;
    protected $maxFileSizeBytes;

    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type= 'font';
        $this->displayType = __('Font');

        // Get the max upload size from PHP
        $this->maxFileSize 	= ini_get('upload_max_filesize');
        $this->maxFileSizeBytes = convertBytes($this->maxFileSize);

        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    /**
     * Installs any files specific to this module
     */
    public function InstallFiles()
    {
        $fontsCss = 'modules/preview/fonts.css';

        if (!file_exists($fontsCss)) {
            touch($fontsCss);
        }
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
        $formFields = array();
        $formFields[] = FormManager::AddMessage(__('Renaming a font will cause existing layouts that use the font to break. Please be cautious.'));
        return $this->EditFormForLibraryMedia($formFields);
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $return = $this->AddLibraryMedia();

        return $return;
    }

    /**
     * Edit Media in the Database
     * @return
     */
    public function EditMedia()
    {
        $return = $this->EditLibraryMedia();

        // Install the font.
        $this->InstallFonts();

        return $return;
    }

    private function InstallFonts() {

        $media = new Media();

        $fontTemplate = '
@font-face {
    font-family: \'[family]\';
    src: url(\'[url]\');
}
        ';
        
        // Save a fonts.css file to the library for use as a module
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT mediaID, name, storedAs FROM `media` WHERE type = :type AND IsEdited = 0 ORDER BY name');
            $sth->execute(array(
                    'type' => 'font'
                ));
            
            $fonts = $sth->fetchAll();

            if (count($fonts) < 1)
                return;

            $css = '';
            $localCss = '';
            $ckeditorString = '';
            foreach ($fonts as $font) {

                // Css for the client contains the actual stored as location of the font.
                $css .= str_replace('[url]', $font['storedAs'], str_replace('[family]', $font['name'], $fontTemplate));

                // Css for the local CMS contains the full download path to the font
                $url = Kit::GetXiboRoot() . '?p=module&mod=font&q=Exec&method=GetResource&download=1&downloadFromLibrary=1&mediaid=' . $font['mediaID'];
                $localCss .= str_replace('[url]', $url, str_replace('[family]', $font['name'], $fontTemplate));

                // CKEditor string
                $ckeditorString .= $font['name'] . '/' . $font['name'] . ';';
            }

            file_put_contents('modules/preview/fonts.css', $css);

            // Install it (doesn't expire, is a system file, force update)
            $media->addModuleFile('modules/preview/fonts.css', 0, true, true);
            
            // Generate a fonts.css file for use locally (in the CMS)
            file_put_contents('modules/preview/fonts.css', $localCss);

            // Edit the CKEditor file
            $ckeditor = file_get_contents('theme/default/libraries/ckeditor/config.js');
            $replace = "/*REPLACE*/ config.font_names = '" . $ckeditorString . "' + config.font_names; /*ENDREPLACE*/";

            $ckeditor = preg_replace('/\/\*REPLACE\*\/.*?\/\*ENDREPLACE\*\//', $replace, $ckeditor);

            file_put_contents('theme/default/libraries/ckeditor/config.js', $ckeditor);
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
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
