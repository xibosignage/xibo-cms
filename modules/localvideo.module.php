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
class localvideo extends Module
{
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type = 'localvideo';
        $this->displayType = 'Local Video';

        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }
	
    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm()
    {
        $db =& $this->db;
        $user =& $this->user;

        // Would like to get the regions width / height
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $rWidth = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight = Kit::GetParam('rHeight', _REQUEST, _STRING);

        $msgDuration = __('Duration');
        $msgVideoPath = __('Video Path');

        $this->response->html = <<<FORM
            <form id="ModuleForm" class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=AddMedia">
                <input type="hidden" name="layoutid" value="$layoutid">
                <input type="hidden" id="iRegionId" name="regionid" value="$regionid">
                <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
                <table>
                    <tr>
                        <td><label for="uri" title="$msgVideoPath">$msgVideoPath<span class="required">*</span></label></td>
                        <td><input id="uri" name="uri" type="text" class="required"></td>
                    </tr>
                    <tr>
                        <td><label for="duration" title="$msgDuration">$msgDuration<span class="required">*</span></label></td>
                        <td><input id="duration" name="duration" type="text" class="numeric required"></td>
                    </tr>
                </table>
            </form>
FORM;

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');
        $this->response->dialogTitle = __('Add Local Video');
        $this->response->dialogSize 	= true;
        $this->response->dialogWidth 	= '350px';
        $this->response->dialogHeight 	= '250px';

        return $this->response;
    }
	
    /**
     * Return the Edit Form as HTML
     * @return
     */
    public function EditForm()
    {
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
		
        $direction = $this->GetOption('direction');
        $uri = urldecode($this->GetOption('uri'));
                
        $durationFieldEnabled = ($this->auth->modifyPermissions) ? '' : ' readonly';

        $msgDuration = __('Duration');
        $msgVideoPath = __('Video Path');

        $this->response->html = <<<FORM
            <form id="ModuleForm" class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=EditMedia">
                <input type="hidden" name="layoutid" value="$layoutid">
                <input type="hidden" id="iRegionId" name="regionid" value="$regionid">
                <input type="hidden" name="mediaid" value="$mediaid">
                <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
                <table>
                    <tr>
                        <td><label for="uri" title="$msgVideoPath">$msgVideoPath<span class="required">*</span></label></td>
                        <td><input id="uri" name="uri" value="$uri" type="text" class="required"></td>
                    </tr>
                    <tr>
                        <td><label for="duration" title="$msgDuration">$msgDuration<span class="required">*</span></label></td>
                        <td><input id="duration" name="duration" value="$this->duration" type="text" class="numeric required"></td>
                    </tr>
                </table>
            </form>
FORM;
		
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
        
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');
        $this->response->dialogTitle = __('Edit Local Video');
        $this->response->dialogSize 	= true;
        $this->response->dialogWidth 	= '350px';
        $this->response->dialogHeight 	= '250px';

        return $this->response;
    }
	
    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $db =& $this->db;

        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

        //Other properties
        $uri = Kit::GetParam('uri', _POST, _URI);
        $duration = Kit::GetParam('duration', _POST, _INT, 0);

        $url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

        // Validate the URL?
        if ($uri == "")
        {
            $this->response->SetError(__('Please enter a full path name giving the location of this video on the client'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        if ($duration < 0)
        {
            $this->response->SetError('You must enter a duration.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Required Attributes
        $this->mediaid = md5(uniqid());
        $this->duration = $duration;

        // Any Options
        $this->SetOption('uri', $uri);

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'localvideo');

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

        //Other properties
        $uri = Kit::GetParam('uri', _POST, _URI);

        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

        $url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

        //Validate the URL?
        if ($uri == "")
        {
            $this->response->SetError(__('Please enter a full path name giving the location of this video on the client'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        if ($this->duration < 0)
        {
            $this->response->SetError('You must enter a duration.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Any Options
        $this->SetOption('uri', $uri);

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'localvideo');

        if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }

        return $this->response;
    }
    
    public function IsValid() {
        // Client dependant
        return 2;
    }
}
?>