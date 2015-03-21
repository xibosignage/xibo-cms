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
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Theme;

class shellcommand extends Module
{
	/**
     * Return the Add Form as HTML
     */
    public function AddForm()
    {
        $response = new ApplicationState();

        // Configure form
        $this->configureForm('AddMedia');
        
        $formFields = array();
        
        $formFields[] = FormManager::AddText('windowsCommand', __('Windows Command'), NULL, 
            __('Enter a Windows Command Line compatible command'), 'w');
        
        $formFields[] = FormManager::AddText('linuxCommand', __('Android / Linux Command'), NULL, 
            __('Enter an Android / Linux Command Line compatible command'), 'l');

        Theme::Set('form_fields', $formFields);

        $response->html = Theme::RenderReturn('form_render');
        $this->configureFormButtons($response);
        $response->dialogTitle = __('Add Shell Command');

        return $response;
    }
	
    /**
     * Return the Edit Form as HTML
     */
    public function EditForm()
    {
        $response = new ApplicationState();

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Configure the form
        $this->configureForm('EditMedia');

        $formFields = array();
        
        $formFields[] = FormManager::AddText('windowsCommand', __('Windows Command'), htmlentities(urldecode($this->GetOption('windowsCommand'))), 
            __('Enter a Windows Command Line compatible command'), 'w');
        
        $formFields[] = FormManager::AddText('linuxCommand', __('Android / Linux Command'), htmlentities(urldecode($this->GetOption('linuxCommand'))), 
            __('Enter an Android / Linux Command Line compatible command'), 'l');

        Theme::Set('form_fields', $formFields);
        
        $response->html = Theme::RenderReturn('form_render');
        $this->configureFormButtons($response);
        $response->dialogTitle = __('Edit Shell Command');

        return $response;
    }
	
    /**
     * Add Media to the Database
     */
    public function AddMedia()
    {
        $response = new ApplicationState();

        $windowsCommand = \Kit::GetParam('windowsCommand', _POST, _STRING);
        $linuxCommand = \Kit::GetParam('linuxCommand', _POST, _STRING);

        if ($windowsCommand == '' && $linuxCommand == '')
            throw new InvalidArgumentException(__('You must enter a command'));

        // Any Options (we need to encode shell commands, as they sit on the options rather than the raw
        $this->setDuration(1);
        $this->SetOption('windowsCommand', urlencode($windowsCommand));
        $this->SetOption('linuxCommand', urlencode($linuxCommand));

        // Save the widget
        $this->saveWidget();

        // Load form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }

    /**
     * Edit Media in the Database
     * @return
     */
    public function EditMedia()
    {
        $response = new ApplicationState();

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Configure the form
        $this->configureForm('EditMedia');

        $windowsCommand = \Kit::GetParam('windowsCommand', _POST, _STRING);
        $linuxCommand = \Kit::GetParam('linuxCommand', _POST, _STRING);

        if ($windowsCommand == '' && $linuxCommand == '')
            throw new InvalidArgumentException(__('You must enter a command'));

        // Any Options
        $this->setDuration(1);
        $this->SetOption('windowsCommand', urlencode($windowsCommand));
        $this->SetOption('linuxCommand', urlencode($linuxCommand));

        // Save the widget
        $this->saveWidget();

        // Load an edit form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }

    public function Preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0)
            return parent::Preview ($width, $height);
        
        $msgWindows = __('Windows Command');
        $msgLinux = __('Linux Command');

        $preview = '';
        $preview .= '<p>' . $msgWindows . ': ' . urldecode($this->GetOption('windowsCommand')) . '</p>';
        $preview .= '<p>' . $msgLinux . ': ' . urldecode($this->GetOption('linuxCommand')) . '</p>';

        return $preview;
    }

    public function HoverPreview()
    {
        return $this->Preview(0,0);
    }

    public function IsValid()
    {
        // Client dependant
        return 2;
    }
}
?>