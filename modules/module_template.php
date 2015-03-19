<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
 *
 *
 *
 * This is a template module used to demonstrate how a module for Xibo can be made.
 *
 * The class name must be equal to the $this->type and the file name must be equal to modules/type.module.php
 */ 
class moduletemplate extends Module
{
    /**
     * Install or Update this module
     */
    public function InstallOrUpdate()
    {
        // This function should update the `module` table with information about your module.
        // The current version of the module in the database can be obtained in $this->schemaVersion
        // The current version of this code can be obtained in $this->codeSchemaVersion
        
        // $settings will be made available to all instances of your module in $this->settings. These are global settings to your module, 
        // not instance specific (i.e. not settings specific to the layout you are adding the module to).
        // $settings will be collected from the Administration -> Modules CMS page.
        // 
        // Layout specific settings should be managed with $this->SetOption in your add / edit forms.
        
        if ($this->schemaVersion <= 1) {
            // Install
            // Call "$this->InstallModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings)"
        }
        else {
            // Update
            // Call "$this->UpdateModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings)" with the updated items
        }

        // Check we are all installed
        $this->InstallFiles();

        // After calling either Install or Update your code schema version will match the database schema version and this method will not be called
        // again. This means that if you want to change those fields in an update to your module, you will need to increment your codeSchemaVersion.
    }

    /**
     * Form for updating the module settings
     */
    public function ModuleSettingsForm()
    {
        // Output any form fields (formatted via a Theme file)
        // These are appended to the bottom of the "Edit" form in Module Administration
        return array();
    }

    /**
     * Process any module settings
     */
    public function ModuleSettings()
    {
        // Process any module settings you asked for.
        
        // Return an array of the processed settings.
        return array();
    }
    
    /**
     * Return the Add Form
     */
    public function AddForm()
    {
        $response = new ResponseManager();

        // You also have access to $settings, which is the array of settings you configured for your module.
        
        // The CMS provides access to the region that holds this widget (if it is being edited in the context of a region)
        // This can be found in $this->region;

        // All forms should set some meta data about the form - the parameter is the function the form save action should call
        $this->configureForm('AddMedia');

        // Any values for the form fields should be added to the theme here.

        // Modules should be rendered using the theme engine.
        $response->html = Theme::RenderReturn('form_render');

        // Any JavaScript call backs should be set (you can use text_callback to set up a text editor should you need one)
        $response->callBack = 'text_callback';

        $response->dialogTitle = __('Add Text');
        
        // You can have a bigger form
        //$response->dialogClass = 'modal-big';

        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        $this->configureFormButtons($response);

        // The response must be returned.
        return $response;
    }

    /**
     * Add Media to the Database
     */
    public function AddMedia()
    {
        $response = new ResponseManager();

        // You must provide a duration (all media items must provide this field)
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration(), false));

        // You should validate all form input using the Kit::GetParam helper classes
        //Kit::GetParam('duration', _POST, _INT, 0, false);
        
        // You should also validate that fields are set to your liking
        // throw a new InvalidArgumentException if there is something amiss

        // You can store any additional options for your module using the SetOption method
        //$this->SetOption('direction', $direction);

        // You may also store raw XML/HTML using SetRaw. You should provide a containing node (in this example: <text>)
        //$this->setRawNode($name, $value);

        // Save the widget
        $this->saveWidget();

        // Load form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }

    /**
     * Return the Edit Form as HTML
     */
    public function EditForm()
    {
        // Edit forms are the same as add forms, except you will have the $this->mediaid member variable available for use.
        $response = new ResponseManager();

        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Configure the form
        $this->configureForm('EditMedia');

        // Modules should be rendered using the theme engine.
        $response->html = Theme::RenderReturn('form_render');

        $this->configureFormButtons($response);

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

        // Save the widget
        $this->saveWidget();

        // Load an edit form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }

    /**
     * GetResource
     * Return the rendered resource to be used by the client (or a preview) for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return mixed
     */
    public function GetResource($displayId = 0)
    {
        // Behave exactly like the client.

        // A template is provided which contains a number of different libraries that might
        // be useful (jQuery, etc).
        // You can provide your own template, or just output the HTML directly in this method. It is up to you.
        //$template = file_get_contents('modules/preview/HtmlTemplateSimple.html');
        $template = file_get_contents('modules/preview/HtmlTemplate.html');

        // If we are coming from a CMS preview or the Layout Designer we will have some additional variables passed in
        // These will not be passed in from the client.
        $width = Kit::GetParam('width', _REQUEST, _DOUBLE);
        $height = Kit::GetParam('height', _REQUEST, _DOUBLE);

        // Get any options you require from the XLF
        $myvariable = $this->GetOption('myvariable');
        
        // The duration is always available
        $duration = $this->getDuration();

        // Do whatever it is you need to do to render your content.
        // Return that content.
        return $template;
    }
    
    public function IsValid() {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
    }
}
?>
