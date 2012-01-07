<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class Module implements ModuleInterface
{
	//Media information
	protected $db;
	protected $user;
	protected $region;
	protected $response;
        public $auth;
	protected $type;
      	protected $displayType;

	protected $layoutid;
	protected $regionid;

	protected $mediaid;
	protected $name;
	private   $schemaVersion;
	protected $regionSpecific;
	protected $duration;
	protected $lkid;
	protected $validExtensions;
	protected $validExtensionsText;

	protected $xml;

	protected $existingMedia;
	protected $deleteFromRegion;
        protected $showRegionOptions;
        protected $originalUserId;
        protected $assignedMedia;

    /**
     * Constructor - sets up this media object with all the available information
     * @return
     * @param $db database
     * @param $user user
     * @param $mediaid String[optional]
     * @param $layoutid String[optional]
     * @param $regionid String[optional]
     */
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        include_once("lib/pages/region.class.php");

        $this->db 	=& $db;
        $this->user 	=& $user;

        $this->mediaid 	= $mediaid;
        $this->name 	= '';
        $this->layoutid = $layoutid;
        $this->regionid = $regionid;
        $this->lkid     = $lkid;

        $this->region 	= new region($db, $user);
        $this->response = new ResponseManager();

        $this->existingMedia 	= false;
        $this->deleteFromRegion = false;
        $this->showRegionOptions = Kit::GetParam('showRegionOptions', _REQUEST, _INT, 1);
        $this->duration = '';

        // Determine which type this module is
        $this->SetModuleInformation();

        Debug::LogEntry($db, 'audit', 'Module created with MediaID: ' . $mediaid . ' LayoutID: ' . $layoutid . ' and RegionID: ' . $regionid);

        // Either the information from the region - or some blanks
        $this->SetMediaInformation($this->layoutid, $this->regionid, $this->mediaid, $this->lkid);

        return true;
    }

	/**
	 * Sets the module information
	 * @return
	 */
	final private function SetModuleInformation()
	{
		$db 		=& $this->db;
		$type		= $this->type;

		if ($type == '')
		{
			$this->response->SetError(__('Unable to create Module [No type given] - please refer to the Module Documentation.'));
			$this->response->Respond();
		}

		$SQL = sprintf("SELECT * FROM module WHERE Module = '%s'", $db->escape_string($type));

		if (!$result = $db->query($SQL))
		{
			$this->response->SetError(__('Unable to create Module [Cannot find type in the database] - please refer to the Module Documentation.'));
			$this->response->Respond();
		}

		if ($db->num_rows($result) != 1)
		{
			$this->response->SetError(__('Unable to create Module [No registered modules of this type] - please refer to the Module Documentation.'));
			$this->response->Respond();
		}

		$row = $db->get_assoc_row($result);

		$this->schemaVersion 		= Kit::ValidateParam($row['SchemaVersion'], _INT);
		$this->regionSpecific 		= Kit::ValidateParam($row['RegionSpecific'], _INT);
		$this->validExtensionsText 	= Kit::ValidateParam($row['ValidExtensions'], _STRING);
		$this->validExtensions 		= explode(',', $this->validExtensionsText);
		$this->validExtensionsText	= str_replace(',', ', ', $this->validExtensionsText);

		return true;
	}

    /**
     * Gets the information about this Media on this region on this layout
     * @return
     * @param $layoutid Object
     * @param $regionid Object
     * @param $mediaid Object
     */
    final private function SetMediaInformation($layoutid, $regionid, $mediaid, $lkid)
    {
        $db =& $this->db;
        $region =& $this->region;
        $xmlDoc = new DOMDocument();

        if ($this->mediaid != '' && $this->regionid != '' && $this->layoutid != '')
        {
            // Existing media that is assigned to a layout
            $this->existingMedia = true;
            $this->assignedMedia = true;

            // Set the layout Xml
            $layoutXml = $region->GetLayoutXml($layoutid);

            //Debug::LogEntry($db, 'audit', 'Layout XML retrieved: ' . $layoutXml);

            $layoutDoc = new DOMDocument();
            $layoutDoc->loadXML($layoutXml);

            $layoutXpath = new DOMXPath($layoutDoc);

            // Get the media node and extract the info
            if ($lkid != '')
                $mediaNodeXpath = $layoutXpath->query("//region[@id='$regionid']/media[@lkid='$lkid']");
            else
                $mediaNodeXpath = $layoutXpath->query("//region[@id='$regionid']/media[@id='$mediaid']");

            // Test to make sure we got a node
            if ($mediaNodeXpath->length <= 0)
                trigger_error(__('Cannot find this media item. Please refresh the region options.'), E_USER_ERROR);

            // Create a Media node in the DOMDocument for us to replace
            $xmlDoc->loadXML('<root/>');

            $mediaNode = $mediaNodeXpath->item(0);
            $mediaNode->setAttribute('schemaVersion', $this->schemaVersion);

            $this->duration = $mediaNode->getAttribute('duration');
            
            // Get the LK id if we do not have one provided
            if ($lkid == '')
                $this->lkid = $mediaNode->getAttribute('lkid');

            $this->originalUserId = $mediaNode->getAttribute('userId');

            // Make sure we have permissions
            $this->auth = $this->user->MediaAssignmentAuth($this->originalUserId, $this->layoutid, $this->regionid, $this->mediaid, true);

            $mediaNode = $xmlDoc->importNode($mediaNode, true);
            $xmlDoc->documentElement->appendChild($mediaNode);

            Debug::LogEntry($db, 'audit', 'Existing Assigned Media XML is: \n ' . $xmlDoc->saveXML(), 'module', 'SetMediaInformation');
        }
        else
        {
            if ($this->mediaid != '' && $this->regionSpecific == 0)
            {
                // We do not have a region or a layout
                // But this is some existing media
                // Therefore make sure we get the bare minimum!
                $this->existingMedia = true;
                $this->assignedMedia = false;

                // Load what we know about this media into the object
                $SQL = "SELECT duration, name, UserId FROM media WHERE mediaID = '$mediaid'";

                Debug::LogEntry($db, 'audit', $SQL, 'Module', 'SetMediaInformation');

                if (!$result = $db->query($SQL))
                {
                    trigger_error($db->error()); //log the error
                }

                if ($db->num_rows($result) != 0)
                {
                    $row = $db->get_row($result);
                    $this->duration = $row[0];
                    $this->name = $row[1];
                    $this->originalUserId = $row[2];
                }

                $this->auth = $this->user->MediaAuth($this->mediaid, true);
            }
            else
            {
                // New assignment, therefore user and permissions are defaulted
                $this->originalUserId = $this->user->userid;
            }

            $xml = <<<XML
            <root>
                    <media id="" type="$this->type" duration="" lkid="" userId="$this->originalUserId" schemaVersion="$this->schemaVersion">
                            <options />
                            <raw />
                    </media>
            </root>
XML;
            $xmlDoc->loadXML($xml);
        }

        $this->xml = $xmlDoc;
        return true;
    }

	/**
	 * Sets the Layout and Region Information
	 * @return
	 * @param $layoutid Object
	 * @param $regionid Object
	 * @param $mediaid Object
	 */
	public function SetRegionInformation($layoutid, $regionid)
	{
		$this->layoutid = $layoutid;
		$this->regionid = $regionid;

		return true;
	}

	/**
	 * This Media item represented as XML
	 * @return
	 */
	final public function AsXml()
	{
		// Make sure the required attributes are present on the Media Node
		// We can add / change:
		// 		MediaID
		//		Duration
		//		Type
		//		SchemaVersion (use the type to get this from the DB)
		// LkID is done by the region code (where applicable - otherwise it will be left blank)
		$mediaNodes = $this->xml->getElementsByTagName('media');
		$mediaNode	= $mediaNodes->item(0);

		$mediaNode->setAttribute('id', $this->mediaid);
		$mediaNode->setAttribute('duration', $this->duration);
		$mediaNode->setAttribute('type', $this->type);
                $mediaNode->setAttribute('userId', $this->originalUserId);

		return $this->xml->saveXML($mediaNode);
	}

	/**
	 * Adds the name/value element to the XML Options sequence
	 * @return
	 * @param $name String
	 * @param $value String
	 */
	final protected function SetOption($name, $value)
	{
		$db =& $this->db;
		if ($name == '') return;

		Debug::LogEntry($db, 'audit', sprintf('IN with Name=%s and value=%s', $name, $value), 'module', 'Set Option');

		// Get the options node from this document
		$optionNodes = $this->xml->getElementsByTagName('options');
		// There is only 1
		$optionNode = $optionNodes->item(0);

		// Create a new option node
		$newNode = $this->xml->createElement($name, $value);

		Debug::LogEntry($db, 'audit', sprintf('Created a new Option Node with Name=%s and value=%s', $name, $value), 'module', 'Set Option');

		// Check to see if we already have this option or not
		$xpath = new DOMXPath($this->xml);

		// Xpath for it
		$userOptions = $xpath->query('//options/' . $name);

		if ($userOptions->length == 0)
		{
			// Append the new node to the list
			$optionNode->appendChild($newNode);
		}
		else
		{
			// Replace the old node we found with XPath with the new node we just created
			$optionNode->replaceChild($newNode, $userOptions->item(0));
		}
	}

	/**
	 * Gets the value for the option in Parameter 1
	 * @return
	 * @param $name String The Option Name
	 * @param $default Object[optional] The Default Value
	 */
	final protected function GetOption($name, $default = false)
	{
		$db =& $this->db;

		if ($name == '') return false;

		// Check to see if we already have this option or not
		$xpath = new DOMXPath($this->xml);

		// Xpath for it
		$userOptions = $xpath->query('//options/' . $name);

		if ($userOptions->length == 0)
		{
			// We do not have an option - return the default
			Debug::LogEntry($db, 'audit', 'GetOption ' . $name . ': Not Set - returning default ' . $default);
			return $default;
		}
		else
		{
			// Replace the old node we found with XPath with the new node we just created
			Debug::LogEntry($db, 'audit', 'GetOption ' . $name . ': Set - returning: ' . $userOptions->item(0)->nodeValue);
			return ($userOptions->item(0)->nodeValue != '') ? $userOptions->item(0)->nodeValue : $default;
		}
	}

	/**
	 * Sets the RAW XML string that is given as the content for Raw
	 * @return
	 * @param $xml String
	 * @param $replace Boolean[optional]
	 */
	final protected function SetRaw($xml, $replace = false)
	{
		if ($xml == '') return;

		// Load the XML we are given into its own document
		$rawNode = new DOMDocument();
		$rawNode->loadXML('<raw>' . $xml . '</raw>');

		// Import the Raw node into this document (with all sub nodes)
		$importedNode = $this->xml->importNode($rawNode->documentElement, true);

		// Get the Raw Xml node from our document
		$rawNodes = $this->xml->getElementsByTagName('raw');

		// There is only 1
		$rawNode = $rawNodes->item(0);

		// Append the imported node (at the end of whats already there)
		$rawNode->parentNode->replaceChild($importedNode, $rawNode);
	}

	/**
	 * Gets the XML string from RAW
	 * @return
	 */
	final protected function GetRaw()
	{
		// Get the Raw Xml node from our document
		$rawNodes = $this->xml->getElementsByTagName('raw');

		// There is only 1
		$rawNode = $rawNodes->item(0);

		// Return it as a XML string
		return $this->xml->saveXML($rawNode);
	}

	/**
	 * Updates the region information with this media record
	 * @return
	 */
	final public function UpdateRegion()
	{
            Debug::LogEntry($this->db, 'audit', 'Updating Region');

            // By this point we expect to have a MediaID, duration
            $layoutid = $this->layoutid;
            $regionid = $this->regionid;

            if ($this->deleteFromRegion)
            {
                    // We call region delete
                    if (!$this->region->RemoveMedia($layoutid, $regionid, $this->lkid, $this->mediaid))
                    {
                            $this->message = __("Unable to Remove this media from the Layout");
                            return false;
                    }
            }
            else
            {
                    if ($this->existingMedia)
                    {
                            // We call region swap with the same media id
                            if (!$this->region->SwapMedia($layoutid, $regionid, $this->lkid, $this->mediaid, $this->mediaid, $this->AsXml()))
                            {
                                    $this->message = __("Unable to assign to the Region");
                                    return false;
                            }
                    }
                    else
                    {
                            // We call region add
                            if (!$this->region->AddMedia($layoutid, $regionid, $this->regionSpecific, $this->AsXml()))
                            {
                                    $this->message = __("Error adding this media to the library");
                                    return false;
                            }
                    }
            }
            Debug::LogEntry($this->db, 'audit', 'Finished Updating Region');

            return true;
	}

	/**
	* Determines whether or not the provided file extension is valid for this module
	*
	*/
	final protected function IsValidExtension($extension)
	{
		return in_array($extension, $this->validExtensions);
	}

	/**
	 * Return the Delete Form as HTML
	 * @return
	 */
	public function DeleteForm()
	{
            $db =& $this->db;
            $helpManager = new HelpManager($db, $this->user);
            $this->response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Media', 'Delete') . '")');

            //Parameters
            $layoutid = $this->layoutid;
            $regionid = $this->regionid;
            $mediaid = $this->mediaid;
            $lkid = $this->lkid;
            $userid = $this->user->userid;

            // Can this user delete?
            if (!$this->auth->del)
            {
                $this->response->SetError('You do not have permission to delete this media.');
                $this->response->keepOpen = false;
                return $this->response;
            }

            // Messages
            $msgTitle = __('Return to the Region Options');
            $msgWarn = __('Are you sure you want to remove this item from Xibo?');
            $msgWarnLost = __('It will be lost');
            $msgYes = __('Yes');
            $msgNo = __('No');

            if ($this->regionSpecific)
            {
                $form = <<<END
                <form id="MediaDeleteForm" class="XiboForm" method="post" action="index.php?p=module&mod=text&q=Exec&method=DeleteMedia">
                        <input type="hidden" name="mediaid" value="$mediaid">
                        <input type="hidden" name="layoutid" value="$layoutid">
                        <input type="hidden" name="regionid" value="$regionid">
                        <p>$msgWarn <span class="required">$msgWarnLost</span>.</p>
                </form>
END;
                $this->response->AddButton(__('No'), 'XiboFormRender("index.php?p=layout&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
                $this->response->AddButton(__('Yes'), '$("#MediaDeleteForm").submit()');
            }
            else
            {
                // This is for library based media
                $options = '';

                // Always have the abilty to unassign from the region
                $options .= 'unassign|' . __('Unassign from this region only');

                // Get the permissions for the media item
                $mediaAuth = $this->user->MediaAuth($mediaid, true);

                // Is this user allowed to delete this media?
                if ($mediaAuth->del)
                {
                    // Load what we know about this media into the object
                    $SQL = "SELECT IFNULL(editedMediaID, 0) AS editedMediaID FROM media WHERE mediaID = $mediaid ";
                    $editedMediaID = $db->GetSingleValue($SQL, 'editedMediaID', _INT);
                    
                    if ($editedMediaID === false)
                    {
                        trigger_error($editedMediaID . $db->error());
                        $this->response->SetError(__('Error querying for the Media information'));
                        $this->response->keepOpen = true;
                        return $this->response;
                    }

                    $options .= ',retire|' . __('Unassign from this region and retire');

                    // Is this media retired?
                    $revised = false;
                    if ($editedMediaID != 0)
                            $revised = true;

                    // Is this media being used anywhere else?
                    if ($layoutid == '')
                    {
                        $SQL = sprintf('SELECT layoutID FROM lklayoutmedia WHERE mediaID = %d ', $mediaid);
                        $options = '';
                    }
                    else
                    {
                        $SQL = sprintf("SELECT layoutID FROM lklayoutmedia WHERE mediaID = %d AND layoutid <> %d AND regionID <> '%s' ", $mediaid, $layoutid, $regionid);
                    }

                    if (!$results = $db->query($SQL))
                    {
                        trigger_error($db->error());

                        $this->response->SetError(__('Cannot determine if this media has been used.'));
                        $this->response->keepOpen = true;
                        return $this->response;
                    }

                    if ($db->num_rows($results) == 0 && !$revised)
                    {
                        $options .= ',delete|' . __('Delete this media');
                    }
                    else
                    {
                        $options .= ',retire|' . __('Retire this media');
                    }
                }
                else
                {
                    // If this is the normal content page then say they cant edit, otherwise display the form with only the unassign option
                    if ($layoutid == '')
                    {
                        $this->response->SetError(__('You do not have permission to alter/delete this media.'));
                        $this->response->keepOpen = true;
                        return $this->response;
                    }
                }

                $options = ltrim($options, ',');

                $deleteOptions = listcontent($options, 'options');

                $msgWarn = __('Are you sure you want to delete this media?');
                $msgSelect = __('Please select from the following options');
                $msgCaution = __('Deleting media cannot be undone');

                //we can delete
                $form = <<<END
                <form id="MediaDeleteForm" class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=DeleteMedia">
                    <input type="hidden" name="mediaid" value="$mediaid">
                    <input type="hidden" name="lkid" value="$lkid">
                    <input type="hidden" name="layoutid" value="$layoutid">
                    <input type="hidden" name="regionid" value="$regionid">
                    <p>$msgWarn</p>
                    <p>$msgSelect: $deleteOptions </p>
                    <p>$msgCaution</p>
                </form>
END;
                if ($layoutid == '')
                    $this->response->AddButton(__('No'), 'XiboDialogClose()');
                else
                   $this->response->AddButton(__('No'), 'XiboFormRender("index.php?p=layout&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');

                $this->response->AddButton(__('Yes'), '$("#MediaDeleteForm").submit()');
            }

            $this->response->html = $form;
            $this->response->dialogTitle = __('Delete Media');
            $this->response->dialogSize = true;
            $this->response->dialogWidth = '450px';
            $this->response->dialogHeight = '280px';

            return $this->response;
	}

	/**
	 * Delete Media from the Database
	 * @return
	 */
	public function DeleteMedia()
	{
            $db =& $this->db;

            $layoutid = $this->layoutid;
            $regionid = $this->regionid;
            $mediaid = $this->mediaid;

            $url = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

            if (!$this->auth->del)
            {
                $this->response->SetError('You do not have permission to delete this assignment.');
                $this->response->keepOpen = false;
                return $this->response;
            }

            if ($layoutid != '')
            {
                Kit::ClassLoader('layoutmediagroupsecurity');
                $security = new LayoutMediaGroupSecurity($db);

                if (!$security->UnlinkAll($layoutid, $regionid, $this->mediaid))
                    trigger_error($security->GetErrorMessage(), E_USER_ERROR);

                $this->deleteFromRegion = true;
                $this->UpdateRegion();
            }

            // Are we region specific media?
            if (!$this->regionSpecific)
            {
                $options = Kit::GetParam('options', _POST, _WORD);

                // If we are set to retire we retire
		if ($options == 'retire')
		{
                    //Update the media record to say it is retired
                    $SQL = sprintf("UPDATE media SET retired = 1 WHERE mediaid = %d ", $mediaid);

                    if (!$db->query($SQL))
                    {
                        trigger_error($db->error());

                        $this->response->SetError(__('Database error retiring this media record.'));
                        $this->response->keepOpen = true;
                        return $this->response;
                    }
		}

		// If we are set to delete, we delete
		if ($options == 'delete')
		{
                    // Get the file location from the database
                    $storedAs = $db->GetSingleValue(sprintf("SELECT storedAs FROM media WHERE mediaid = %d", $mediaid), 'storedAs', _FILENAME);

                    // Remove permission assignments
                    Kit::ClassLoader('mediagroupsecurity');

                    $security = new MediaGroupSecurity($db);

                    if (!$security->UnlinkAll($mediaid))
                        trigger_error($security->GetErrorMessage(), E_USER_ERROR);

                    //Update the media record to say it is retired
                    $SQL = sprintf("DELETE FROM media WHERE mediaid = %d ", $mediaid);

                    if (!$db->query($SQL))
                    {
                        trigger_error($db->error());

                        $this->response->SetError(__('Database error deleting this media record.'));
                        $this->response->keepOpen = true;
                        return $this->response;
                    }

                    $this->DeleteMediaFiles($storedAs);
		}

                $this->response->message = __('Media Deleted');
            }
            else
            {
                // We want to load a new form
                $this->response->loadForm = true;
                $this->response->loadFormUri= $url;
            }

            return $this->response;
	}

	/**
	 * Default AddForm
	 * @return
	 */
	public function AddForm()
	{
		$form = '<p>' . __('Not yet implemented by this module.') . '</p>';
END;

		$this->response->html 		 	= $form;
		$this->response->dialogTitle 	= __('Add Item');
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '450px';
		$this->response->dialogHeight 	= '150px';

		return $this->response;
	}

    protected function AddFormForLibraryMedia()
    {
        global $session;
        $db =& $this->db;
        $user =& $this->user;

        // Would like to get the regions width / height
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;

        // Set the Session / Security information
        $sessionId = session_id();
        $securityToken = CreateFormToken();
        $backgroundImage = Kit::GetParam('backgroundImage', _GET, _BOOL, false);

        $session->setSecurityToken($securityToken);

        //Get the default value for the shared list
        $default = Config::GetSetting($db, 'defaultMedia');
        $defaultDuration = Config::GetSetting($db, 'jpg_length');

        // Save button is different depending on if we are on a region or not
        if ($regionid != '' && $this->showRegionOptions)
        {
            setSession('content','mediatype', $this->type);

            $save_button = <<<END
            <input id="btnSave" type="submit" value="Save" disabled />
            <input class="XiboFormButton" id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" value="Cancel" />
            <input class="XiboFormButton" type="button" href="index.php?p=content&q=LibraryAssignForm&layoutid=$layoutid&regionid=$regionid" title="Library" value="Library" />
END;
        }
        elseif ($regionid != '' && !$this->showRegionOptions)
        {
            $save_button = <<<END
            <input id="btnSave" type="submit" value="Save" disabled />
            <input class="XiboFormButton" id="btnCancel" type="button" title="Close" onclick="$('#div_dialog').dialog('close')" value="Cancel" />
END;
        }
        elseif ($backgroundImage)
        {
            // Show the save button, and make cancel go back to the background form
            $save_button = <<<END
            <input id="btnSave" type="submit" value="Save" disabled />
            <input class="XiboFormButton" id="btnCancel" type="button" title="Close" href="index.php?p=layout&q=BackgroundForm&modify=true&layoutid=$layoutid" value="Cancel" />
END;
        }
        else
        {
            $save_button = <<<END
            <input id="btnSave" type="submit" value="Save" disabled />
            <input class="XiboFormButton" id="btnCancel" type="button" title="Close" href="index.php?p=content&q=displayForms&sp=add" value="Cancel" />
END;
        }

        $form = <<<FORM
        <div style="display:none"><iframe name="fileupload" width="1px" height="1px"></iframe></div>
        <div>
                <form id="file_upload" method="post" action="index.php?p=content&q=FileUpload" enctype="multipart/form-data" target="fileupload">
                        <input type="hidden" id="PHPSESSID" value="$sessionId" />
                        <input type="hidden" id="SecurityToken" value="$securityToken" />
                        <input type="hidden" name="MAX_FILE_SIZE" value="$this->maxFileSizeBytes" />
                        <table>
                                <tr>
                                        <td><label for="file">$this->displayType File<span class="required">*</span></label></td>
                                        <td colspan="3">
                                                <input type="file" name="media_file" onchange="fileFormSubmit();this.form.submit();" />
                                        </td>
                                </tr>
                        </table>
                </form>
        </div>
        <div id="uploadProgress" style="display:none">
                <img src="img/loading.gif"><span style="padding-left:10px">You may fill in the form while your file is uploading.</span>
        </div>
        <form class="XiboForm" id="AddLibraryBasedMedia" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=AddMedia">
                <input type="hidden" name="layoutid" value="$layoutid">
                <input type="hidden" name="regionid" value="$regionid">
                <input type="hidden" name="backgroundImage" value="$backgroundImage" />
                <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
                <input type="hidden" id="txtFileName" name="txtFileName" readonly="true" />
                <input type="hidden" name="hidFileID" id="hidFileID" value="" />
                <table width="100%">
                        <tr>
                        <td><label for="name" title="The name of the $this->type. Leave this blank to use the file name">Name</label></td>
                        <td><input id="name" name="name" type="text"></td>
                        </tr>
                        <tr>
                        <td><label for="duration" title="The duration in seconds this image should be displayed (may be overridden on each layout)">Duration<span class="required">*</span></label></td>
                        <td><input id="duration" name="duration" type="text" value="$defaultDuration"></td>
                        </tr>
                        <tr>
                                <td></td>
                                <td>This form accepts: <span class="required">$this->validExtensionsText</span> files up to a maximum size of <span class="required">$this->maxFileSize</span>.</td>
                        </tr>
                        <tr>
                                <td></td>
                                <td colspan="3">$save_button</td>
                        </tr>
                </table>
        </form>
FORM;

        $this->response->html = $form;
        $this->response->dialogTitle = 'Add New ' . $this->displayType;
        $this->response->dialogSize = true;
        $this->response->dialogWidth = '450px';
        $this->response->dialogHeight = '280px';

        return $this->response;
    }

	/**
	 * Default Edit Form
	 * @return
	 */
	public function EditForm()
	{
		$form = '<p>' . __('Not yet implemented by this module.') . '</p>';

		$this->response->html 		 	= $form;
		$this->response->dialogTitle 	= __('Add Item');
		$this->response->dialogSize 	= true;
		$this->response->dialogWidth 	= '450px';
		$this->response->dialogHeight 	= '150px';

		return $this->response;
	}

    protected function EditFormForLibraryMedia()
    {
        global $session;
        $db =& $this->db;
        $user =& $this->user;

        // Would like to get the regions width / height
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;
        $lkid = $this->lkid;
        $userid	= $this->user->userid;

        // Can this user delete?
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this media.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // Set the Session / Security information
        $sessionId = session_id();
        $securityToken = CreateFormToken();

        $session->setSecurityToken($securityToken);

        // Load what we know about this media into the object
        $SQL = "SELECT name, originalFilename, userID, retired, storedAs, isEdited, editedMediaID FROM media WHERE mediaID = $mediaid ";

        if (!$row = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error()); //log the error

            $this->message = __('Error querying for the Media information');
            return false;
        }

        $name = $row['name'];
        $originalFilename = $row['originalFilename'];
        $userid = $row['userID'];
        $retired = $row['retired'];
        $storedAs = $row['storedAs'];
        $isEdited = $row['isEdited'];
        $editedMediaID = $row['editedMediaID'];
        $ext = strtolower(substr(strrchr($originalFilename, '.'), 1));

        // Save button is different depending on if we are on a region or not
        if ($regionid != '' && $this->showRegionOptions)
        {
            setSession('content', 'mediatype', $this->type);

            $extraNotes = '<em>Note: Uploading a new ' . $this->displayType . ' here will replace it on this layout only.</em>';

            $save_button = <<<END
            <input id="btnSave" type="submit" value="Save" />
            <input class="XiboFormButton" id="btnCancel" type="button" title="Return to the Region Options" href="index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions" value="Cancel" />
END;
        }
        elseif ($regionid != '' && !$this->showRegionOptions)
        {
            $extraNotes = '<em>Note: Uploading a new ' . $this->displayType . ' here will replace it on this layout only.</em>';
            
            $save_button = <<<END
            <input id="btnSave" type="submit" value="Save" />
            <input id="btnCancel" type="button" title="Close" onclick="$('#div_dialog').dialog('close')" value="Cancel" />
END;
        }
        else
        {
            $updateMediaChecked = (Config::GetSetting($db, 'LIBRARY_MEDIA_UPDATEINALL_CHECKB') == 'Checked') ? 'checked' : '';
            $extraNotes = '<input type="checkbox" id="replaceInLayouts" name="replaceInLayouts" ' . $updateMediaChecked . '><label for="replaceInLayouts">' . __('Update this media in all layouts it is assigned to. Note: It will only be replaced in layouts you have permission to edit.') . '</label>';

            $save_button = <<<END
            <input id="btnSave" type="submit" value="Save" />
            <input id="btnCancel" type="button" title="Close" onclick="$('#div_dialog').dialog('close')" value="Cancel" />
END;
        }

        $durationFieldEnabled = ($this->auth->modifyPermissions) ? '' : ' readonly';

        $form = <<<FORM
        <div style="display:none"><iframe name="fileupload" width="1px" height="1px"></iframe></div>
        <div>
                <form id="file_upload" method="post" action="index.php?p=content&q=FileUpload" enctype="multipart/form-data" target="fileupload">
                        <input type="hidden" id="PHPSESSID" value="$sessionId" />
                        <input type="hidden" id="SecurityToken" value="$securityToken" />
                        <input type="hidden" name="MAX_FILE_SIZE" value="$this->maxFileSizeBytes" />
                        <table>
                                <tr>
                                        <td><label for="file">New $this->displayType File<span class="required">*</span></label></td>
                                        <td colspan="3">
                                                <input type="file" name="media_file" onchange="fileFormSubmit();this.form.submit();" />
                                        </td>
                                </tr>
                        </table>
                </form>
        </div>
        <div id="uploadProgress" style="display:none">
                <img src="img/loading.gif"><span style="padding-left:10px">You may fill in the form while your file is uploading.</span>
        </div>
        <form class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=EditMedia">
                <input type="hidden" name="hidFileID" id="hidFileID" value="" />
                <input type="hidden" id="txtFileName" name="txtFileName" readonly="true" />
                <input type="hidden" name="layoutid" value="$layoutid">
                <input type="hidden" name="regionid" value="$regionid">
                <input type="hidden" name="mediaid" value="$mediaid">
                <input type="hidden" name="lkid" value="$lkid">
                <input type="hidden" id="PHPSESSID" value="$sessionId" />
                <input type="hidden" id="SecurityToken" value="$securityToken" />
                <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
                <table>
                        <tr>
                        <td><label for="name" title="The name of the $this->displayType. Leave this blank to use the file name">Name</label></td>
                        <td><input id="name" name="name" type="text" value="$name"></td>
                        <td><label for="duration" title="The duration in seconds this media should be displayed (may be overridden on each layout)">Duration<span class="required">*</span></label></td>
                        <td><input id="duration" name="duration" type="text" value="$this->duration" $durationFieldEnabled></td>
                        </tr>
                        <tr>
                                <td></td>
                                <td>This form accepts: <span class="required">$this->validExtensionsText</span> files up to a maximum size of <span class="required">$this->maxFileSize</span>.</td>
                        </tr>
                        <tr>
                                <td></td>
                                <td colspan="2">$extraNotes</td>
                        </tr>
                        <tr>
                                <td></td>
                                <td colspan="3">$save_button</td>
                        </tr>
                </table>
        </form>
FORM;

        $this->response->html 		= $form;
        $this->response->dialogTitle 	= 'Edit ' . $this->displayType;
        $this->response->dialogSize 	= true;
        $this->response->dialogWidth 	= '450px';
        $this->response->dialogHeight 	= '280px';

        return $this->response;
    }

	/**
	 * Default Add Media
	 * @return
	 */
	public function AddMedia()
	{
		// We want to load a new form
		$this->response->message = __('Add Media has not been implemented for this module.');
		
		return $this->response;	
	}

    protected function AddLibraryMedia()
    {
        $db =& $this->db;
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;
        $userid	= $this->user->userid;
        $backgroundImage = Kit::GetParam('backgroundImage', _POST, _BOOL, false);

        // File data
        $tmpName = Kit::GetParam('hidFileID', _POST, _STRING);

        if ($tmpName == '')
        {
            $this->response->SetError('Cannot save Image details. <br/> You must have picked a file.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        // File name and extension (orignial name)
        $fileName = Kit::GetParam('txtFileName', _POST, _STRING);
        $fileName = basename($fileName);
        $ext = strtolower(substr(strrchr($fileName, "."), 1));

        // Other properties
        $name = Kit::GetParam('name', _POST, _STRING);
        $duration = Kit::GetParam('duration', _POST, _INT, -1);

        if ($name == '')
            $name = Kit::ValidateParam($fileName, _FILENAME);

        // Validation
        if (!$this->IsValidExtension($ext))
        {
            $this->response->SetError(sprintf(__('Your file has an extension not supported by Media Type %s'), $this->displayType));
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Make sure the name isnt too long
        if (strlen($name) > 100)
        {
            $this->response->SetError(__('The name cannot be longer than 100 characters'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        if ($duration < 0)
        {
            $this->response->SetError(__('You must enter a duration.'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Ensure the name is not already in the database
        $SQL = sprintf("SELECT name FROM media WHERE name = '%s' AND userid = %d", $db->escape_string($name), $userid);

        if(!$result = $db->query($SQL))
        {
            trigger_error($db->error());
            $this->response->SetError('Error checking whether the media name is ok. Try choosing a different name.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        if ($db->num_rows($result) != 0)
        {
            $this->response->SetError('Some media you own already has this name. Please choose another.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        // All OK to insert this record
        $SQL  = "INSERT INTO media (name, type, duration, originalFilename, userID, retired ) ";
        $SQL .= "VALUES ('%s', '$this->type', '%s', '%s', %d, 0) ";

        $SQL = sprintf($SQL, $db->escape_string($name), $db->escape_string($duration), $db->escape_string($fileName), $userid);

        if (!$mediaid = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->response->SetError(__('Database error adding this media record.'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        // File upload directory.. get this from the settings object
        $databaseDir = Config::GetSetting($db, 'LIBRARY_LOCATION');

        // What are we going to store this media as...
        $storedAs = $mediaid . '.' . $ext;

        // Now we need to move the file
        if (!$result = rename($databaseDir . 'temp/' . $tmpName, $databaseDir . $storedAs))
        {
            // If we couldnt move it - we need to delete the media record we just added
            $SQL = sprintf("DELETE FROM media WHERE mediaID = %d ", $mediaid);

            if (!$db->query($SQL))
            {
                trigger_error($db->error());
                $this->response->SetError(__('Error storing file'));
                $this->response->keepOpen = true;
                return $this->response;
            }
        }

        // Calculate the MD5 and the file size
        $md5 = md5_file($databaseDir.$storedAs);
        $fileSize = filesize($databaseDir.$storedAs);

        // Update the media record to include this information
        $SQL = sprintf("UPDATE media SET storedAs = '%s', `MD5` = '%s', FileSize = %d WHERE mediaid = %d", $storedAs, $md5, $fileSize, $mediaid);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return true;
        }

        // Required Attributes
        $this->mediaid	= $mediaid;
        $this->duration = $duration;

        // Any Options
        $this->SetOption('uri', $storedAs);

        // Should have built the media object entirely by this time
        if ($regionid != '' && $this->showRegionOptions)
        {
            // This saves the Media Object to the Region
            $this->UpdateRegion();
            $this->response->loadFormUri = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";;
        }
        elseif ($regionid != '' && !$this->showRegionOptions)
        {
            $this->UpdateRegion();
            $this->response->loadForm = false;
        }
        else
        {
            $this->response->loadFormUri = "index.php?p=content&q=displayForms&sp=add";
        }

        // We want to load a new form
        $this->response->loadForm = true;

        // If we just added a background we should load the background form
        if ($backgroundImage)
        {
            $this->response->loadFormUri = "index.php?p=layout&q=BackgroundForm&modify=true&layoutid=$layoutid&backgroundOveride=$storedAs";
        }

        // What permissions should we assign this with?
        if (Config::GetSetting($db, 'MEDIA_DEFAULT') == 'public')
        {
            Kit::ClassLoader('mediagroupsecurity');

            $security = new MediaGroupSecurity($db);
            $security->LinkEveryone($mediaid, 1, 0, 0);
        }

        return $this->response;
    }

	/**
	 * Default EditMedia
	 * @return
	 */
	public function EditMedia()
	{
		// We want to load a new form
		$this->response->message = __('Edit Media has not been implemented for this module.');
		
		return $this->response;	
	}

    protected function EditLibraryMedia()
    {
        $db =& $this->db;
        $user =& $this->user;
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;
        $userid = $this->user->userid;

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this media.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // Stored As from the XML
        $storedAs = $this->GetOption('uri');

        // File data
        $tmpName = Kit::GetParam('hidFileID', _POST, _STRING);

        if ($tmpName == '')
        {
            $fileRevision = false;
        }
        else
        {
            $fileRevision = true;

            // File name and extension (orignial name)
            $fileName = Kit::GetParam('txtFileName', _POST, _STRING);
            $fileName = basename($fileName);
            $ext = strtolower(substr(strrchr($fileName, "."), 1));

            if (!$this->IsValidExtension($ext))
            {
                $this->response->SetError('Your file has an extension not supported by this Media Type.');
                $this->response->keepOpen = true;
                return $this->response;
            }
        }

        // Other properties
        $name = Kit::GetParam('name', _POST, _STRING);
        
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

        if ($name == '')
        {
            if ($fileRevision)
            {
                $name = Kit::ValidateParam($fileName, _FILENAME);
            }
            else
            {
                $this->response->SetError(__('The Name cannot be blank.'));
                $this->response->keepOpen = true;
                return $this->response;
            }
        }

        // Make sure the name isnt too long
        if (strlen($name) > 100)
        {
            $this->response->SetError(__('The name cannot be longer than 100 characters'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        if ($this->duration < 0)
        {
            $this->response->SetError(__('You must enter a duration.'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Ensure the name is not already in the database
        $SQL = sprintf("SELECT name FROM media WHERE name = '%s' AND userid = %d AND mediaid <> %d  AND IsEdited = 0", $db->escape_string($name), $userid, $mediaid);

        if(!$result = $db->query($SQL))
        {
            trigger_error($db->error());
            $this->response->SetError(__('Error checking whether the media name is ok. Try choosing a different name.'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        if ($db->num_rows($result) != 0)
        {
            $this->response->SetError(__('Some media you own already has this name. Please choose another.'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        //Are we revising this media - or just plain editing
        if ($fileRevision)
        {
            // All OK to insert this record
            $SQL  = "INSERT INTO media (name, type, duration, originalFilename, userID, retired ) ";
            $SQL .= "VALUES ('%s', '$this->type', '%s', '%s', %d, 0) ";

            $SQL = sprintf($SQL, $db->escape_string($name), $db->escape_string($this->duration), $db->escape_string($fileName), $userid);

            if (!$new_mediaid = $db->insert_query($SQL))
            {
                trigger_error($db->error());
                trigger_error('Error inserting replacement media record.', E_USER_ERROR);
            }

            //What are we going to store this media as...
            $storedAs = $new_mediaid . '.' . $ext;

            // File upload directory.. get this from the settings object
            $databaseDir = Config::GetSetting($db, 'LIBRARY_LOCATION');

            //Now we need to move the file
            if (!$result = rename($databaseDir . '/temp/' . $tmpName, $databaseDir . $storedAs))
            {
                //If we couldnt move it - we need to delete the media record we just added
                $SQL = "DELETE FROM media WHERE mediaID = $new_mediaid ";

                if (!$db->insert_query($SQL))
                {
                    $this->response->SetError('Error rolling back transcation.');
                    $this->response->keepOpen = true;
                    return $this->response;
                }
            }

            // Calculate the MD5 and the file size
            $md5 = md5_file($databaseDir.$storedAs);
            $fileSize = filesize($databaseDir.$storedAs);

            // Update the media record to include this information
            $SQL = sprintf("UPDATE media SET storedAs = '%s', `MD5` = '%s', FileSize = %d WHERE mediaid = %d", $storedAs, $md5, $fileSize, $new_mediaid);

            if (!$db->query($SQL))
            {
                trigger_error($db->error());
                $this->response->SetError('Error updating media with Library location.');
                $this->response->keepOpen = true;
                return $this->response;
            }

            // Update the existing record with the new record's id
            $SQL =  "UPDATE media SET isEdited = 1, editedMediaID = $new_mediaid ";
            $SQL .= " WHERE IFNULL(editedMediaID,0) <> $new_mediaid AND mediaID = $mediaid ";

            Debug::LogEntry($db, 'audit', $SQL);

            if (!$db->query($SQL))
            {
                trigger_error($db->error());

                $this->response->SetError('Database error editing this media record.');
                $this->response->keepOpen = true;
                return $this->response;
            }

            // We need to assign all permissions for the old media id to the new media id
            Kit::ClassLoader('mediagroupsecurity');

            $security = new MediaGroupSecurity($db);
            $security->Copy($mediaid, $new_mediaid);

            // Are we on a region
            if ($regionid != '')
            {
                Kit::ClassLoader('layoutmediagroupsecurity');

                $security = new LayoutMediaGroupSecurity($db);
                $security->Copy($layoutid, $regionid, $mediaid, $new_mediaid);
            }
        }
        else
        {
            // Editing the existing record
            $new_mediaid = $mediaid;

            $SQL =  "UPDATE media SET name = '%s' ";
            $SQL .= " WHERE mediaID = %d ";
            $SQL = sprintf($SQL, $db->escape_string($name), $mediaid);

            Debug::LogEntry($db, 'audit', $SQL);

            if (!$db->query($SQL))
            {
                trigger_error($db->error());

                $this->response->SetError('Database error editing this media record.');
                $this->response->keepOpen = true;
                return $this->response;
            }
        }

        // Required Attributes
        $this->mediaid	= $new_mediaid;

        // Any Options
        $this->SetOption('uri', $storedAs);

        // Should have built the media object entirely by this time
        if ($regionid != '' && $this->showRegionOptions)
        {
            // This saves the Media Object to the Region
            $this->UpdateRegion();

            $this->response->loadForm	 = true;
            $this->response->loadFormUri = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";;
        }
        elseif ($regionid != '' && !$this->showRegionOptions)
        {
            $this->UpdateRegion();
            $this->response->loadForm = false;
        }
        else
        {
            // We are in the library so we therefore have to update the duration with the new value.
            // We could do this in the above code, but it is much simpler here until we rewrite
            // these classes to use a data base class.
            $db->query(sprintf("UPDATE media SET duration = %d WHERE mediaID = %d", $this->duration, $this->mediaid));

            $this->response->message = 'Edited the ' . $this->displayType;

            // Edit from the library - check to see if we are replacing this media in *all* layouts.
            if (Kit::GetParam('replaceInLayouts', _POST, _CHECKBOX) == 1)
                $this->ReplaceMediaInAllLayouts($mediaid, $this->mediaid, $this->duration);
        }

        return $this->response;
    }

    /**
     * Replace media in all layouts.
     * @param <type> $oldMediaId
     * @param <type> $newMediaId
     */
    private function ReplaceMediaInAllLayouts($oldMediaId, $newMediaId)
    {
        Kit::ClassLoader('region');
        $db =& $this->db;
        $count = 0;
        
        Debug::LogEntry($db, 'audit', sprintf('Replacing mediaid %s with mediaid %s in all layouts', $oldMediaId, $newMediaId), 'module', 'ReplaceMediaInAllLayouts');

        // Create a region object for later use
        $region = new region($db, $this->user);

        // Loop through a list of layouts this user has access to
        foreach($this->user->LayoutList() as $layout)
        {
            $layoutId = $layout['layoutid'];

            // Does this layout use the old media id?
            $SQL = sprintf("SELECT lklayoutmediaid, regionid FROM lklayoutmedia WHERE mediaid = %d and layoutid = %d", $oldMediaId, $layoutId);
            
            if (!$results = $db->query($SQL))
                return false;
            
            // Loop through each media link for this layout
            while ($row = $db->get_assoc_row($results))
            {
                // Get the LKID of the link between this layout and this media.. could be more than one?
                $lkId = $row['lklayoutmediaid'];
                $regionId = $row['regionid'];

                // Get the Type of this media
                if (!$type = $region->GetMediaNodeType($layoutId, '', '', $lkId))
                    continue;

                // Create a new media node use it to swap the nodes over
                Debug::LogEntry($db, 'audit', 'Creating new module with MediaID: ' . $newMediaId . ' LayoutID: ' . $layoutId . ' and RegionID: ' . $regionId, 'region', 'ReplaceMediaInAllLayouts');
                require_once('modules/' . $type . '.module.php');

                // Create a new module as if we were assigning it for the first time
                $module = new $type($db, $this->user, $newMediaId);

                // Sets the URI field
                $module->SetRegionInformation($layoutId, $regionId);

                // Get the media xml string to use in the swap.
                $mediaXmlString = $module->AsXml();

                // Swap the nodes
                if (!$region->SwapMedia($layoutId, $regionId, $lkId, $oldMediaId, $newMediaId, $mediaXmlString))
                    return false;

                // Update the LKID with the new media id
                $db->query("UPDATE lklayoutmedia SET mediaid = %d WHERE lklayoutmediaid = %d", $newMediaId, $row['lklayoutmediaid']);

                $count++;
            }
        }

        Debug::LogEntry($db, 'audit', sprintf('Replaced media in %d layouts', $count), 'module', 'ReplaceMediaInAllLayouts');
    }

    /**
     * Default GetName
     * @return
     */
    public function GetName()
    {
        $db =& $this->db;

        if ($this->name == '' && !$this->regionSpecific)
        {
            // Load what we know about this media into the object
            $SQL = "SELECT name FROM media WHERE mediaID = %d ";

            $this->name = $db->GetSingleValue(sprintf($SQL, $this->mediaid), 'name', _STRING);
        }

        Debug::LogEntry($db, 'audit', sprintf('Module name returned for MediaID: %s is %s', $this->mediaid, $this->name), 'Module', 'GetName');

        return $this->name;
    }

        /**
         * Preview code for a module
         * @param <type> $width
         * @param <type> $height
         */
        public function Preview($width, $height)
        {
            return '<div style="text-align:center;"><img alt="' . $this->type . ' thumbnail" src="img/forms/' . $this->type . '.png" /></div>';
        }

    /**
     * Is this media node region specific
     * @return <bool>
     */
    public function IsRegionSpecific()
    {
        return $this->regionSpecific;
    }

    /**
     * Permissions form
     */
    public function PermissionsForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = $this->response;
        $helpManager = new HelpManager($db, $user);

        if (!$this->auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this media'), E_USER_ERROR);

        // Form content
        $form = '<form id="LayoutPermissionsForm" class="XiboForm" method="post" action="index.php?p=module&mod=' . $this->type . '&q=Exec&method=Permissions">';
	$form .= '<input type="hidden" name="layoutid" value="' . $this->layoutid . '" />';
	$form .= '<input type="hidden" name="regionid" value="' . $this->regionid . '" />';
	$form .= '<input type="hidden" name="mediaid" value="' . $this->mediaid . '" />';
        $form .= '<div class="dialog_table">';
	$form .= '  <table style="width:100%">';
        $form .= '      <tr>';
        $form .= '          <th>' . __('Group') . '</th>';
        $form .= '          <th>' . __('View') . '</th>';
        $form .= '          <th>' . __('Edit') . '</th>';
        $form .= '          <th>' . __('Delete') . '</th>';
        $form .= '      </tr>';

        // List of all Groups with a view/edit/delete checkbox
        $SQL = '';
        $SQL .= 'SELECT `group`.GroupID, `group`.`Group`, View, Edit, Del, `group`.IsUserSpecific ';
        $SQL .= '  FROM `group` ';

        if ($this->assignedMedia)
        {
            $SQL .= '   LEFT OUTER JOIN lklayoutmediagroup ';
            $SQL .= '   ON lklayoutmediagroup.GroupID = group.GroupID ';
            $SQL .= sprintf(" AND lklayoutmediagroup.MediaID = '%s' AND lklayoutmediagroup.RegionID = '%s' AND lklayoutmediagroup.LayoutID = %d ", $this->mediaid, $this->regionid, $this->layoutid);
        }
        else
        {
            $SQL .= '   LEFT OUTER JOIN lkmediagroup ';
            $SQL .= '   ON lkmediagroup.GroupID = group.GroupID ';
            $SQL .= sprintf('       AND lkmediagroup.MediaID = %d ', $this->mediaid);
        }

        $SQL .= ' WHERE `group`.GroupID <> %d ';
        $SQL .= 'ORDER BY `group`.IsEveryone DESC, `group`.IsUserSpecific, `group`.`Group` ';

        $SQL = sprintf($SQL, $user->getGroupFromId($user->userid, true));

        Debug::LogEntry($db, 'audit', $SQL, 'module', 'PermissionsForm');

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get permissions for this layout'), E_USER_ERROR);
        }

        while($row = $db->get_assoc_row($results))
        {
            $groupId = $row['GroupID'];
            $group = ($row['IsUserSpecific'] == 0) ? '<strong>' . $row['Group'] . '</strong>' : $row['Group'];

            $form .= '<tr>';
            $form .= ' <td>' . $group . '</td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_view" ' . (($row['View'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_edit" ' . (($row['Edit'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_del" ' . (($row['Del'] == 1) ? 'checked' : '') . '></td>';
            $form .= '</tr>';
        }

        $form .= '</table>';
        $form .= '</div>';
        $form .= '</form>';

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('LayoutMedia', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=layout&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
        $response->AddButton(__('Save'), '$("#LayoutPermissionsForm").submit()');

        return $response;
    }

    /**
     * Permissions Edit
     */
    public function Permissions()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = $this->response;

        Kit::ClassLoader('mediagroupsecurity');
        Kit::ClassLoader('layoutmediagroupsecurity');

        $layoutId = Kit::GetParam('layoutid', _POST, _INT);
        $regionId = Kit::GetParam('regionid', _POST, _STRING);
        $mediaId = Kit::GetParam('mediaid', _POST, _STRING);
        $groupIds = Kit::GetParam('groupids', _POST, _ARRAY);

        if (!$this->auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this layout'), E_USER_ERROR);

        // Unlink all
        if ($this->assignedMedia)
        {
            $layoutMediaSecurity = new LayoutMediaGroupSecurity($db);
            if (!$layoutMediaSecurity->UnlinkAll($layoutId, $regionId, $mediaId))
                trigger_error(__('Unable to set permissions'));
        }
        else
        {
            $mediaSecurity = new MediaGroupSecurity($db);
            if (!$mediaSecurity->UnlinkAll($mediaId))
                trigger_error(__('Unable to set permissions'));
        }

        // Some assignments for the loop
        $lastGroupId = 0;
        $first = true;
        $view = 0;
        $edit = 0;
        $del = 0;

        // List of groupIds with view, edit and del assignments
        foreach($groupIds as $groupPermission)
        {
            $groupPermission = explode('_', $groupPermission);
            $groupId = $groupPermission[0];

            if ($first)
            {
                // First time through
                $first = false;
                $lastGroupId = $groupId;
            }

            if ($groupId != $lastGroupId)
            {
                // The groupId has changed, so we need to write the current settings to the db.
                // Link new permissions
                if ($this->assignedMedia)
                {
                    if (!$layoutMediaSecurity->Link($layoutId, $regionId, $mediaId, $lastGroupId, $view, $edit, $del))
                        trigger_error(__('Unable to set permissions'));
                }
                else
                {
                    if (!$mediaSecurity->Link($mediaId, $lastGroupId, $view, $edit, $del))
                        trigger_error(__('Unable to set permissions'));
                }

                // Reset
                $lastGroupId = $groupId;
                $view = 0;
                $edit = 0;
                $del = 0;
            }

            switch ($groupPermission[1])
            {
                case 'view':
                    $view = 1;
                    break;

                case 'edit':
                    $edit = 1;
                    break;

                case 'del':
                    $del = 1;
                    break;
            }
        }

        // Need to do the last one
        if (!$first)
        {
            if ($this->assignedMedia)
            {
                if (!$layoutMediaSecurity->Link($layoutId, $regionId, $mediaId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));
            }
            else
            {
                if (!$mediaSecurity->Link($mediaId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));
            }
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));

        return $response;
    }

    /**
     * Deletes the media files associated with this record
     * @return
     */
    private function DeleteMediaFiles($fileName)
    {
        $db =& $this->db;

        //Library location
        $databaseDir = Config::GetSetting($db, 'LIBRARY_LOCATION');

        //3 things to check for..
        //the actual file, the thumbnail, the background
        if (file_exists($databaseDir . $fileName))
        {
            unlink($databaseDir . $fileName);
        }

        if (file_exists($databaseDir . 'tn_' . $fileName))
        {
            unlink($databaseDir . 'tn_' . $fileName);
        }

        if (file_exists($databaseDir . 'bg_' . $fileName))
        {
            unlink($databaseDir . 'bg_' . $fileName);
        }

        return true;
    }

    public function GetResource()
    {
        return false;
    }
}
?>