<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2012 Daniel Garner and James Packer
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
        $this->displayType = 'Text';
        $this->name = 'Text';

        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }
	
    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm()
    {
        $db 		=& $this->db;
        $user		=& $this->user;

        // Would like to get the regions width / height
        $layoutid	= $this->layoutid;
        $regionid	= $this->regionid;
        $rWidth		= Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight	= Kit::GetParam('rHeight', _REQUEST, _STRING);

        $direction_list = listcontent("none|None,left|Left,right|Right,up|Up,down|Down", "direction");

        $msgFitText = __('Fit text to region');

            $form = <<<FORM
            <form id="ModuleForm" class="XiboTextForm" method="post" action="index.php?p=module&mod=text&q=Exec&method=AddMedia">
                    <input type="hidden" name="layoutid" value="$layoutid">
                    <input type="hidden" id="iRegionId" name="regionid" value="$regionid">
                    <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
                    <table>
                            <tr>
                            <td><label for="direction" title="The Direction this text should move, if any">Direction<span class="required">*</span></label></td>
                            <td>$direction_list</td>
                            <td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration<span class="required">*</span></label></td>
                            <td><input id="duration" name="duration" type="text"></td>
                            </tr>
                            <tr>
                                <td><label for="scrollSpeed" title="The scroll speed of the ticker.">Scroll Speed<span class="required">*</span> (higher is faster)</label></td>
                                <td><input id="scrollSpeed" name="scrollSpeed" type="text" value="2"></td>
                                <td><label for="fitText" title="$msgFitText">$msgFitText</label></td>
                                <td><input id="fitText" name="fitText" type="checkbox"></td>
                            </tr>
                            <tr>
                                    <td colspan="4">
                                            <textarea id="ta_text" name="ta_text"></textarea>
                                    </td>
                            </tr>
                    </table>
            </form>
FORM;

        $this->response->html 		= $form;
        $this->response->callBack 	= 'text_callback';
        $this->response->dialogTitle    = __('Add Text');
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

        // Other properties
        $direction = $this->GetOption('direction');
        $scrollSpeed = $this->GetOption('scrollSpeed');
        $fitText = $this->GetOption('fitText', 0);
        $fitTextChecked = ($fitText == 0) ? '' : ' checked';

        // Get the text out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        Debug::LogEntry($db, 'audit', 'Raw XML returned: ' . $this->GetRaw());

        // Get the Text Node out of this
        $textNodes = $rawXml->getElementsByTagName('text');
        $textNode = $textNodes->item(0);
        $text = $textNode->nodeValue;

        $direction_list = listcontent("none|None,left|Left,right|Right,up|Up,down|Down", "direction", $direction);

        $durationFieldEnabled = ($this->auth->modifyPermissions) ? '' : ' readonly';

        $msgFitText = __('Fit text to region');

        // Output the form
        $form = <<<FORM
        <form id="ModuleForm" class="XiboTextForm" method="post" action="index.php?p=module&mod=text&q=Exec&method=EditMedia">
                <input type="hidden" name="layoutid" value="$layoutid">
                <input type="hidden" name="mediaid" value="$mediaid">
                <input type="hidden" id="iRegionId" name="regionid" value="$regionid">
                <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
                <table>
                        <tr>
                        <td><label for="direction" title="The Direction this text should move, if any">Direction<span class="required">*</span></label></td>
                        <td>$direction_list</td>
                        <td><label for="duration" title="The duration in seconds this text should be displayed">Duration<span class="required">*</span></label></td>
                        <td><input id="duration" name="duration" value="$this->duration" type="text" $durationFieldEnabled></td>
                        </tr>
                        <tr>
                            <td><label for="scrollSpeed" title="The scroll speed of the ticker.">Scroll Speed<span class="required">*</span> (higher is faster)</label></td>
                            <td><input id="scrollSpeed" name="scrollSpeed" type="text" value="$scrollSpeed"></td>
                                <td><label for="fitText" title="$msgFitText">$msgFitText</label></td>
                                <td><input id="fitText" name="fitText" type="checkbox" $fitTextChecked></td>
                        </tr>
                        <tr>
                                <td colspan="4">
                                        <textarea id="ta_text" name="ta_text">$text</textarea>
                                </td>
                        </tr>
                </table>
        </form>
FORM;

        $this->response->html = $form;
        $this->response->callBack = 'text_callback';
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
        $db 		=& $this->db;

        $layoutid 	= $this->layoutid;
        $regionid 	= $this->regionid;
        $mediaid	= $this->mediaid;

        //Other properties
        $direction	  = Kit::GetParam('direction', _POST, _WORD, 'none');
        $duration	  = Kit::GetParam('duration', _POST, _INT, 0);
        $text		  = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
        $scrollSpeed  = Kit::GetParam('scrollSpeed', _POST, _INT, 2);
        $fitText = Kit::GetParam('fitText', _POST, _CHECKBOX);

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
        $this->mediaid	= md5(uniqid());
        $this->duration = $duration;

        // Any Options
        $this->SetOption('direction', $direction);
        $this->SetOption('scrollSpeed', $scrollSpeed);
        $this->SetOption('fitText', $fitText);
        $this->SetRaw('<text><![CDATA[' . $text . ']]></text>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'text');

	if ($this->showRegionOptions)
        {
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
            $db 		=& $this->db;
            $user =& $this->user;

            $layoutid 	= $this->layoutid;
            $regionid 	= $this->regionid;
            $mediaid	= $this->mediaid;

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

            //Other properties
            $direction	  = Kit::GetParam('direction', _POST, _WORD, 'none');
            $text		  = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
            $scrollSpeed  = Kit::GetParam('scrollSpeed', _POST, _INT, 30);
        $fitText = Kit::GetParam('fitText', _POST, _CHECKBOX);

        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

            Debug::LogEntry($db, 'audit', 'Text received: ' . $text);

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
            $this->SetOption('direction', $direction);
            $this->SetOption('scrollSpeed', $scrollSpeed);
            $this->SetOption('fitText', $fitText);
            $this->SetRaw('<text><![CDATA[' . $text . ']]></text>');

            // Should have built the media object entirely by this time
            // This saves the Media Object to the Region
            $this->UpdateRegion();

            //Set this as the session information
            setSession('content', 'type', 'text');

	if ($this->showRegionOptions)
        {
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
        
        $layoutId = $this->layoutid;
        $regionId = $this->regionid;

        $mediaId = $this->mediaid;
        $lkId = $this->lkid;
        $mediaType = $this->type;
        $mediaDuration = $this->duration;
        
        $widthPx	= $width.'px';
        $heightPx	= $height.'px';

        return '<iframe scrolling="no" id="innerIframe" src="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=RawPreview&raw=true&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '&width=' . $width . '&height=' . $height . '" width="' . $widthPx . '" height="' . $heightPx . '" style="border:0;"></iframe>';
    }

    /**
     * Raw Preview
     */
    public function RawPreview()
    {
        // Behave exactly like the client.

        // Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplate.htm');

        $width = Kit::GetParam('width', _REQUEST, _INT);
        $height = Kit::GetParam('height', _REQUEST, _INT);
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

        // Replace the head content
        $headContent  = '<script type="text/javascript">';
        $headContent .= '   function init() { ';
        $headContent .= '       $("#text").xiboRender({ ';
        $headContent .= '           type: "text",';
        $headContent .= '           direction: "' . $direction . '",';
        $headContent .= '           duration: ' . $duration . ',';
        $headContent .= '           durationIsPerItem: false,';
        $headContent .= '           numItems: 0,';
        $headContent .= '           width: ' . $width . ',';
        $headContent .= '           height: ' . $height . ',';
        $headContent .= '           scrollSpeed: ' . $scrollSpeed . ',';
        $headContent .= '           fitText: ' . (($fitText == 0) ? 'false' : 'true') . ',';
        $headContent .= '           scaleText: ' . (($fitText == 1) ? 'false' : 'true') . ',';
        $headContent .= '           scaleFactor: 1';
        $headContent .= '       });';
        $headContent .= '   } ';
        $headContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        // Generate the body content
        $bodyContent  = '';
        $bodyContent .= '<div id="contentPane" style="overflow: none; width:' . $width . 'px; height:' . $height . 'px;">';
        $bodyContent .= '   <div id="text">';
        $bodyContent .= '       ' . $text;
        $bodyContent .= '   </div>';
        $bodyContent .= '</div>';

        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', $bodyContent, $template);

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
}
?>
