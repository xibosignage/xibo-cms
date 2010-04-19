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
class ticker extends Module
{
	// Custom Media information
	private $text;
	private $direction;
	
	public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '')
	{
		// Must set the type of the class
		$this->type = 'ticker';
	
		// Must call the parent class	
		parent::__construct($db, $user, $mediaid, $layoutid, $regionid);
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
		
		$direction_list = listcontent("none|None,left|Left,right|Right,up|Up,down|Down,single|Single", "direction");
		
		$form = <<<FORM
		<form class="XiboTextForm" method="post" action="index.php?p=module&mod=ticker&q=Exec&method=AddMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" id="iRegionId" name="regionid" value="$regionid">
			<table>
				<tr>
					<td><label for="uri" title="The Link for the RSS feed">Link<span class="required">*</span></label></td>
					<td><input id="uri" name="uri" value="http://" type="text"></td>
					<td><label for="copyright" title="Copyright information to display as the last item in this feed.">Copyright</label></td>
					<td><input id="copyright" name="copyright" type="text" /></td>
				</td>
				<tr>
		    		<td><label for="direction" title="The Direction this text should move, if any">Direction<span class="required">*</span></label></td>
		    		<td>$direction_list</td>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" type="text"></td>		
				</tr>
				<tr>
		    		<td><label for="scrollSpeed" title="The scroll speed of the ticker.">Scroll Speed<span class="required">*</span> (lower is faster)</label></td>
		    		<td><input id="scrollSpeed" name="scrollSpeed" type="text" value="30"></td>		
		    		<td><label for="updateInterval" title="The Interval at which the client should cache the feed.">Update Interval (mins)<span class="required">*</span></label></td>
		    		<td><input id="updateInterval" name="updateInterval" type="text" value="360"></td>
				</tr>
				<tr>
					<td colspan="4">
						<textarea id="ta_text" name="ta_text">
							[Title] - [Date] - [Description]
						</textarea>
					</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input id="btnSave" type="submit" value="Save"  />
						<input class="XiboFormButton" id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" value="Cancel" />
					</td>
				</tr>
			</table>
		</form>
FORM;

		$this->response->html 		= $form;
		$this->response->callBack 	= 'text_callback';
		$this->response->dialogTitle = 'Add New Ticker';

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
		
		$direction		= $this->GetOption('direction');
		$copyright		= $this->GetOption('copyright');
		$scrollSpeed 	= $this->GetOption('scrollSpeed');
		$updateInterval = $this->GetOption('updateInterval');
		$uri			= urldecode($this->GetOption('uri'));
		
		// Get the text out of RAW
		$rawXml = new DOMDocument();
		$rawXml->loadXML($this->GetRaw());
		
		Debug::LogEntry($db, 'audit', 'Raw XML returned: ' . $this->GetRaw());
		
		// Get the Text Node out of this
		$textNodes 	= $rawXml->getElementsByTagName('template');
		$textNode 	= $textNodes->item(0);
		$text 		= $textNode->nodeValue;
		
		$direction_list = listcontent("none|None,left|Left,right|Right,up|Up,down|Down,single|Single", "direction", $direction);
		
		//Output the form
		$form = <<<FORM
		<form class="XiboTextForm" method="post" action="index.php?p=module&mod=ticker&q=Exec&method=EditMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" id="iRegionId" name="regionid" value="$regionid">
			<table>
				<tr>
					<td><label for="uri" title="The Link for the RSS feed">Link<span class="required">*</span></label></td>
					<td><input id="uri" name="uri" value="$uri" type="text"></td>
					<td><label for="copyright" title="Copyright information to display as the last item in this feed.">Copyright</label></td>
					<td><input id="copyright" name="copyright" type="text" value="$copyright" /></td>
				</td>
				<tr>
		    		<td><label for="direction" title="The Direction this text should move, if any">Direction<span class="required">*</span></label></td>
		    		<td>$direction_list</td>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" value="$this->duration" type="text"></td>		
				</tr>
				<tr>
		    		<td><label for="scrollSpeed" title="The scroll speed of the ticker.">Scroll Speed<span class="required">*</span> (lower is faster)</label></td>
		    		<td><input id="scrollSpeed" name="scrollSpeed" type="text" value="$scrollSpeed"></td>		
		    		<td><label for="updateInterval" title="The Interval at which the client should cache the feed.">Update Interval (mins)<span class="required">*</span></label></td>
		    		<td><input id="updateInterval" name="updateInterval" type="text" value="$updateInterval"></td>
				</tr>
				<tr>
					<td colspan="4">
						<textarea id="ta_text" name="ta_text">$text</textarea>
					</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input id="btnSave" type="submit" value="Save"  />
						<input class="XiboFormButton" id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" value="Cancel" />
					</td>
				</tr>
			</table>
		</form>
FORM;
		
		$this->response->html 		= $form;
		$this->response->callBack 	= 'text_callback';
		$this->response->dialogTitle = 'Edit Ticker.';

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
		$direction	  = Kit::GetParam('direction', _POST, _WORD, 'none');
		$duration	  = Kit::GetParam('duration', _POST, _INT, 0);
		$scrollSpeed  = Kit::GetParam('scrollSpeed', _POST, _INT, 30);
		$updateInterval = Kit::GetParam('updateInterval', _POST, _INT, 360);
		$text		  = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
		$copyright	  = Kit::GetParam('copyright', _POST, _STRING);
		
		$url 		  = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
						
		//validation
		if ($text == '')
		{
			$this->response->SetError('Please enter some text');
			$this->response->keepOpen = true;
			return $this->response;
		}
		
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
		$this->SetOption('direction', $direction);
		$this->SetOption('copyright', $copyright);
		$this->SetOption('scrollSpeed', $scrollSpeed);
		$this->SetOption('updateInterval', $updateInterval);
		$this->SetOption('uri', $uri);

		$this->SetRaw('<template><![CDATA[' . $text . ']]></template>');
		
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
		$uri		  = Kit::GetParam('uri', _POST, _URI);
		$direction	  = Kit::GetParam('direction', _POST, _WORD, 'none');
		$duration	  = Kit::GetParam('duration', _POST, _INT, 0);
		$text		  = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
		$scrollSpeed  = Kit::GetParam('scrollSpeed', _POST, _INT, 30);
		$updateInterval = Kit::GetParam('updateInterval', _POST, _INT, 360);
		$copyright	  = Kit::GetParam('copyright', _POST, _STRING);
		
		$url 		  = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
		
		//validation
		if ($text == '')
		{
			$this->response->SetError('Please enter some text');
			$this->response->keepOpen = true;
			return $this->response;
		}
		
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
		$this->duration = $duration;
		
		// Any Options
		$this->SetOption('direction', $direction);
		$this->SetOption('copyright', $copyright);
		$this->SetOption('scrollSpeed', $scrollSpeed);
		$this->SetOption('updateInterval', $updateInterval);
		$this->SetOption('uri', $uri);

		$this->SetRaw('<template><![CDATA[' . $text . ']]></template>');
		
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
            $textNodes 	= $rawXml->getElementsByTagName('template');
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