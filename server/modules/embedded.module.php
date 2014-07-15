<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2012 Daniel Garner
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
class embedded extends Module
{
	
	public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
	{
		// Must set the type of the class
		$this->type = 'embedded';
                $this->displayType = 'Embedded HTML';
	
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

		Theme::Set('default_head_content', '
<script type="text/javascript">
function EmbedInit()
{
	// Init will be called when this page is loaded in the client.
	
	return;
}
</script>');

		$form = Theme::RenderReturn('media_form_embedded_add');

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->html 			= $form;
        $this->response->dialogTitle 	= 'Add Embedded HTML';
        $this->response->dialogSize 	= true;
        $this->response->dialogWidth 	= '650px';
        $this->response->dialogHeight 	= '450px';
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

        // Can this user edit?
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this media.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $mediaid . '">');
		Theme::Set('name', $this->GetOption('name'));

		// Get the embedded HTML out of RAW
		$rawXml = new DOMDocument();
		$rawXml->loadXML($this->GetRaw());
		
		Debug::LogEntry('audit', 'Raw XML returned: ' . $this->GetRaw());
		
		// Get the HTML Node out of this
		$textNodes 	= $rawXml->getElementsByTagName('embedHtml');
		$textNode 	= $textNodes->item(0);
		Theme::Set('embedHtml', $textNode->nodeValue);
		
		$textNodes 	= $rawXml->getElementsByTagName('embedScript');
		$textNode 	= $textNodes->item(0);
		Theme::Set('embedScript', $textNode->nodeValue);

		Theme::Set('duration', $this->duration);
        Theme::Set('durationFieldEnabled', (($this->auth->modifyPermissions) ? '' : ' readonly'));

		// Is the transparency option set?
		if ($this->GetOption('transparency'))
            Theme::Set('transparency_checked', 'checked');
		
		//Output the form
		$form = Theme::RenderReturn('media_form_embedded_edit');

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

		$this->response->html 			= $form;
		$this->response->dialogTitle 	= 'Edit Embedded HTML';
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '650px';
		$this->response->dialogHeight 	= '450px';
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
		$embedHtml	  = Kit::GetParam('embedHtml', _POST, _HTMLSTRING);
		$embedScript  = Kit::GetParam('embedScript', _POST, _HTMLSTRING);
		$duration	  = Kit::GetParam('duration', _POST, _INT, 0);
		$transparency = Kit::GetParam('transparency', _POST, _CHECKBOX, 'off');
		$name = Kit::GetParam('name', _POST, _STRING);
		
		$url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
						
		//Validate the URL?
		if ($embedHtml == "")
		{
			$this->response->SetError('Please enter some HTML to embed.');
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
		$this->mediaid = md5(uniqid());
		$this->duration = $duration;
		$this->SetOption('transparency', $transparency);
		$this->SetOption('name', $name);
		
		// Any Options
		$this->SetRaw('<embedHtml><![CDATA[' . $embedHtml . ']]></embedHtml><embedScript><![CDATA[' . $embedScript . ']]></embedScript>');

		// Should have built the media object entirely by this time
		// This saves the Media Object to the Region
		$this->UpdateRegion();
		
		//Set this as the session information
		setSession('content', 'type', $this->type);
		
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
		$embedHtml	  = Kit::GetParam('embedHtml', _POST, _HTMLSTRING);
		$embedScript  = Kit::GetParam('embedScript', _POST, _HTMLSTRING);
		$transparency = Kit::GetParam('transparency', _POST, _CHECKBOX, 'off');
		$name = Kit::GetParam('name', _POST, _STRING);

		$this->SetOption('transparency', $transparency);
		$this->SetOption('name', $name);

        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);
		
		$url 		  = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
						
		// Validate the URL?
		if ($embedHtml == "")
		{
			$this->response->SetError('Please enter some HTML to embed.');
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
		$this->SetRaw('<embedHtml><![CDATA[' . $embedHtml . ']]></embedHtml><embedScript><![CDATA[' . $embedScript . ']]></embedScript>');

		// Should have built the media object entirely by this time
		// This saves the Media Object to the Region
		$this->UpdateRegion();
		
		//Set this as the session information
		setSession('content', 'type', $this->type);
		
	if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }
		
		return $this->response;	
	}
	
	public function GetName() {
		return $this->GetOption('name');
	}
	
    public function IsValid() {
    	// Can't be sure because the client does the rendering
    	return 2;
    }
   
   public function GetResource($display = 0) {
        // Behave exactly like the client.

        // Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplateForGetResource.html');

        // Get the text out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        // Get the Text Node
        $html = $rawXml->getElementsByTagName('embedHtml');
        $html = $html->item(0);
        $html = $html->nodeValue;

        // Get the Script
        $script = $rawXml->getElementsByTagName('embedScript');
        $script = $script->item(0);
        $script = $script->nodeValue;

        // Set some options
        $options = array(
            'originalWidth' => $this->width,
            'originalHeight' => $this->height,
            'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
            'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
        );

        // Add an options variable with some useful information for scaling
        $script .= '<script type="text/javascript">';
        $script .= '   var options = ' . json_encode($options) . ';';
        $script .= '</script>';
        
        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $script, $template);

        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', $html, $template);

        return $template;
    }
}

?>
