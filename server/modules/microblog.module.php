<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2010 Daniel Garner
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
class microblog extends Module
{
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type = 'microblog';
        $this->displayType = __('Microblog');

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

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');

        $form = Theme::RenderReturn('media_form_microblog_add');

        $this->response->html = $form;
        $this->response->dialogTitle = __('Add Microblog');
        $this->response->dialogClass = __('modal-big');
        $this->response->callBack = 'microblog_callback';
        $this->response->AddButton(__('Help'), 'XiboHelpRender("index.php?p=help&q=Display&Topic=Microblog&Category=Media")');

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
        $db 		=& $this->db;

        $layoutid	= $this->layoutid;
        $regionid	= $this->regionid;
        $mediaid  	= $this->mediaid;

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
        
        // Get some options
        Theme::Set('searchTerm', $this->GetOption('searchTerm'));
        Theme::Set('fadeInterval', $this->GetOption('fadeInterval'));
        Theme::Set('speedInterval', $this->GetOption('speedInterval'));
        Theme::Set('updateInterval', $this->GetOption('updateInterval'));
        Theme::Set('historySize', $this->GetOption('historySize'));
        $twitter = $this->GetOption('twitter');
        $identica = $this->GetOption('identica');
        
        // Is the transparency option set?
        if ($twitter)
            Theme::Set('twitter_checked', ' checked');

        if ($identica)
            Theme::Set('identica_checked', ' checked');

        // Get Raw
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        Debug::LogEntry('audit', 'Raw XML returned: ' . $this->GetRaw());

        $templateNodes = $rawXml->getElementsByTagName('template');
        $templateNode = $templateNodes->item(0);
        Theme::Set('template', $templateNode->nodeValue);

        $nocontentNodes	= $rawXml->getElementsByTagName('nocontent');
        $nocontentNode = $nocontentNodes->item(0);
        Theme::Set('nocontent', $nocontentNode->nodeValue);

        // Duration
        Theme::Set('duration', $this->duration);
        Theme::Set('is_duration_enabled', ($this->auth->modifyPermissions) ? '' : ' readonly');
        
        //Output the form
        $form = Theme::RenderReturn('media_form_microblog_edit');

        $this->response->html = $form;
        $this->response->dialogTitle = __('Edit MicroBlog');
        $this->response->dialogClass = __('modal-big');
        $this->response->callBack = 'microblog_callback';
        $this->response->AddButton(__('Help'), 'XiboHelpRender("index.php?p=help&q=Display&Topic=Microblog&Category=Media")');

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
        $url 		= "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

        //Other properties
        $searchTerm	= Kit::GetParam('searchTerm', _POST, _STRING);
        $duration	= Kit::GetParam('duration', _POST, _INT, 0);
        $fadeInterval   = Kit::GetParam('fadeInterval', _POST, _INT);
        $speedInterval  = Kit::GetParam('speedInterval', _POST, _INT);
        $updateInterval = Kit::GetParam('updateInterval', _POST, _INT);
        $historySize    = Kit::GetParam('historySize', _POST, _INT);
        $twitter        = Kit::GetParam('twitter', _POST, _CHECKBOX, 'off');
        $identica       = Kit::GetParam('identica', _POST, _CHECKBOX, 'off');
        $template       = Kit::GetParam('template', _POST, _HTMLSTRING);
        $nocontent      = Kit::GetParam('nocontent', _POST, _HTMLSTRING);

        // Validation
        if ($duration == 0)
            $this->response->Error('You must enter a duration.', true);

        if ($template == '')
            $this->response->Error('You must enter a Message Template.', true);

        // Required Attributes
        $this->mediaid	= md5(uniqid());
        $this->duration = $duration;

        // Any Options
        $this->SetOption('searchTerm', $searchTerm);
        $this->SetOption('fadeInterval', $fadeInterval);
        $this->SetOption('speedInterval', $speedInterval);
        $this->SetOption('updateInterval', $updateInterval);
        $this->SetOption('historySize', $historySize);
        $this->SetOption('twitter', $twitter);
        $this->SetOption('identica', $identica);

        $this->SetRaw('<template><![CDATA[' . $template . ']]></template><nocontent><![CDATA[' . $nocontent . ']]></nocontent>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'microblog');

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

        $layoutid 	= $this->layoutid;
        $regionid 	= $this->regionid;
        $mediaid	= $this->mediaid;
        $url 		= "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        //Other properties
        $searchTerm	= Kit::GetParam('searchTerm', _POST, _STRING);
        $fadeInterval   = Kit::GetParam('fadeInterval', _POST, _INT);
        $speedInterval  = Kit::GetParam('speedInterval', _POST, _INT);
        $updateInterval = Kit::GetParam('updateInterval', _POST, _INT);
        $historySize    = Kit::GetParam('historySize', _POST, _INT);
        $twitter        = Kit::GetParam('twitter', _POST, _CHECKBOX, 'off');
        $identica       = Kit::GetParam('identica', _POST, _CHECKBOX, 'off');
        $template       = Kit::GetParam('template', _POST, _HTMLSTRING);
        $nocontent      = Kit::GetParam('nocontent', _POST, _HTMLSTRING);

        // Validation
        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);
            
        if ($this->duration == 0)
            $this->response->Error('You must enter a duration.', true);

        if ($template == '')
            $this->response->Error('You must enter a Message Template.', true);

        // Any Options
        $this->SetOption('searchTerm', $searchTerm);
        $this->SetOption('fadeInterval', $fadeInterval);
        $this->SetOption('speedInterval', $speedInterval);
        $this->SetOption('updateInterval', $updateInterval);
        $this->SetOption('historySize', $historySize);
        $this->SetOption('twitter', $twitter);
        $this->SetOption('identica', $identica);

        $this->SetRaw('<template><![CDATA[' . $template . ']]></template><nocontent><![CDATA[' . $nocontent . ']]></nocontent>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        if (!$this->UpdateRegion())
            trigger_error($this->GetErrorMessage(), E_USER_ERROR);

        //Set this as the session information
        setSession('content', 'type', 'microblog');

	if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }

        return $this->response;
    }
    
    public function IsValid() {
        // Error state
        return 3;
    }
}

?>
