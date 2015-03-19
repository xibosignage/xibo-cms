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
class counter extends Module
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
        $formFields[] = FormManager::AddMessage(__('Ubuntu Client Only'));

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this counter should be displayed'), 'd', 'required');

        $formFields[] = FormManager::AddCheckbox('popupNotification', __('Pop-up Notification?'), 
            NULL, __('Popup a notification when the counter changes?'), 
            'n');

        $formFields[] = FormManager::AddMultiText('ta_text', NULL, NULL, 
            __('Enter a format that should be applied to the counter when it is show.'), 't', 10);

        Theme::Set('form_fields', $formFields);

        $this->configureFormButtons($response);
        $response->html = Theme::RenderReturn('form_render');
        $response->callBack = 'text_callback';
        $response->dialogTitle = __('Add Counter');
        $response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $response;
    }

    /**
     * Return the Edit Form as HTML
     */
    public function EditForm()
    {
        $response = new ResponseManager();

        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Configure the form
        $this->configureForm('EditMedia');

        // Build the Form
        $formFields = array();
        $formFields[] = FormManager::AddMessage(__('Ubuntu Client Only'));

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->getDuration(),
            __('The duration in seconds this counter should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        $formFields[] = FormManager::AddCheckbox('popupNotification', __('Pop-up Notification?'),
            $this->GetOption('popupNotification'), __('Popup a notification when the counter changes?'),
            'n');

        $formFields[] = FormManager::AddMultiText('ta_text', NULL, $this->getRawNode('template', null),
            __('Enter a format that should be applied to the counter when it is show.'), 't', 10);

        Theme::Set('form_fields', $formFields);

        $this->configureFormButtons($response);
        $response->html = Theme::RenderReturn('form_render');
        $response->callBack = 'text_callback';
        $response->dialogTitle = __('Edit Counter');
        $response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $response;
    }

    /**
     * Add Media to the Database
     */
    public function AddMedia()
    {
        $response = new ResponseManager();

        // Properties
        $duration = Kit::GetParam('duration', _POST, _INT, 0, false);
        $text = Kit::GetParam('ta_text', _POST, _HTMLSTRING);

        // Validation
        if ($text == '')
            throw new InvalidArgumentException(__('Please enter a template'));

        if ($duration == 0)
            throw new InvalidArgumentException(__('Pleased enter a duration'));

        // Any Options
        $this->setDuration($duration);
        $this->SetOption('popupNotification', Kit::GetParam('popupNotification', _POST, _CHECKBOX));
        $this->setRawNode('template', $text);

        // Save the widget
        $this->saveWidget();

        // Load form
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
        $text = Kit::GetParam('ta_text', _POST, _HTMLSTRING);

        if ($text == '')
            throw new InvalidArgumentException(__('Please enter a template'));

        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions) {
            $duration = Kit::GetParam('duration', _POST, _INT, 0);
            if ($duration == 0)
                throw new InvalidArgumentException(__('Pleased enter a duration'));
        }

        // Any Options
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration(), false));
        $this->SetOption('popupNotification', Kit::GetParam('popupNotification', _POST, _CHECKBOX));
        $this->setRawNode('template', $text);

        // Save the widget
        $this->saveWidget();

        // Load form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
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
        if ($this->module->previewEnabled == 0)
            return parent::Preview($width, $height);

        $text = $this->getRawNode('template', null);

        // Show the contents of text accordingly
        $return = <<<END
        <div style="position:relative; overflow:hidden ;width:{$width}px; height:{$height}px; font-size: 1em;">
            <div style="position:absolute; left: 0px; top: 0px;">
                <div class="article">
                        $text
                </div>
            </div>
        </div>
END;
        return $return;
    }
    
    public function IsValid() {
        // Client dependant
        return 2;
    }
}
