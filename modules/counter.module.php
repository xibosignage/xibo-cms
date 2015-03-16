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

    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type = 'counter';

        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }
	
    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm()
    {
        $this->response = new ResponseManager();
        $db 		=& $this->db;
        $user		=& $this->user;

        // Would like to get the regions width / height
        $layoutid	= $this->layoutid;
        $regionid	= $this->regionid;
        $rWidth		= Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight	= Kit::GetParam('rHeight', _REQUEST, _STRING);

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');

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
        
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->callBack = 'text_callback';
        $this->response->dialogTitle = __('Add Counter');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }

    /**
     * Return the Edit Form as HTML
     * @return
     */
    public function EditForm()
    {
        $this->response = new ResponseManager();
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
        $popupNotification = $this->GetOption('popupNotification');

        // Get the text out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        Debug::LogEntry('audit', 'Raw XML returned: ' . $this->GetRaw());

        // Get the Text Node out of this
        $textNodes = $rawXml->getElementsByTagName('template');
        $textNode = $textNodes->item(0);
        $text = $textNode->nodeValue;

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="mediaid" value="' . $mediaid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');

        $formFields = array();
        $formFields[] = FormManager::AddMessage(__('Ubuntu Client Only'));

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this counter should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        $formFields[] = FormManager::AddCheckbox('popupNotification', __('Pop-up Notification?'), 
            $popupNotification, __('Popup a notification when the counter changes?'), 
            'n');

        $formFields[] = FormManager::AddMultiText('ta_text', NULL, $text, 
            __('Enter a format that should be applied to the counter when it is show.'), 't', 10);

        Theme::Set('form_fields', $formFields);

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->callBack = 'text_callback';
        $this->response->dialogTitle = __('Edit Counter');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $this->response = new ResponseManager();
        $db 		=& $this->db;

        $layoutid 	= $this->layoutid;
        $regionid 	= $this->regionid;
        $mediaid	= $this->mediaid;

        //Other properties
        $duration = Kit::GetParam('duration', _POST, _INT, 0, false);
        $text = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
        $popupNotification = Kit::GetParam('popupNotification', _POST, _CHECKBOX);

        $url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

        //validation
        if ($text == '')
        {
            $this->response->SetError('Please enter a template');
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
        $this->SetOption('popupNotification', $popupNotification);
        $this->SetRaw('<template><![CDATA[' . $text . ']]></template>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'counter');

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
        $this->response = new ResponseManager();
        $db =& $this->db;
        $user =& $this->user;

        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        //Other properties
        $text = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
        $popupNotification = Kit::GetParam('popupNotification', _POST, _CHECKBOX);

        Debug::LogEntry('audit', 'Popup notification:' . $popupNotification);

        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);

        Debug::LogEntry('audit', 'Text received: ' . $text);

        $url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

        // Validation
        if ($text == '')
        {
            $this->response->SetError('Please enter a template');
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
        $this->SetOption('popupNotification', $popupNotification);
        $this->SetRaw('<template><![CDATA[' . $text . ']]></template>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'counter');

        if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }

        return $this->response;
    }

    public function Preview($width, $height)
    {
        if ($this->previewEnabled == 0)
            return parent::Preview ($width, $height);
        
        $regionid   = $this->regionid;

        // Get the text out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        // Get the Text Node out of this
        $textNodes 	= $rawXml->getElementsByTagName('template');
        $textNode 	= $textNodes->item(0);
        $text 	= $textNode->nodeValue;

        $textId 	= $regionid.'_text';
        $innerId 	= $regionid.'_innerText';
        $timerId	= $regionid.'_timer';
        $widthPx	= $width.'px';
        $heightPx	= $height.'px';

        //Show the contents of text accordingly
        $return = <<<END
        <div id="$textId" style="position:relative; overflow:hidden ;width:$widthPx; height:$heightPx; font-size: 1em;">
            <div id="$innerId" style="position:absolute; left: 0px; top: 0px;">
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
?>
