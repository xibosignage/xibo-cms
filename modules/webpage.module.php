<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
		
		// Direction Options
        $directionOptions = array(
            array('directionid' => 'none', 'direction' => __('None')), 
            array('directionid' => 'left', 'direction' => __('Left')), 
            array('directionid' => 'right', 'direction' => __('Right')), 
            array('directionid' => 'up', 'direction' => __('Up')), 
            array('directionid' => 'down', 'direction' => __('Down'))
        );
        Theme::Set('direction_field_list', $directionOptions);

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');

        $this->response->html = Theme::RenderReturn('media_form_webpage_add');

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
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

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $mediaid . '">');
        	
		// Direction Options
        $directionOptions = array(
            array('directionid' => 'none', 'direction' => __('None')), 
            array('directionid' => 'left', 'direction' => __('Left')), 
            array('directionid' => 'right', 'direction' => __('Right')), 
            array('directionid' => 'up', 'direction' => __('Up')), 
            array('directionid' => 'down', 'direction' => __('Down'))
        );
        Theme::Set('direction_field_list', $directionOptions);

		Theme::Set('direction', $this->GetOption('direction'));
		Theme::Set('copyright', $this->GetOption('copyright'));
		Theme::Set('scaling', $this->GetOption('scaling'));
		Theme::Set('uri', urldecode($this->GetOption('uri')));
        Theme::Set('offsetLeft', $this->GetOption('offsetLeft'));
        Theme::Set('offsetTop', $this->GetOption('offsetTop'));

        // Is the transparency option set?		
		if ($this->GetOption('transparency'))
            Theme::Set('transparency_checked', 'checked');

        Theme::Set('duration', $this->duration);
        Theme::Set('is_duration_enabled', ($this->auth->modifyPermissions) ? '' : ' readonly');

        $this->response->html = Theme::RenderReturn('media_form_webpage_edit');
		
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
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
                $offsetLeft = Kit::GetParam('offsetLeft', _POST, _INT);
                $offsetTop = Kit::GetParam('offsetTop', _POST, _INT);
		
		$url 		  = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
						
		//Validate the URL?
		if ($uri == "" || $uri == "http://")
		{
			$this->response->SetError('Please enter a Link');
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
		$this->SetOption('xmds', true);
		$this->SetOption('uri', $uri);
                $this->SetOption('scaling', $scaling);
                $this->SetOption('transparency', $transparency);
                $this->SetOption('offsetLeft', $offsetLeft);
                $this->SetOption('offsetTop', $offsetTop);

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
                $offsetLeft = Kit::GetParam('offsetLeft', _POST, _INT);
                $offsetTop = Kit::GetParam('offsetTop', _POST, _INT);
        
        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

	$url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
						
		//Validate the URL?
		if ($uri == "" || $uri == "http://")
		{
			$this->response->SetError('Please enter a Link');
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
		$this->SetOption('uri', $uri);
                $this->SetOption('scaling', $scaling);
                $this->SetOption('transparency', $transparency);
                $this->SetOption('offsetLeft', $offsetLeft);
                $this->SetOption('offsetTop', $offsetTop);

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
     * Preview
     * @param <type> $width
     * @param <type> $height
     * @return <type>
     */
    public function Preview($width, $height) {
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

        return '<iframe scrolling="no" id="innerIframe" src="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=GetResource&raw=true&preview=true&scale_override=1&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '&width=' . $width . '&height=' . $height . '" width="' . $widthPx . '" height="' . $heightPx . '" style="border:0;"></iframe>';
    }

    public function GetResource($displayId = 0) {

    	// Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplateSimple.html');

        // Get some parameters
        $width = Kit::GetParam('width', _REQUEST, _DOUBLE);
        $height = Kit::GetParam('height', _REQUEST, _DOUBLE);
        $duration = $this->duration;

        // Work out the url
        $url = urldecode($this->GetOption('uri'));
        $url = (preg_match('/^' . preg_quote('http') . "/", $url)) ? $url : 'http://' . $url;

        $options = array(
        		'originalWidth' => $this->width,
        		'originalHeight' => $this->height,
        		'previewWidth' => $width,
        		'previewHeight' => $height,
        		'offsetTop' => $this->GetOption('offsetTop', 0),
        		'offsetLeft' => $this->GetOption('offsetLeft', 0),
        		'scale' => ($this->GetOption('scaling', 100) / 100),
        		'scale_override' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
        	);

        // Head Content
        $headContent = '<style>#iframe { border:0; }</style>';
        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        // Body content
        $output = '<div id="wrap"><iframe id="iframe" scrolling="no" src="' . $url . '"></iframe></div>';
        
        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', $output, $template);

        // After body content
    	$after_body  = '<script>' . file_get_contents('modules/preview/vendor/jquery-1.11.1.min.js') . '</script>';
        $after_body .= '<script>
        	var options = ' . json_encode($options) . '
        	' . file_get_contents('modules/preview/xibo-webpage-render.js') . '</script>';

        // Replace the After body Content
        $template = str_replace('<!--[[[AFTERBODYCONTENT]]]-->', $after_body, $template);

        return $template;
    }

    public function IsValid() {
    	// Can't be sure because the client does the rendering
    	return 2;
    }
}
?>
