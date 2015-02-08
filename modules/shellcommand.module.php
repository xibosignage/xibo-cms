<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012 Daniel Garner
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
class shellcommand extends Module
{
	
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type = 'shellcommand';

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
        $db =& $this->db;
        $user =& $this->user;
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
        
        $formFields = array();
        
        $formFields[] = FormManager::AddText('windowsCommand', __('Windows Command'), NULL, 
            __('Enter a Windows Command Line compatible command'), 'w');
        
        $formFields[] = FormManager::AddText('linuxCommand', __('Android / Linux Command'), NULL, 
            __('Enter an Android / Linux Command Line compatible command'), 'l');

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
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');
        $this->response->dialogTitle = __('Add Shell Command');
        $this->response->dialogSize 	= true;
        $this->response->dialogWidth 	= '450px';
        $this->response->dialogHeight 	= '250px';

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
        
        $formFields = array();
        
        $formFields[] = FormManager::AddText('windowsCommand', __('Windows Command'), htmlentities(urldecode($this->GetOption('windowsCommand'))), 
            __('Enter a Windows Command Line compatible command'), 'w');
        
        $formFields[] = FormManager::AddText('linuxCommand', __('Android / Linux Command'), htmlentities(urldecode($this->GetOption('linuxCommand'))), 
            __('Enter an Android / Linux Command Line compatible command'), 'l');

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
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');
        $this->response->dialogTitle = __('Edit Shell Command');
        $this->response->dialogSize 	= true;
        $this->response->dialogWidth 	= '450px';
        $this->response->dialogHeight 	= '250px';

        return $this->response;
    }
	
    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $this->response = new ResponseManager();
        $db =& $this->db;

        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

        // Required Attributes
        $this->mediaid = md5(uniqid());
        $this->duration = 1;
        
        $windowsCommand = Kit::GetParam('windowsCommand', _POST, _STRING);
        $linuxCommand = Kit::GetParam('linuxCommand', _POST, _STRING);

        if ($windowsCommand == '' && $linuxCommand == '')
        {
            $this->response->SetError('You must enter a command');
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Any Options (we need to encode shell commands, as they sit on the options rather than the raw
        $this->SetOption('windowsCommand', urlencode($windowsCommand));
        $this->SetOption('linuxCommand', urlencode($linuxCommand));

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Set this as the session information
        setSession('content', 'type', 'shellcommand');

        if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
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

        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        $windowsCommand = Kit::GetParam('windowsCommand', _POST, _STRING);
        $linuxCommand = Kit::GetParam('linuxCommand', _POST, _STRING);

        if ($windowsCommand == '' && $linuxCommand == '')
        {
            $this->response->SetError('You must enter a command');
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Any Options
        $this->duration = 1;
        $this->SetOption('windowsCommand', urlencode($windowsCommand));
        $this->SetOption('linuxCommand', urlencode($linuxCommand));

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Set this as the session information
        setSession('content', 'type', 'shellcommand');

        if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
        }

        return $this->response;
    }

    public function Preview($width, $height, $scaleOverride = 0)
    {
        if ($this->previewEnabled == 0)
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
    
    
    public function IsValid() {
        // Client dependant
        return 2;
    }
}
?>