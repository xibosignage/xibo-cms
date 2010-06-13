<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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
		
		$form = <<<FORM
		<form class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=AddMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" id="iRegionId" name="regionid" value="$regionid">
			<table>
				<tr>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" type="text"></td>	
				</tr>
				<tr>
		    		<td colspan="2">
						<label for="embedHtml" title="The HTML you want to Embed in this Layout.">Embed HTML<span class="required">*</span></label><br />
<textarea id="embedHtml" name="embedHtml">

</textarea>
					</td>
				</tr>
				<tr>
		    		<td colspan="2">
						<label for="embedScript" title="The JavaScript you want to Embed in this Layout.">Embed Script<span class="required">*</span></label><br />
<textarea id="embedScript" name="embedScript">
<script type="text/javascript">
function EmbedInit()
{
	// Init will be called when this page is loaded in the client.
	
	return;
}
</script>
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

		$this->response->html 			= $form;
		$this->response->dialogTitle 	= 'Add Embedded HTML';
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '650px';
		$this->response->dialogHeight 	= '450px';

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
		
		// Get the embedded HTML out of RAW
		$rawXml = new DOMDocument();
		$rawXml->loadXML($this->GetRaw());
		
		Debug::LogEntry($db, 'audit', 'Raw XML returned: ' . $this->GetRaw());
		
		// Get the HTML Node out of this
		$textNodes 	= $rawXml->getElementsByTagName('embedHtml');
		$textNode 	= $textNodes->item(0);
		$embedHtml	= $textNode->nodeValue;
		
		$textNodes 	= $rawXml->getElementsByTagName('embedScript');
		$textNode 	= $textNodes->item(0);
		$embedScript= $textNode->nodeValue;
		
		//Output the form
		$form = <<<FORM
		<form class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=EditMedia">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" id="iRegionId" name="regionid" value="$regionid">
			<table>
				<tr>
		    		<td><label for="duration" title="The duration in seconds this webpage should be displayed (may be overridden on each layout)">Duration<span class="required">*</span></label></td>
		    		<td><input id="duration" name="duration" value="$this->duration" type="text"></td>		
				</tr>
				<tr>
		    		<td colspan="2">
						<label for="embedHtml" title="The HTML you want to Embed in this Layout.">Embed HTML<span class="required">*</span></label><br />
		    			<textarea id="embedHtml" name="embedHtml">$embedHtml</textarea>
					</td>
				</tr>
				<tr>
		    		<td colspan="2">
						<label for="embedScript" title="The JavaScript you want to Embed in this Layout.">Embed Script<span class="required">*</span></label><br />
						<textarea id="embedScript" name="embedScript">$embedScript</textarea>
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
		
		$this->response->html 			= $form;
		$this->response->dialogTitle 	= 'Edit Embedded HTML';
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '650px';
		$this->response->dialogHeight 	= '450px';

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
		
		$url 		  = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
						
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
		$this->mediaid	= md5(uniqid());
		$this->duration = $duration;
		
		// Any Options
		$this->SetRaw('<embedHtml><![CDATA[' . $embedHtml . ']]></embedHtml><embedScript><![CDATA[' . $embedScript . ']]></embedScript>');

		// Should have built the media object entirely by this time
		// This saves the Media Object to the Region
		$this->UpdateRegion();
		
		//Set this as the session information
		setSession('content', 'type', $this->type);
		
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
		$embedHtml	  = Kit::GetParam('embedHtml', _POST, _HTMLSTRING);
		$embedScript  = Kit::GetParam('embedScript', _POST, _HTMLSTRING);
		$duration	  = Kit::GetParam('duration', _POST, _INT, 0);
		
		$url 		  = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
						
		// Validate the URL?
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
		$this->duration = $duration;
		
		// Any Options
		$this->SetRaw('<embedHtml><![CDATA[' . $embedHtml . ']]></embedHtml><embedScript><![CDATA[' . $embedScript . ']]></embedScript>');

		// Should have built the media object entirely by this time
		// This saves the Media Object to the Region
		$this->UpdateRegion();
		
		//Set this as the session information
		setSession('content', 'type', $this->type);
		
		// We want to load a new form
		$this->response->loadForm	= true;
		$this->response->loadFormUri= $url;
		
		return $this->response;	
	}
}

?>