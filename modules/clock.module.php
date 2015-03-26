<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014-15 Daniel Garner
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
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Theme;

class clock extends Module
{
    public $codeSchemaVersion = 1;

    public function InstallFiles()
    {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->addModuleFile('modules/preview/vendor/jquery-cycle-2.1.6.min.js');
        $media->addModuleFile('modules/preview/vendor/moment.js');
        $media->addModuleFile('modules/preview/vendor/flipclock.min.js');
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');
    }

    /**
     * Return the Add Form as HTML
     */
    public function AddForm()
    {
        $response = $this->getState();

        // Configure form
        $this->configureForm('AddMedia');
    
        $formFields = array();

        // Offer a choice of clock type
        $formFields[] = Form::AddCombo(
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

        $formFields[] = Form::AddNumber('duration', __('Duration'), NULL,
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

        $formFields[] = Form::AddNumber('offset', __('Offset'), NULL,
            __('The offset in minutes that should be applied to the current time.'), 'o', NULL, 'offset-control-group');

        // Offer a choice of theme
        $formFields[] = Form::AddCombo(
                    'themeid', 
                    __('Theme'), 
                    NULL,
                    array(array('themeid' => '1', 'theme' => 'Light'), array('themeid' => '2', 'theme' => 'Dark')),
                    'themeid',
                    'theme',
                    __('Please select a theme for the clock.'), 
                    't',
                    'analogue-control-group');

        $formFields[] = Form::AddMessage(sprintf(__('Enter a format for the Digital Clock below. e.g. [HH:mm] or [DD/MM/YYYY]. See the <a href="%s" target="_blank">format guide</a> for more information.'), Help::Link('Widget', 'ClockFormat')), 'digital-control-group');
        
        $formFields[] = Form::AddMultiText('ta_text', NULL, '[HH:mm]',
            __('Enter a format for the clock'), 'f', 10, '', 'digital-control-group');

        Theme::Set('form_fields', $formFields);

        // Dependencies (some fields should be shown / hidden)
        $this->SetFieldDependencies($response);

        // Modules should be rendered using the theme engine.
        $response->html = Theme::RenderReturn('form_render');

        $this->configureFormButtons($response);
        $response->dialogTitle = __('Add Clock');
        $response->callBack = 'text_callback';

        // The response must be returned.
        return $response;
    }

    /**
     * Add Media to the Database
     */
    public function AddMedia()
    {
        $response = $this->getState();

        // You must also provide a duration (all media items must provide this field)
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration(), false));
        $this->SetOption('theme', \Kit::GetParam('themeid', _POST, _INT, 0));
        $this->SetOption('clockTypeId', \Kit::GetParam('clockTypeId', _POST, _INT, 1));
        $this->SetOption('offset', \Kit::GetParam('offset', _POST, _INT, 0));
        $this->setRawNode('ta_text', \Kit::GetParam('ta_text', _POST, _HTMLSTRING));

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
        $response = $this->getState();

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Configure the form
        $this->configureForm('EditMedia');

        // Build the form
        $formFields = array();

        // Offer a choice of clock type
        $formFields[] = Form::AddCombo(
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

        $formFields[] = Form::AddNumber('duration', __('Duration'), $this->getDuration(),
            __('The duration in seconds this item should be displayed'), 'd', 'required');


        $formFields[] = Form::AddNumber('offset', __('Offset'), $this->GetOption('offset'),
            __('The offset in minutes that should be applied to the current time.'), 'o', NULL, 'offset-control-group');

        // Offer a choice of theme
        $formFields[] = Form::AddCombo(
                    'themeid', 
                    __('Theme'), 
                    $this->GetOption('theme'),
                    array(array('themeid' => '1', 'theme' => 'Light'), array('themeid' => '2', 'theme' => 'Dark')),
                    'themeid',
                    'theme',
                    __('Please select a theme for the clock.'), 
                    't',
                    'analogue-control-group');

        $formFields[] = Form::AddMessage(sprintf(__('Enter a format for the Digital Clock below. e.g. [HH:mm] or [DD/MM/YYYY]. See the <a href="%s" target="_blank">format guide</a> for more information.'), Help::Link('Widget', 'ClockFormat')), 'digital-control-group');
        
        $formFields[] = Form::AddMultiText('ta_text', NULL, $this->getRawNode('format', null),
            __('Enter a format for the clock'), 'f', 10, '', 'digital-control-group');

        Theme::Set('form_fields', $formFields);

        // Dependencies (some fields should be shown / hidden)
        $this->SetFieldDependencies($response);

        // Modules should be rendered using the theme engine.
        $response->html = Theme::RenderReturn('form_render');

        $this->configureFormButtons($response);
        $response->dialogTitle = __('Edit Clock');
        $response->callBack = 'text_callback';
        return $response;
        $this->response->AddButton(__('Apply'), 'XiboDialogApply("#ModuleForm")');
    }

    /**
     * Edit Media in the Database
     */
    public function EditMedia()
    {
        $response = $this->getState();

        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // You must also provide a duration (all media items must provide this field)
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration(), false));
        $this->SetOption('theme', \Kit::GetParam('themeid', _POST, _INT, 0));
        $this->SetOption('clockTypeId', \Kit::GetParam('clockTypeId', _POST, _INT, 1));
        $this->SetOption('offset', \Kit::GetParam('offset', _POST, _INT, 0));
        $this->setRawNode('ta_text', \Kit::GetParam('ta_text', _POST, _HTMLSTRING));

