<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
class webpage extends Module
{
	
	public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
	{
		// Must set the type of the class
		$this->type = 'webpage';
                $this->displayType = 'Webpage';
	
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
		
		$form = <<<FORM
		<form id="ModuleForm" class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=AddMedia">
                    <input type="hidden" name="layoutid" value="$layoutid">
                    <input type="hidden" id="iRegionId" name="regionid" value="$regionid">
                    <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
                    <table>
                        <tr>
                            <td><label for="uri" title="The Location (URL) of the webpage. E.g. http://www.xibo.org.uk">Link<span class="required">*</span></label></td>
                            <td><input id="uri" name="uri" type="text"></td>
                        </tr>
                        <tr>
                            <td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration (s)<span class="required">*</span></label></td>
                            <td><input id="duration" name="duration" type="text"></td>
                        </tr>
                        <tr>
                            <td><label for="scaling" title="">Scale Percentage</label></td>
                            <td><input id="scaling" name="scaling" type="text" /></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <input id="transparency" name="transparency" type="checkbox">
                                <label for="transparency" title="Make webpage background transparent?">Background transparent? (python only)</label>
                            </td>
                        </tr>
                    </table>
		</form>
FORM;

            $this->response->html 		= $form;
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=layout&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
            $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');
            $this->response->dialogTitle = __('Add Webpage');
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
		
		$direction	= $this->GetOption('direction');
		$copyright	= $this->GetOption('copyright');
		$scaling	= $this->GetOption('scaling');
		$transparency	= $this->GetOption('transparency');
                $transparencyChecked = '';
		$uri		= urldecode($this->GetOption('uri'));

                // Is the transparency option set?
                if ($transparency)
                    $transparencyChecked = 'checked';
		
		$direction_list = listcontent("none|None,left|Left,right|Right,up|Up,down|Down", "direction", $direction);

        $durationFieldEnabled = ($this->auth->modifyPermissions) ? '' : ' readonly';
		
		//Output the form
		$form = <<<FORM
		<form id="ModuleForm" class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=EditMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" id="iRegionId" name="regionid" value="$regionid">
                        <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
			<table>
				<tr>
		    		<td><label for="uri" title="The Location (URL) of the webpage. E.g. http://www.xibo.org.uk">Link<span class="required">*</span></label></td>
		    		<td><input id="uri" name="uri" value="$uri" type="text"></td>
				</tr>
				<tr>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed (may be overridden on each layout)">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" value="$this->duration" type="text" $durationFieldEnabled></td>
				</tr>
                        <tr>
                            <td><label for="scaling" title="">Scale Percentage</label></td>
                            <td><input id="scaling" name="scaling" type="text" value="$scaling"/></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <input id="transparency" name="transparency" type="checkbox" $transparencyChecked>
                                <label for="transparency" title="Make webpage background transparent?">Background transparency (python only)</label>
                            </td>
                        </tr>
			</table>
		</form>
FORM;
		
            $this->response->html 		= $form;
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=layout&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
            $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');
            $this->response->dialogTitle = __('Edit Webpage');
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
		$db 		=& $this->db;
		
		$layoutid 	= $this->layoutid;
		$regionid 	= $this->regionid;
		$mediaid	= $this->mediaid;
		
		//Other properties
		$uri		  = Kit::GetParam('uri', _POST, _URI);
		$duration	  = Kit::GetParam('duration', _POST, _INT, 0);
                $scaling	  = Kit::GetParam('scaling', _POST, _INT, 100);
		$transparency     = Kit::GetParam('transparency', _POST, _CHECKBOX, 'off');
		
		$url 		  = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
						
		//Validate the URL?
		if ($uri == "" || $uri == "http://")
		{
			$this->response->SetError('Please enter a Link for this Ticker');
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
		$this->SetOption('uri', $uri);
                $this->SetOption('scaling', $scaling);
                $this->SetOption('transparency', $transparency);


		// Should have built the media object entirely by this time
		// This saves the Media Object to the Region
		$this->UpdateRegion();
		
		//Set this as the session information
		setSession('content', 'type', 'webpage');
		
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

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }
		
		//Other properties
		$uri		  = Kit::GetParam('uri', _POST, _URI);
                $scaling	  = Kit::GetParam('scaling', _POST, _INT, 100);
		$transparency     = Kit::GetParam('transparency', _POST, _CHECKBOX, 'off');
        
        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

	$url = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
						
		//Validate the URL?
		if ($uri == "" || $uri == "http://")
		{
			$this->response->SetError('Please enter a Link for this Ticker');
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
		$this->SetOption('uri', $uri);
                $this->SetOption('scaling', $scaling);
                $this->SetOption('transparency', $transparency);

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
}

?>