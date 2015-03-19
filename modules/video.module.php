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
    /**
     * Return the Edit Form as HTML
     */
    public function EditForm()
    {
        $response = new ResponseManager();
        $formFields = array();
        $formFields[] = FormManager::AddCheckbox('loop', __('Loop?'),
            $this->GetOption('loop', 0), __('Should the video loop if it finishes before the provided duration?'),
            'l', 'loop-fields');

        $formFields[] = FormManager::AddCheckbox('mute', __('Mute?'),
            $this->GetOption('mute', 1), __('Should the video be muted?'),
            'm', 'mute-fields');

        $response->AddFieldAction('duration', 'init', '0', array('.loop-fields' => array('display' => 'none')));
        $response->AddFieldAction('duration', 'change', '0', array('.loop-fields' => array('display' => 'none')));
        $response->AddFieldAction('duration', 'init', '0', array('.loop-fields' => array('display' => 'block')), 'not');
        $response->AddFieldAction('duration', 'change', '0', array('.loop-fields' => array('display' => 'block')), 'not');

        // Standard Edit Form
        $this->baseEditForm($formFields, $response);
    }

    /**
     * Edit Media in the Database
     */
    public function EditMedia()
    {
        // Set the properties specific to this module
        $this->SetOption('loop', Kit::GetParam('loop', _POST, _CHECKBOX));
        $this->SetOption('mute', Kit::GetParam('mute', _POST, _CHECKBOX));

        parent::EditMedia();
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
        return $this->previewIcon();
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function GetResource($displayId = 0)
    {
        $this->ReturnFile();
        exit();
    }

    /**
     * Is this module valid
     * @return int
     */
    public function IsValid()
    {
        // Yes
        return 1;
    }
}
