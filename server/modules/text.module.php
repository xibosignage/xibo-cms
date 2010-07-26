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
class text extends Module
{
	// Custom Media information
	private $text;
	private $direction;
	
	public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
	{
		// Must set the type of the class
		$this->type = 'text';
	
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
		<form id="ModuleForm" class="XiboTextForm" method="post" action="index.php?p=module&mod=text&q=Exec&method=AddMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" id="iRegionId" name="regionid" value="$regionid">
			<table>
				<tr>
		    		<td><label for="direction" title="The Direction this text should move, if any">Direction<span class="required">*</span></label></td>
		    		<td>$direction_list</td>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" type="text"></td>		
				</tr>
				<tr>
					<td><label for="scrollSpeed" title="The scroll speed of the ticker.">Scroll Speed<span class="required">*</span> (lower is faster)</label></td>
		    		<td><input id="scrollSpeed" name="scrollSpeed" type="text" value="30"></td>
				</tr>
				<tr>
					<td colspan="4">
						<textarea id="ta_text" name="ta_text"></textarea>
					</td>
				</tr>
				<tr>
					<td colspan="4"><input type="checkbox" id="termsOfService" name="termsOfService" checked="checked"><label for="termsOfService">I certify I have the right to publish this media and that this media does not violate the terms of service stated in the <a href="http://www.xibo.org.uk/manual/index.php?p=content/license/termsofservice">manual</a>.</label></td>
				</tr>
			</table>
		</form>
FORM;

            $this->response->html 		= $form;
            $this->response->callBack 	= 'text_callback';
            $this->response->dialogTitle    = 'Add new Text item';
            $this->response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=layout&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
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
		
		$layoutid		= $this->layoutid;
		$regionid		= $this->regionid;
		$mediaid  		= $this->mediaid;
		
		$direction		= $this->GetOption('direction');
		$scrollSpeed 	= $this->GetOption('scrollSpeed');
		
		// Get the text out of RAW
		$rawXml = new DOMDocument();
		$rawXml->loadXML($this->GetRaw());
		
		Debug::LogEntry($db, 'audit', 'Raw XML returned: ' . $this->GetRaw());
		
		// Get the Text Node out of this
		$textNodes 	= $rawXml->getElementsByTagName('text');
		$textNode 	= $textNodes->item(0);
		$text 		= $textNode->nodeValue;
		
		$direction_list = listcontent("none|None,left|Left,right|Right,up|Up,down|Down", "direction", $direction);
		
		//Output the form
		$form = <<<FORM
		<form id="ModuleForm" class="XiboTextForm" method="post" action="index.php?p=module&mod=text&q=Exec&method=EditMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" id="iRegionId" name="regionid" value="$regionid">
			<table>
				<tr>
		    		<td><label for="direction" title="The Direction this text should move, if any">Direction<span class="required">*</span></label></td>
		    		<td>$direction_list</td>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" value="$this->duration" type="text"></td>		
				</tr>
				<tr>
					<td><label for="scrollSpeed" title="The scroll speed of the ticker.">Scroll Speed<span class="required">*</span> (lower is faster)</label></td>
		    		<td><input id="scrollSpeed" name="scrollSpeed" type="text" value="$scrollSpeed"></td>
				</tr>
				<tr>
					<td colspan="4">
						<textarea id="ta_text" name="ta_text">$text</textarea>
					</td>
				</tr>
				<tr>
					<td colspan="4"><input type="checkbox" id="termsOfService" name="termsOfService" checked="checked"><label for="termsOfService">I certify I have the right to publish this media and that this media does not violate the terms of service stated in the <a href="http://www.xibo.org.uk/manual/index.php?p=content/license/termsofservice">manual</a>.</label></td>
				</tr>
			</table>
		</form>
FORM;
		
		$this->response->html 		= $form;
		$this->response->callBack 	= 'text_callback';
		$this->response->dialogTitle = 'Edit Text item';
                $this->response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=layout&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
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
		$scrollSpeed  = Kit::GetParam('scrollSpeed', _POST, _INT, 30);
		
		$url 		  = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
						
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
		$this->SetRaw('<text><![CDATA[' . $text . ']]></text>');
		
		// Should have built the media object entirely by this time
		// This saves the Media Object to the Region
		$this->UpdateRegion();
		
		//Set this as the session information
		setSession('content', 'type', 'text');
		
		// We want to load a new form
		$this->response->loadForm	= true;
		$this->response->loadFormUri= $url;
		
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
		
		//Other properties
		$direction	  = Kit::GetParam('direction', _POST, _WORD, 'none');
		$duration	  = Kit::GetParam('duration', _POST, _INT, 0);
		$text		  = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
		$scrollSpeed  = Kit::GetParam('scrollSpeed', _POST, _INT, 30);
		
		Debug::LogEntry($db, 'audit', 'Text received: ' . $text);
		
		$url 		  = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
		
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
		$this->duration = $duration;
		
		// Any Options
		$this->SetOption('direction', $direction);
		$this->SetOption('scrollSpeed', $scrollSpeed);
		$this->SetRaw('<text><![CDATA[' . $text . ']]></text>');
		
		// Should have built the media object entirely by this time
		// This saves the Media Object to the Region
		$this->UpdateRegion();
		
		//Set this as the session information
		setSession('content', 'type', 'text');
		
		// We want to load a new form
		$this->response->loadForm	= true;
		$this->response->loadFormUri= $url;
		
		return $this->response;		
	}

        public function Preview($width, $height)
        {
            $regionid   = $this->regionid;
            $direction  = $this->GetOption('direction');

            // Get the text out of RAW
            $rawXml = new DOMDocument();
            $rawXml->loadXML($this->GetRaw());

            // Get the Text Node out of this
            $textNodes 	= $rawXml->getElementsByTagName('text');
            $textNode 	= $textNodes->item(0);
            $text 	= $textNode->nodeValue;

            $textId 	= $regionid.'_text';
            $innerId 	= $regionid.'_innerText';
            $timerId	= $regionid.'_timer';
            $widthPx	= $width.'px';
            $heightPx	= $height.'px';

            $textWrap = '';
            if ($direction == "left" || $direction == "right") $textWrap = "white-space:nowrap;";

            //Show the contents of text accordingly
            $return = <<<END
            <div id="$textId" style="position:relative; overflow:hidden ;width:$widthPx; height:$heightPx; font-size: 1em;">
                <div id="$innerId" style="position:absolute; left: 0px; top: 0px; $textWrap">
                    <div class="article">
                            $text
                    </div>
                </div>
            </div>
END;
            return $return;
        }
}
?>