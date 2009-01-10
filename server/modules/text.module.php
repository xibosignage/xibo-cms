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
	//Media information
	private	$duration;
	private $text;
	private $direction;
	
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
		<form class="XiboTextForm" method="post" action="index.php?p=module&mod=text&q=Exec&method=AddMedia">
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
					<td colspan="4">
						<textarea id="ta_text" name="ta_text"></textarea>
					</td>
				</tr>
				<tr>
					<td colspan="4"><input type="checkbox" id="termsOfService" name="termsOfService" checked="checked"><label for="termsOfService">I certify I have the right to publish this media and that this media does not violate the terms of service stated in the <a href="http://www.xibo.org.uk/manual/index.php?p=content/license/termsofservice">manual</a>.</label></td>
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
		$this->response->dialogTitle = 'Add new Text item';

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
		
		$direction_list = listcontent("none|None,left|Left,right|Right,up|Up,down|Down", "direction", $this->direction);
		
		//Output the form
		$form = <<<FORM
		<form class="dialog_text_form" method="post" action="index.php?p=module&mod=text&q=EditMedia">
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
					<td colspan="4">
						<textarea id="ta_text" name="ta_text">$this->text</textarea>
					</td>
				</tr>
				<tr>
					<td colspan="4"><input type="checkbox" id="termsOfService" name="termsOfService" checked="checked"><label for="termsOfService">I certify I have the right to publish this media and that this media does not violate the terms of service stated in the <a href="http://www.xibo.org.uk/manual/index.php?p=content/license/termsofservice">manual</a>.</label></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input id="btnSave" type="submit" value="Save"  />
						<input id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" onclick="return init_button(this,'Region Options','',region_options_callback)" value="Cancel" />
						<input type="button" onclick="window.open('$this->help_link')" value="Help" />
					</td>
				</tr>
			</table>
		</form>
FORM;
		
		$this->response->html 		= $form;
		$this->response->callBack 	= 'text_callback';
		$this->response->dialogTitle = 'Edit Text item';

		return $this->response;		
	}
	
	/**
	 * Return the Delete Form as HTML
	 * @return 
	 */
	public function DeleteForm()
	{
		$db =& $this->db;
		
		//ajax request handler
		$response = new ResponseManager();
		
		//Parameters
		$layoutid 	= $this->layoutid;
		$regionid 	= $this->regionid;
		$mediaid	= $this->mediaid;

		//we can delete
		$form = <<<END
		<form class="dialog_form" method="post" action="index.php?p=module&mod=text&q=DeleteMedia">
			<input type="hidden" name="mediaid" value="$mediaid">
			<input type="hidden" name="layoutid" value="$layoutid">
			<input type="hidden" name="regionid" value="$regionid">
			<p>Are you sure you want to remove this webpage from Xibo? <span class="required">It will be lost</span>.</p>
			<input id="btnSave" type="submit" value="Yes"  />
			<input id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" onclick="return init_button(this,'Region Options','',region_options_callback)" value="No" />
			<input type="button" onclick="window.open('$this->help_link')" value="Help" />
		</form>
END;
		
		$arh->decode_response(true, $form);
	}
	
	/**
	 * Add Media to the Database
	 * @return 
	 */
	public function AddMedia()
	{
		$db 		=& $this->db;
		$response 	=& $this->response;
		
		//Other properties
		$direction	  = Kit::GetParam('direction', _POST, _WORD, 'none');
		$duration	  = Kit::GetParam('duration', _POST, _INT, 1);
		$text		  = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
						
		//validation
		if ($text == '')
		{
			$response->SetError('Please enter some text');
			$response->keepOpen = true;
			return $response;
		}
		
		// Required Attributes
		$this->mediaid	= md5(uniqid());
		$this->type		= 'text';
		$this->duration = $duration;
		
		// Any Options
		$this->SetOption('direction', $direction);
		$this->SetRaw('<text><![CDATA[' . $text . ']]></text>');
		
		// Should have built the media object entirely by this time
		// This saves the Media Object to the Region
		$this->UpdateRegion();
		
		//Set this as the session information
		setSession('content', 'type', 'text');
		
		return $response;
	}
	
	/**
	 * Edit Media in the Database
	 * @return 
	 */
	public function EditMedia()
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh = new ResponseManager();
		
		//Other properties
		$direction	  = $_POST['direction'];
		$duration	  = $_POST['duration'];
		$text		  = $_POST['ta_text'];
		
		if (get_magic_quotes_gpc())
		{
			$text = stripslashes($text);
		}
		
		//Optional parameters
		$layoutid = $_POST['layoutid'];
		$regionid = $_POST['regionid'];
		$mediaid  = $_POST['mediaid'];
		
		//Ensure regions
		if ($layoutid == "" && $regionid == "")
		{
			$this->message .= "Text must be assigned to regions";
			return false;
		}
		
		//validation
		if ($text == "")
		{
			$this->message .= "Please enter some text";
			return false;
		}
		
		//Validate the URL?
		
		if (!is_numeric($duration))
		{
			$this->message .= "You must enter a value for duration";
			return false;
		}
		
		//Save the info in the object
		$this->SetMediaId($mediaid);
		
		$this->text		= $text;
		$this->duration = $duration;
		$this->direction = $direction;
		
		//Do the assignment here - we probabily want to create a region object to handle this.
		include_once("lib/pages/region.class.php");
	
		$region = new region($db, $user);
		
		if (!$region->SwapMedia($layoutid, $regionid, "", $mediaid, $mediaid, $this->AsXml()))
		{
			$message = "Unable to assign to the Region";
			return false;
		}

		//Set this as the session information
		setSession('content', 'type', 'text');
		
		return true;		
	}
	
	/**
	 * Delete Media from the Database
	 * @return 
	 */
	public function DeleteMedia() 
	{
		$db =& $this->db;
		
		$layoutid = $_REQUEST['layoutid'];
		$regionid = $_REQUEST['regionid'];
		$mediaid  = $_REQUEST['mediaid'];
		
		//Options
		//Regardless of the option we want to unassign.
		include_once("lib/app/region.class.php");
	
		$region = new region($db);
		
		if (!$region->RemoveMedia($layoutid, $regionid, $lkid, $mediaid))
		{
			$this->message = "Unable to Remove this media from the Layout";
			return false;
		}
		
		return true;
	}
}

?>