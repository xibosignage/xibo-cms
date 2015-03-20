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
 */ 
class text extends Module
{
    /**
     * Install Files
     */
    public function InstallFiles() {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->addModuleFile('modules/preview/vendor/moment.js');
        $media->addModuleFile('modules/preview/vendor/jquery.marquee.min.js');
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');
        $media->addModuleFile('modules/preview/xibo-text-render.js');
    }
    
    /**
     * Return the Add Form
     */
    public function AddForm()
    {
        $response = new ResponseManager();

        // Configure form
        $this->configureForm('AddMedia');
        
        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'), array(array('name' => 'enlarge', 'value' => true)));
        $tabs[] = FormManager::AddTab('options', __('Options'));

        Theme::Set('form_tabs', $tabs);

        $formFields = array();
	
	$formFields['options'][] = FormManager::AddText('name', __('Name'), NULL, 
            __('An optional name for this media'), 'n');

        $formFields['options'][] = FormManager::AddCombo(
                'effect', 
                __('Effect'), 
                $this->GetOption('effect'),
                array(
                    array('effectid' => 'none', 'effect' => __('None')), 
                    array('effectid' => 'fade', 'effect' => __('Fade')),
                    array('effectid' => 'fadeout', 'effect' => __('Fade Out')),
                    array('effectid' => 'scrollHorz', 'effect' => __('Scroll Horizontal')),
                    array('effectid' => 'scrollVert', 'effect' => __('Scroll Vertical')),
                    array('effectid' => 'flipHorz', 'effect' => __('Flip Horizontal')),
                    array('effectid' => 'flipVert', 'effect' => __('Flip Vertical')),
                    array('effectid' => 'shuffle', 'effect' => __('Shuffle')),
                    array('effectid' => 'tileSlide', 'effect' => __('Tile Slide')),
                    array('effectid' => 'tileBlind', 'effect' => __('Tile Blinds')),
                    array('effectid' => 'marqueeLeft', 'effect' => __('Marquee Left')),
                    array('effectid' => 'marqueeRight', 'effect' => __('Marquee Right')),
                    array('effectid' => 'marqueeUp', 'effect' => __('Marquee Up')),
                    array('effectid' => 'marqueeDown', 'effect' => __('Marquee Down')),
                ),
                'effectid',
                'effect',
                __('Please select the effect that will be used. Some effects will transition between paragraphs in the text. Marquee effects are CPU intensive and may not be suitable for lower power displays.'), 
                'e');

        $formFields['options'][] = FormManager::AddNumber('speed', __('Speed'), NULL, 
            __('The transition speed of the selected effect in milliseconds (normal = 1000) or the Marquee Speed in a low to high scale (normal = 1).'), 's', NULL, 'effect-controls');

        // A list of web safe colours
        $formFields['options'][] = FormManager::AddText('backgroundColor', __('Background Colour'), NULL, 
            __('The selected effect works best with a background colour. Optionally add one here.'), 'c', NULL, 'effect-controls');

        $formFields['options'][] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this should be displayed'), 'd', 'required');

        // Handle the substitutions as RAW items
        $subs = array(
                array('Substitute' => 'Clock'),
                array('Substitute' => 'Clock|HH:mm'),
                array('Substitute' => 'Date'),
                array('Substitute' => 'Clock|DD/MM/YYYY')
            );
        Theme::Set('substitutions', $subs);
        $formFields['general'][] = FormManager::AddRaw(Theme::RenderReturn('media_form_text_edit'));

        $formFields['general'][] = FormManager::AddMultiText('ta_text', NULL, NULL, 
            __('Enter the text to display. Please note that the background colour has automatically coloured to your layout background colour.'), 't', 10);

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_options', $formFields['options']);

        // Add a dependency
        $response->AddFieldAction('effect', 'init', 'none', array('.effect-controls' => array('display' => 'none')));
        $response->AddFieldAction('effect', 'change', 'none', array('.effect-controls' => array('display' => 'none')));
        $response->AddFieldAction('effect', 'init', 'none', array('.effect-controls' => array('display' => 'block')), 'not');
        $response->AddFieldAction('effect', 'change', 'none', array('.effect-controls' => array('display' => 'block')), 'not');

        $response->html = Theme::RenderReturn('form_render');
        $response->callBack = 'text_callback';
        $response->dialogSize = 'large';
        $this->configureFormButtons($response);
        $response->dialogTitle = __('Add Text');


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

        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'), array(array('name' => 'enlarge', 'value' => true)));
        $tabs[] = FormManager::AddTab('options', __('Options'));

        Theme::Set('form_tabs', $tabs);

        $formFields = array();

        // Handle older layouts that have a direction node but no effect node
        $oldDirection = $this->GetOption('direction', 'none');
        if ($oldDirection != 'none')
            $oldDirection = 'marquee' . ucfirst($oldDirection);
	
	$formFields['options'][] = FormManager::AddText('name', __('Name'), $this->GetOption('name'), 
	    __('An optional name for this media'), 'n');

        $formFields['options'][] = FormManager::AddCombo(
                'effect', 
                __('Effect'), 
                $this->GetOption('effect', $oldDirection),
                array(
                    array('effectid' => 'none', 'effect' => __('None')), 
                    array('effectid' => 'fade', 'effect' => __('Fade')),
                    array('effectid' => 'fadeout', 'effect' => __('Fade Out')),
                    array('effectid' => 'scrollHorz', 'effect' => __('Scroll Horizontal')),
                    array('effectid' => 'scrollVert', 'effect' => __('Scroll Vertical')),
                    array('effectid' => 'flipHorz', 'effect' => __('Flip Horizontal')),
                    array('effectid' => 'flipVert', 'effect' => __('Flip Vertical')),
                    array('effectid' => 'shuffle', 'effect' => __('Shuffle')),
                    array('effectid' => 'tileSlide', 'effect' => __('Tile Slide')),
                    array('effectid' => 'tileBlind', 'effect' => __('Tile Blinds')),
                    array('effectid' => 'marqueeLeft', 'effect' => __('Marquee Left')),
                    array('effectid' => 'marqueeRight', 'effect' => __('Marquee Right')),
                    array('effectid' => 'marqueeUp', 'effect' => __('Marquee Up')),
                    array('effectid' => 'marqueeDown', 'effect' => __('Marquee Down')),
                ),
                'effectid',
                'effect',
                __('Please select the effect that will be used to transition between items. If all items should be output, select None. Marquee effects are CPU intensive and may not be suitable for lower power displays.'), 
                'e');

        $formFields['options'][] = FormManager::AddNumber('speed', __('Speed'), $this->GetOption('speed'), 
            __('The transition speed of the selected effect in milliseconds (normal = 1000) or the Marquee Speed in a low to high scale (normal = 1).'), 's', NULL, 'effect-controls');

        // A list of web safe colours
        $formFields['options'][] = FormManager::AddText('backgroundColor', __('Background Colour'), $this->GetOption('backgroundColor'), 
            __('The selected effect works best with a background colour. Optionally add one here.'), 'c', NULL, 'effect-controls');

        $formFields['options'][] = FormManager::AddNumber('duration', __('Duration'), $this->getDuration(),
            __('The duration in seconds this counter should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        // Handle the substitutions as RAW items
        $subs = array(
                array('Substitute' => 'Clock'),
                array('Substitute' => 'Clock|HH:mm'),
                array('Substitute' => 'Date'),
                array('Substitute' => 'Clock|DD/MM/YYYY')
            );
        Theme::Set('substitutions', $subs);

        $textNode = $this->getRawNode('text', null);

        $formFields['general'][] = FormManager::AddMultiText('ta_text', NULL, $textNode,
            __('Enter the text to display. Please note that the background colour has automatically coloured to your layout background colour.'), 't', 10);

        $formFields['general'][] = FormManager::AddRaw(Theme::RenderReturn('media_form_text_edit'));

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_options', $formFields['options']);

        // Add a dependency
        $response->AddFieldAction('effect', 'init', 'none', array('.effect-controls' => array('display' => 'none')));
        $response->AddFieldAction('effect', 'change', 'none', array('.effect-controls' => array('display' => 'none')));
        $response->AddFieldAction('effect', 'init', 'none', array('.effect-controls' => array('display' => 'block')), 'not');
        $response->AddFieldAction('effect', 'change', 'none', array('.effect-controls' => array('display' => 'block')), 'not');

        $response->html = Theme::RenderReturn('form_render');
        $response->callBack = 'text_callback';
        $response->dialogSize = 'large';
        $this->configureFormButtons($response);
        $response->dialogTitle = __('Edit Text');
        $this->response->AddButton(__('Apply'), 'XiboDialogApply("#ModuleForm")');

        return $response;
    }

    /**
     * Add Media to the Database
     */
    public function AddMedia()
    {
        $response = new ResponseManager();

        // Other properties
        $duration = \Kit::GetParam('duration', _POST, _INT, 0, false);
        $text = \Kit::GetParam('ta_text', _POST, _HTMLSTRING);
	$name 	      = \Kit::GetParam('name', _POST, _STRING);

        // Validation
        if ($text == '')
            throw new InvalidArgumentException(__('Please enter some text'));

        if ($duration == 0)
            throw new InvalidArgumentException(__('You must enter a duration.'));

        // Any Options
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration()));
        $this->SetOption('xmds', true);
        $this->SetOption('effect', \Kit::GetParam('effect', _POST, _STRING));
        $this->SetOption('speed', \Kit::GetParam('speed', _POST, _INT));
        $this->SetOption('backgroundColor', \Kit::GetParam('backgroundColor', _POST, _STRING));
        $this->SetOption('name', $name);
        $this->setRawNode('text', $text);

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

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Other properties
        $text = \Kit::GetParam('ta_text', _POST, _HTMLSTRING);
        $name = \Kit::GetParam('name', _POST, _STRING);

        // Validation
        if ($text == '')
            throw new InvalidArgumentException(__('Please enter some text'));

        // Any Options
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration(), false));
        $this->SetOption('xmds', true);
        $this->SetOption('effect', \Kit::GetParam('effect', _POST, _STRING));
        $this->SetOption('speed', \Kit::GetParam('speed', _POST, _INT));
        $this->SetOption('backgroundColor', \Kit::GetParam('backgroundColor', _POST, _STRING));
        $this->SetOption('name', $name);
        $this->setRawNode('text', $text);

        // Save the widget
        $this->saveWidget();

        // Load form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();
        $this->response->callBack = 'refreshPreview("' . $this->regionid . '")';

        return $response;
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function GetResource($displayId = 0)
    {
        // Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplate.html');

        // Replace the View Port Width?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->region->width, $template);

        $duration = $this->getDuration();

        $text = $this->getRawNode('text', null);

        // Handle older layouts that have a direction node but no effect node
        $oldDirection = $this->GetOption('direction', 'none');
        
        if ($oldDirection != 'none')
            $oldDirection = 'marquee' . ucfirst($oldDirection);

        $effect = $this->GetOption('effect', $oldDirection);

        // Set some options
        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $effect,
            'duration' => $duration,
            'durationIsPerItem' => false,
            'numItems' => 1,
            'takeItemsFrom' => 'start',
            'itemsPerPage' => 0,
            'speed' => $this->GetOption('speed', 0),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => \Kit::GetParam('width', _GET, _DOUBLE, 0),
            'previewHeight' => \Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => \Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
        );

        // See if we need to replace out any [clock] or [date] tags
        $clock = false;

        if (stripos($text, '[Clock]')) {
            $clock = true;
            $text = str_replace('[Clock]', '[HH:mm]', $text);
        }

        if (stripos($text, '[Clock|')) {
            $clock = true;
            $text = str_replace('[Clock|', '[', $text);
        }

        if (stripos($text, '[Date]')) {
            $clock = true;
            $text = str_replace('[Date]', '[DD/MM/YYYY]', $text);
        }

        if ($clock) {
            // Strip out the bit between the [] brackets and use that as the format mask for moment.
            $matches = '';
            preg_match_all('/\[.*?\]/', $text, $matches);

            foreach($matches[0] as $subs) {
                $text = str_replace($subs, '<span class="clock" format="' . str_replace('[', '', str_replace(']', '', $subs)) . '"></span>', $text);
            }
        }

        // Generate a JSON string of substituted items.
        $items[] = $text;
        
        // Replace the head content
        $isPreview = (\Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
        $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';

        // Need the marquee plugin?
        if (stripos($effect, 'marquee') !== false)
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery.marquee.min.js"></script>';
        
        // Need the cycle plugin?
        if ($effect != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-cycle-2.1.6.min.js"></script>';
                
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-text-render.js"></script>';

        // Do we need to include moment?
        if ($clock)
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'moment.js"></script>';
 
        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("#content").xiboTextRender(options, items); $("body").xiboLayoutScaler(options);';
        
        if ($clock)
            $javaScriptContent .= ' updateClock(); setInterval(updateClock, 1000); ';

        $javaScriptContent .= '   }); ';

        if ($clock) {
            $javaScriptContent .= '
                function updateClock() {
                    $(".clock").each(function() {
                        $(this).html(moment().format($(this).attr("format")));
                    });
                }
            ';
        }

        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

        // Add our fonts.css file
        $headContent  = '<link href="' . (($isPreview) ? 'modules/preview/' : '') . 'fonts.css" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::ItemPath('css/client.css')) . '</style>';

        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', '', $template);

        return $template;
    }

    public function HoverPreview()
    {
        // Default Hover window contains a thumbnail, media type and duration
        $output = parent::HoverPreview();

        $output .= '<div class="hoverPreview">';
        $output .= '    ' . $this->getRawNode('text', null);;
        $output .= '</div>';

        return $output;
    }

    public function GetName() {
        return $this->GetOption('name');
    }
    
    public function IsValid()
    {
        // Text rendering will be valid
        return 1;
    }
}
