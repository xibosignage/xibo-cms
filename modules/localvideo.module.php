<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-15 Daniel Garner
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
class localvideo extends Module
{
    /**
     * Return the Add Form as HTML
     */
    public function AddForm()
    {
        $response = new ResponseManager();

        // Configure form
        $this->configureForm('AddMedia');

        $formFields = array();
        
        $formFields[] = FormManager::AddText('uri', __('Video Path'), NULL, 
            __('A local file path or URL to the video. This can be a RTSP stream.'), 'p', 'required');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this counter should be displayed'), 'd', 'required');

        Theme::Set('form_fields', $formFields);

        $response->html = Theme::RenderReturn('form_render');
        $this->configureFormButtons($response);
        $response->dialogTitle = __('Add Local Video');

        return $response;
    }
	
    /**
     * Return the Edit Form as HTML
     */
    public function EditForm()
    {
        $response = new ResponseManager();

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Configure the form
        $this->configureForm('EditMedia');

        $formFields = array();
        
        $formFields[] = FormManager::AddText('uri', __('Video Path'), urldecode($this->GetOption('uri')), 
            __('A local file path or URL to the video. This can be a RTSP stream.'), 'p', 'required');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->getDuration(),
            __('The duration in seconds this counter should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        Theme::Set('form_fields', $formFields);

        $response->html = Theme::RenderReturn('form_render');
        $this->configureFormButtons($response);
        $response->dialogTitle = __('Edit Local Video');

        return $response;
    }
	
    /**
     * Add Media to the Database
     */
    public function AddMedia()
    {
        $response = new ResponseManager();

        // Properties
        $uri = Kit::GetParam('uri', _POST, _URI);
        $duration = Kit::GetParam('duration', _POST, _INT, 0);

        // Validate
        if ($uri == "")
            throw new InvalidArgumentException(__('Please enter a full path name giving the location of this video on the client'));

        if ($duration < 0)
            throw new InvalidArgumentException(__('You must enter a duration.'));

        // Any Options
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration()));
        $this->SetOption('uri', $uri);

        // Save the widget
        $this->saveWidget();

        // Load an edit form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }
	
    /**
     * Edit Media in the Database
     */
    public function EditMedia()
    {
        $response = new ResponseManager();

        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Other properties
        $uri = Kit::GetParam('uri', _POST, _URI);

        // Validate
        if ($uri == "")
            throw new InvalidArgumentException(__('Please enter a full path name giving the location of this video on the client'));

        // Any Options
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration()));
        $this->SetOption('uri', $uri);

        // Save the widget
        $this->saveWidget();

        // Load an edit form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }
    
    public function IsValid()
    {
        // Client dependant
        return 2;
    }
}
