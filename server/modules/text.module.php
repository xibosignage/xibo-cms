<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type = 'text';
        
        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    private function InstallFiles() {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->addModuleFile('modules/preview/vendor/moment.js');
        $media->addModuleFile('modules/preview/vendor/jquery.marquee.min.js');
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');
        $media->addModuleFile('modules/preview/xibo-text-render.js');
    }
    
    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm()
    {
        $db         =& $this->db;
        $user       =& $this->user;

        // Would like to get the regions width / height
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $rWidth     = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight    = Kit::GetParam('rHeight', _REQUEST, _STRING);

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
    
        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'), array(array('name' => 'enlarge', 'value' => true)));
        $tabs[] = FormManager::AddTab('options', __('Options'));

        Theme::Set('form_tabs', $tabs);

        $formFields = array();
        $formFields['options'][] = FormManager::AddCombo(
                    'direction', 
                    __('Direction'), 
                    NULL,
                    array(
                        array('directionid' => 'none', 'direction' => __('None')), 
                        array('directionid' => 'left', 'direction' => __('Left')), 
                        array('directionid' => 'right', 'direction' => __('Right')), 
                        array('directionid' => 'up', 'direction' => __('Up')), 
                        array('directionid' => 'down', 'direction' => __('Down'))
                    ),
                    'directionid',
                    'direction',
                    __('Please select which direction this text should scroll. If scrolling is not required, select None'), 
                    's');

        $formFields['options'][] = FormManager::AddNumber('scrollSpeed', __('Scroll Speed'), NULL, 
            __('The scroll speed to apply if a direction is specified. Higher is faster.'), 'e');

        $formFields['options'][] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this counter should be displayed'), 'd', 'required');

        // Handle the substitutions as RAW items
        $subs = array(
                array('Substitute' => 'Clock'),
                array('Substitute' => 'HH:mm'),
                array('Substitute' => 'Date'),
                array('Substitute' => 'dd-mm-yy')
            );
        Theme::Set('substitutions', $subs);
        $formFields['general'][] = FormManager::AddRaw(Theme::RenderReturn('media_form_text_edit'));

        $formFields['general'][] = FormManager::AddMultiText('ta_text', NULL, NULL, 
            __('Enter the text to display. Please note that the background colour has automatically coloured to your region background colour.'), 't', 10);

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_options', $formFields['options']);

        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->callBack = 'text_callback';
        $this->response->dialogSize = __('large');
        $this->response->dialogTitle = __('Add Text');

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }

    /**
     * Return the Edit Form as HTML
     * @return
     */
    public function EditForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

        // Permissions
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $mediaid . '">');
        
        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'), array(array('name' => 'enlarge', 'value' => true)));
        $tabs[] = FormManager::AddTab('options', __('Options'));

        Theme::Set('form_tabs', $tabs);

        $formFields = array();
        $formFields['options'][] = FormManager::AddCombo(
                    'direction', 
                    __('Direction'), 
                    $this->GetOption('direction'),
                    array(
                        array('directionid' => 'none', 'direction' => __('None')), 
                        array('directionid' => 'left', 'direction' => __('Left')), 
                        array('directionid' => 'right', 'direction' => __('Right')), 
                        array('directionid' => 'up', 'direction' => __('Up')), 
                        array('directionid' => 'down', 'direction' => __('Down'))
                    ),
                    'directionid',
                    'direction',
                    __('Please select which direction this text should scroll. If scrolling is not required, select None'), 
                    's');

        $formFields['options'][] = FormManager::AddNumber('scrollSpeed', __('Scroll Speed'), $this->GetOption('scrollSpeed'), 
            __('The scroll speed to apply if a direction is specified. Higher is faster.'), 'e');

        $formFields['options'][] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this counter should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        // Handle the substitutions as RAW items
        $subs = array(
                array('Substitute' => 'Clock'),
                array('Substitute' => 'HH:mm'),
                array('Substitute' => 'Date'),
                array('Substitute' => 'DD/MM/YYYY')
            );
        Theme::Set('substitutions', $subs);

        // Get the text out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        // Get the Text Node out of this
        $textNodes = $rawXml->getElementsByTagName('text');
        $textNode = $textNodes->item(0);

        $formFields['general'][] = FormManager::AddMultiText('ta_text', NULL, $textNode->nodeValue, 
            __('Enter the text to display. Please note that the background colour has automatically coloured to your region background colour.'), 't', 10);

        $formFields['general'][] = FormManager::AddRaw(Theme::RenderReturn('media_form_text_edit'));

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_options', $formFields['options']);

        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->callBack = 'text_callback';
        $this->response->dialogSize = 'large';
        $this->response->dialogTitle = __('Edit Text');
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;

        //Other properties
        $direction    = Kit::GetParam('direction', _POST, _WORD, 'none');
        $duration     = Kit::GetParam('duration', _POST, _INT, 0);
        $text         = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
        $scrollSpeed  = Kit::GetParam('scrollSpeed', _POST, _INT, 2);

        $url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

        //validation
        if ($text == '')
        {
            $this->response->SetError('Please enter some text');
            $this->response->keepOpen = true;
            return $this->response;
        }

        if ($duration == 0)
        {
            $this->response->SetError('You must enter a duration.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Required Attributes
        $this->mediaid  = md5(uniqid());
        $this->duration = $duration;

        // Any Options
        $this->SetOption('xmds', true);
        $this->SetOption('direction', $direction);
        $this->SetOption('scrollSpeed', $scrollSpeed);
        $this->SetRaw('<text><![CDATA[' . $text . ']]></text>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'text');

        if ($this->showRegionOptions) {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }

        return $this->response;
    }

    /**
     * Edit Media in the Database
     * @return
     */
    public function EditMedia()
    {
        $user =& $this->user;

        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

        if (!$this->auth->edit) {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        //Other properties
        $direction    = Kit::GetParam('direction', _POST, _WORD, 'none');
        $text         = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
        $scrollSpeed  = Kit::GetParam('scrollSpeed', _POST, _INT, 30);
        
        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

        Debug::LogEntry('audit', 'Text received: ' . $text);

        $url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

        // Validation
        if ($text == '')
        {
            $this->response->SetError('Please enter some text');
            $this->response->keepOpen = true;
            return $this->response;
        }

        if ($this->duration == 0)
        {
            $this->response->SetError('You must enter a duration.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Any Options
        $this->SetOption('xmds', true);
        $this->SetOption('direction', $direction);
        $this->SetOption('scrollSpeed', $scrollSpeed);
        $this->SetRaw('<text><![CDATA[' . $text . ']]></text>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'text');

        if ($this->showRegionOptions) {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }

        return $this->response;
    }

    /**
     * Preview
     * @param <type> $width
     * @param <type> $height
     * @return <type>
     */
    public function Preview($width, $height)
    {
        if ($this->previewEnabled == 0)
            return parent::Preview ($width, $height);
        
        return $this->PreviewAsClient($width, $height);
    }

    /**
     * Raw Preview
     */
    public function GetResource($displayId = 0)
    {
        // Make sure this module is installed correctly
        $this->InstallFiles();

        // Load in the template
        if ($this->layoutSchemaVersion == 1)
            $template = file_get_contents('modules/preview/Html4TransitionalTemplate.html');
        else
            $template = file_get_contents('modules/preview/HtmlTemplate.html');

        // Replace the View Port Width?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->width, $template);

        $width = Kit::GetParam('width', _REQUEST, _DOUBLE);
        $height = Kit::GetParam('height', _REQUEST, _DOUBLE);
        $direction = $this->GetOption('direction');
        $scrollSpeed = $this->GetOption('scrollSpeed');
        $fitText = $this->GetOption('fitText', 0);
        $duration = $this->duration;

        // Get the text out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        // Get the Text Node
        $textNodes = $rawXml->getElementsByTagName('text');
        $textNode = $textNodes->item(0);
        $text = $textNode->nodeValue;

        // Set some options
        $options = array('direction' => $direction,
            'duration' => $duration,
            'durationIsPerItem' => false,
            'numItems' => 1,
            'takeItemsFrom' => 'start',
            'itemsPerPage' => 0,
            'scrollSpeed' => $scrollSpeed,
            'originalWidth' => $this->width,
            'originalHeight' => $this->height,
            'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
            'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
        );

        // See if we need to replace out any [clock] or [date] tags
        $clock = false;

        if (stripos($text, '[Clock]')) {
            $clock = true;
            $text = str_replace('[Clock]', '[HH:mm]', $text);
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
        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
        $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';

        if ($direction != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery.marquee.min.js"></script>';
        
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

        // Provide a sample of the text content
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        // Get the Text Node out of this
        $textNodes = $rawXml->getElementsByTagName('text');
        $textNode = $textNodes->item(0);
        $text = $textNode->nodeValue;

        $output .= '<div class="hoverPreview">';
        $output .= '    ' . $text;
        $output .= '</div>';

        return $output;
    }
    
    public function IsValid() {
        // Text rendering will be valid
        return 1;
    }
}
?>