        // Save the widget
        $this->saveWidget();

        // Load an edit form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();
            $this->response->callBack = 'refreshPreview("' . $this->regionid . '")';
        
        return $response;
    }

    /**
     * @param ApplicationState $response
     */
    private function SetFieldDependencies(&$response)
    {
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
            
        $response->AddFieldAction('clockTypeId', 'init', 1, $clockTypeId_1);
        $response->AddFieldAction('clockTypeId', 'change', 1, $clockTypeId_1);
        $response->AddFieldAction('clockTypeId', 'init', 2, $clockTypeId_2);
        $response->AddFieldAction('clockTypeId', 'change', 2, $clockTypeId_2);
        $response->AddFieldAction('clockTypeId', 'init', 3, $clockTypeId_3);
        $response->AddFieldAction('clockTypeId', 'change', 3, $clockTypeId_3);
    }

    /**
     * GetResource
     * Return the rendered resource to be used by the client (or a preview) for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return mixed
     */
    public function GetResource($displayId = 0)
    {
        $template = null;

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
                $isPreview = (\Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
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
                $format = $this->getRawNode('format', null);

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
                        'previewWidth' => \Kit::GetParam('width', _GET, _DOUBLE, 0),
                        'previewHeight' => \Kit::GetParam('height', _GET, _DOUBLE, 0),
                        'originalWidth' => $this->region->width,
                        'originalHeight' => $this->region->height,
                        'scaleOverride' => \Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
                    );

                $isPreview = (\Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
                $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
                $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'moment.js"></script>';
                $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
                $javaScriptContent .= <<<END
<script>
    var options = ' . json_encode($options) . '

    function updateClock() {
        $(".clock").each(function() {
            $(this).html(moment().add(' . {$this->GetOption('offset', 0)} . ', "m").format($(this).attr("format")));
        });
    }

    $(document).ready(function() {
        updateClock();
        setInterval(updateClock, 1000);
        $("body").xiboLayoutScaler(options);
    });
</script>
END;

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
                $isPreview = (\Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
                $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
                $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'flipclock.min.js"></script>';

                // Replace the After body Content
                $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

                break;
        }

        // If we are a preview, then pass in the width and height
        $template = str_replace('<!--[[[PREVIEW_WIDTH]]]-->', \Kit::GetParam('width', _GET, _DOUBLE, 0), $template);
        $template = str_replace('<!--[[[PREVIEW_HEIGHT]]]-->', \Kit::GetParam('height', _GET, _DOUBLE, 0), $template);

        // Replace the View Port Width?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->region->width, $template);

        // Return that content.
        return $template;
    }

    /**
     * Is Valid
     * @return int
     */
    public function IsValid()
    {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
    }
}
