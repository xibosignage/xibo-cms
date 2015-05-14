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
 *
 */ 
class clock extends Module
{
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '') {
        // The Module Type must be set - this should be a unique text string of no more than 50 characters.
        // It is used to uniquely identify the module globally.
        $this->type = 'clock';

        // This is the code schema version, it should be 1 for a new module and should be incremented each time the 
        // module data structure changes.
        // It is used to install / update your module and to put updated modules down to the display clients.
        $this->codeSchemaVersion = 1;
        
        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    public function InstallFiles() {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->addModuleFile('modules/preview/vendor/jquery-cycle-2.1.6.min.js');
        $media->addModuleFile('modules/preview/vendor/moment.js');
        $media->addModuleFile('modules/preview/vendor/flipclock.min.js');
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');
    }

    /**
     * Install or Update this module
     */
    public function InstallOrUpdate() {
        // This function should update the `module` table with information about your module.
        // The current version of the module in the database can be obtained in $this->schemaVersion
        // The current version of this code can be obtained in $this->codeSchemaVersion
        
        // $settings will be made available to all instances of your module in $this->settings. These are global settings to your module, 
        // not instance specific (i.e. not settings specific to the layout you are adding the module to).
        // $settings will be collected from the Administration -> Modules CMS page.
        // 
        // Layout specific settings should be managed with $this->SetOption in your add / edit forms.
        Debug::LogEntry('audit', 'Request to install or update with schemaversion: ' . $this->schemaVersion, 'clock', 'InstallOrUpdate');
        
        if ($this->schemaVersion <= 1) {
            // Install
            Debug::LogEntry('audit', 'Installing Clock module', 'clock', 'InstallOrUpdate');

            $this->InstallModule('Clock', 'Display a Clock', 'forms/library.gif', 1, 1, array());
        }
        else {
            // Update
            // No updates required to this module.
            // Call "$this->UpdateModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings)" with the updated items
        }

        // Check we are all installed
        $this->InstallFiles();
    }

    /**
     * Form for updating the module settings
     */
    public function ModuleSettingsForm() {
        // Output any form fields (formatted via a Theme file)
        // These are appended to the bottom of the "Edit" form in Module Administration
        return array();
    }

    /**
     * Process any module settings
     */
    public function ModuleSettings() {
        // Process any module settings you asked for.
        
        // Return an array of the processed settings.
        return array();
    }

    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm()
    {
        $this->response = new ResponseManager();

        // This is the logged in user and can be used to assess permissions
        $user =& $this->user;

        // The CMS provides the region width and height in case they are needed
        $rWidth = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight = Kit::GetParam('rHeight', _REQUEST, _STRING);

        // All forms should set some meta data about the form.
        // Usually, you would want this meta data to remain the same.
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $this->regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
    
        $formFields = array();

        // Offer a choice of clock type
        $formFields[] = FormManager::AddCombo(
                    'clockTypeId', 
                    __('Clock Type'), 
                    NULL,
                    array(
                        array('clockTypeId' => '1', 'clockType' => 'Analogue'),
                        array('clockTypeId' => '2', 'clockType' => 'Digital'),
                        array('clockTypeId' => '3', 'clockType' => 'Flip Clock')
                    ),
                    'clockTypeId',
                    'clockType',
                    __('Please select the type of clock to display.'), 
                    'c');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

        $formFields[] = FormManager::AddNumber('offset', __('Offset'), NULL, 
            __('The offset in minutes that should be applied to the current time.'), 'o', NULL, 'offset-control-group');

        // Offer a choice of theme
        $formFields[] = FormManager::AddCombo(
                    'themeid', 
                    __('Theme'), 
                    NULL,
                    array(array('themeid' => '1', 'theme' => 'Light'), array('themeid' => '2', 'theme' => 'Dark')),
                    'themeid',
                    'theme',
                    __('Please select a theme for the clock.'), 
                    't',
                    'analogue-control-group');

        $formFields[] = FormManager::AddMessage(sprintf(__('Enter a format for the Digital Clock below. e.g. [HH:mm] or [DD/MM/YYYY]. See the <a href="%s" target="_blank">format guide</a> for more information.'), HelpManager::Link('Widget', 'ClockFormat')), 'digital-control-group');
        
        $formFields[] = FormManager::AddMultiText('ta_text', NULL, '[HH:mm]', 
            __('Enter a format for the clock'), 'f', 10, '', 'digital-control-group');

        Theme::Set('form_fields', $formFields);

        // Dependencies (some fields should be shown / hidden)
        $this->SetFieldDependencies();

        // Modules should be rendered using the theme engine.
        $this->response->html = Theme::RenderReturn('form_render');

        $this->response->dialogTitle = __('Add Clock');
        $this->response->callBack = 'text_callback';
        
        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        // The response must be returned.
        return $this->response;
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $this->response = new ResponseManager();

        // Same member variables as the Form call, except with POST variables for your form fields.
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;

        // You are required to set a media id, which should be unique.
        $this->mediaid  = md5(uniqid());

        // You must also provide a duration (all media items must provide this field)
        $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);
        $this->SetOption('theme', Kit::GetParam('themeid', _POST, _INT, 0));
        $this->SetOption('clockTypeId', Kit::GetParam('clockTypeId', _POST, _INT, 1));
        $this->SetOption('offset', Kit::GetParam('offset', _POST, _INT, 0));
        $this->SetRaw('<format><![CDATA[' . Kit::GetParam('ta_text', _POST, _HTMLSTRING) . ']]></format>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Usually you will want to load the region options form again once you have added your module.
        // In some cases you will want to load the edit form for that module
        $this->response->loadForm = true;
        $this->response->loadFormUri = "index.php?p=timeline&layoutid=$this->layoutid&regionid=$this->regionid&q=RegionOptions";
        
        return $this->response;
    }

    /**
     * Return the Edit Form as HTML
     * @return
     */
    public function EditForm()
    {
        $this->response = new ResponseManager();

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // All forms should set some meta data about the form.
        // Usually, you would want this meta data to remain the same.
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $this->regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $this->mediaid . '">');

        // Extract the format from the raw node in the XLF
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());
        $formatNodes = $rawXml->getElementsByTagName('format');
        $formatNode = $formatNodes->item(0);

        $formFields = array();

        // Offer a choice of clock type
        $formFields[] = FormManager::AddCombo(
                    'clockTypeId', 
                    __('Clock Type'), 
                    $this->GetOption('clockTypeId'),
                    array(
                        array('clockTypeId' => '1', 'clockType' => 'Analogue'),
                        array('clockTypeId' => '2', 'clockType' => 'Digital'),
                        array('clockTypeId' => '3', 'clockType' => 'Flip Clock')
                    ),
                    'clockTypeId',
                    'clockType',
                    __('Please select the type of clock to display.'), 
                    'c');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this item should be displayed'), 'd', 'required');


        $formFields[] = FormManager::AddNumber('offset', __('Offset'), $this->GetOption('offset'), 
            __('The offset in minutes that should be applied to the current time.'), 'o', NULL, 'offset-control-group');

        // Offer a choice of theme
        $formFields[] = FormManager::AddCombo(
                    'themeid', 
                    __('Theme'), 
                    $this->GetOption('theme'),
                    array(array('themeid' => '1', 'theme' => 'Light'), array('themeid' => '2', 'theme' => 'Dark')),
                    'themeid',
                    'theme',
                    __('Please select a theme for the clock.'), 
                    't',
                    'analogue-control-group');

        $formFields[] = FormManager::AddMessage(sprintf(__('Enter a format for the Digital Clock below. e.g. [HH:mm] or [DD/MM/YYYY]. See the <a href="%s" target="_blank">format guide</a> for more information.'), HelpManager::Link('Widget', 'ClockFormat')), 'digital-control-group');
        
        $formFields[] = FormManager::AddMultiText('ta_text', NULL, (($formatNode != NULL) ? $formatNode->nodeValue : ''), 
            __('Enter a format for the clock'), 'f', 10, '', 'digital-control-group');

        Theme::Set('form_fields', $formFields);

        // Dependencies (some fields should be shown / hidden)
        $this->SetFieldDependencies();

        // Modules should be rendered using the theme engine.
        $this->response->html = Theme::RenderReturn('form_render');

        $this->response->dialogTitle = __('Edit Clock');
        $this->response->callBack = 'text_callback';
        
        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        if ($this->showRegionOptions) {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
        }
        else {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->AddButton(__('Apply'), 'XiboDialogApply("#ModuleForm")');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        // The response must be returned.
        return $this->response;
    }

    /**
     * Edit Media in the Database
     * @return
     */
    public function EditMedia()
    {
        $this->response = new ResponseManager();
        
        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // You must also provide a duration (all media items must provide this field)
        $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);
        $this->SetOption('theme', Kit::GetParam('themeid', _POST, _INT, 0));
        $this->SetOption('clockTypeId', Kit::GetParam('clockTypeId', _POST, _INT, 1));
        $this->SetOption('offset', Kit::GetParam('offset', _POST, _INT, 0));
        $this->SetRaw('<format><![CDATA[' . Kit::GetParam('ta_text', _POST, _HTMLSTRING) . ']]></format>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Usually you will want to load the region options form again once you have added your module.
        // In some cases you will want to load the edit form for that module
        if ($this->showRegionOptions) {
            $this->response->callBack = 'refreshPreview("' . $this->regionid . '")';
            $this->response->loadForm = true;
            $this->response->loadFormUri = "index.php?p=timeline&layoutid=$this->layoutid&regionid=$this->regionid&q=RegionOptions";
        }
        
        return $this->response;
    }

    private function SetFieldDependencies() {

        $clockTypeId_1 = array(
                '.analogue-control-group' => array('display' => 'block'),
                '.digital-control-group' => array('display' => 'none'),
                '.flip-control-group' => array('display' => 'none'),
                '.offset-control-group' => array('display' => 'block')
            );

        $clockTypeId_2 = array(
                '.analogue-control-group' => array('display' => 'none'),
                '.digital-control-group' => array('display' => 'block'),
                '.flip-control-group' => array('display' => 'none'),
                '.offset-control-group' => array('display' => 'block')
            );

        $clockTypeId_3 = array(
                '.analogue-control-group' => array('display' => 'none'),
                '.digital-control-group' => array('display' => 'none'),
                '.flip-control-group' => array('display' => 'block'),
                '.offset-control-group' => array('display' => 'none')
            );
            
        $this->response->AddFieldAction('clockTypeId', 'init', 1, $clockTypeId_1);
        $this->response->AddFieldAction('clockTypeId', 'change', 1, $clockTypeId_1);
        $this->response->AddFieldAction('clockTypeId', 'init', 2, $clockTypeId_2);
        $this->response->AddFieldAction('clockTypeId', 'change', 2, $clockTypeId_2);
        $this->response->AddFieldAction('clockTypeId', 'init', 3, $clockTypeId_3);
        $this->response->AddFieldAction('clockTypeId', 'change', 3, $clockTypeId_3);
    }

    /**
     * GetResource
     *     Return the rendered resource to be used by the client (or a preview)
     *     for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     */
    public function GetResource($displayId = 0)
    {
        // Clock Type
        switch ($this->GetOption('clockTypeId', 1)) {

            case 1:
                // Analogue
                $template = file_get_contents('modules/theme/HtmlTemplateForClock.html');
                
                // Render our clock face
                $theme = ($this->GetOption('theme') == 1 ? 'light' : 'dark');
                $theme_face = ($this->GetOption('theme') == 1 ? 'clock_bg_modern_light.png' : 'clock_bg_modern_dark.png');
                 
                $template = str_replace('<!--[[[CLOCK_FACE]]]-->', base64_encode(file_get_contents('modules/theme/' . $theme_face)), $template);
                
                // Light or dark?
                $template = str_replace('<!--[[[CLOCK_THEME]]]-->', $theme, $template);
                $template = str_replace('<!--[[[OFFSET]]]-->', $this->GetOption('offset', 0), $template);

                // After body content
                $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
                $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
                $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'moment.js"></script>';
                
                // Replace the After body Content
                $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);
                break;

            case 2:
                // Digital
                // Digital clock is essentially a cut down text module which always fits to the region
                $template = file_get_contents('modules/preview/HtmlTemplate.html');

                // Extract the format from the raw node in the XLF
                $rawXml = new DOMDocument();
                $rawXml->loadXML($this->GetRaw());
                $formatNodes = $rawXml->getElementsByTagName('format');
                $formatNode = $formatNodes->item(0);
                $format = $formatNode->nodeValue;

                // Strip out the bit between the [] brackets and use that as the format mask for moment.
                $matches = '';
                preg_match_all('/\[.*?\]/', $format, $matches);

                foreach($matches[0] as $subs) {
                    $format = str_replace($subs, '<span class="clock" format="' . str_replace('[', '', str_replace(']', '', $subs)) . '"></span>', $format);
                }

                // Replace all the subs
                $template = str_replace('<!--[[[BODYCONTENT]]]-->', $format, $template);

                // After body content
                $options = array(
                        'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
                        'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
                        'originalWidth' => $this->width,
                        'originalHeight' => $this->height,
                        'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
                    );

                $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
                $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
                $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'moment.js"></script>';
                $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
                $javaScriptContent .= '<script type="text/javascript">
                    var locale = "' . TranslationEngine::GetJsLocale() . '";
                    var options = ' . json_encode($options) . ';

                    function updateClock() {
                        $(".clock").each(function() {
                            $(this).html(moment().add(' . $this->GetOption('offset', 0) . ', "m").format($(this).attr("format")));
                        });
                    }

                    $(document).ready(function() {
                        moment.locale(locale);
                        updateClock();
                        setInterval(updateClock, 1000);
                        $("body").xiboLayoutScaler(options);
                    });
                </script>';

                // Replace the After body Content
                $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

                // Add our fonts.css file
                $headContent = '<link href="' . (($isPreview) ? 'modules/preview/' : '') . 'fonts.css" rel="stylesheet" media="screen">';
                $headContent .= '<style type="text/css">' . file_get_contents(Theme::ItemPath('css/client.css')) . '</style>';

                $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

                break;

            case 3:
                // Flip Clock
                $template = file_get_contents('modules/theme/HtmlTemplateForFlipClock.html');

                // Head Content (CSS for flip clock)
                $template = str_replace('<!--[[[HEADCONTENT]]]-->', '<style type="text/css">' . file_get_contents('modules/preview/vendor/flipclock.css') . '</style>', $template);
                $template = str_replace('<!--[[[OFFSET]]]-->', $this->GetOption('offset', 0), $template);

                // After body content
                $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
                $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
                $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'flipclock.min.js"></script>';

                // Replace the After body Content
                $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

                break;
        }

        // If we are a preview, then pass in the width and height
        $template = str_replace('<!--[[[PREVIEW_WIDTH]]]-->', Kit::GetParam('width', _GET, _DOUBLE, 0), $template);
        $template = str_replace('<!--[[[PREVIEW_HEIGHT]]]-->', Kit::GetParam('height', _GET, _DOUBLE, 0), $template);

        // Replace the View Port Width?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->width, $template);

        // Return that content.
        return $template;
    }

    public function HoverPreview() {
        // Default Hover window contains a thumbnail, media type and duration
        $output = parent::HoverPreview();

        return $output;
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
