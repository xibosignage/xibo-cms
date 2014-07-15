<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
	// Media information
	protected $db;
	protected $user;
	protected $region;
	protected $response;
    public $auth;
	protected $type;
  	public $displayType;

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
    protected $previewEnabled;
        
	protected $xml;

	protected $existingMedia;
	protected $assignedMedia;
	protected $deleteFromRegion;
    protected $showRegionOptions;
    protected $originalUserId;
    protected $storedAs;

    // Track the error state
	private $error;
    private $errorNo;
	private $errorMessage;

    protected $width;
    protected $height;

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
        require_once("lib/data/region.data.class.php");

        $this->db 	=& $db;
        $this->user 	=& $user;

        // Initialise the error state
        $this->error = false;
		$this->errorNo = 0;
		$this->errorMessage = '';

		// Initialise the module state
        $this->mediaid 	= $mediaid;
        $this->name 	= '';
        $this->layoutid = $layoutid;
        $this->regionid = $regionid;
        $this->lkid     = $lkid;

        // New region and response
        $this->region 	= new region($db);
        $this->response = new ResponseManager();

        $this->existingMedia 	= false;
        $this->assignedMedia = false;
        $this->deleteFromRegion = false;
        $this->assignable = true;
        $this->duration = '';

        // Members used by forms (routed through the CMS)
        $this->showRegionOptions = Kit::GetParam('showRegionOptions', _REQUEST, _INT, 1);

        // Determine which type this module is
        if (!$this->SetModuleInformation())
        	return false;

        Debug::LogEntry('audit', 'Module created with MediaID: ' . $mediaid . ' LayoutID: ' . $layoutid . ' and RegionID: ' . $regionid);

        // Either the information from the region - or some blanks
        if (!$this->SetMediaInformation($this->layoutid, $this->regionid, $this->mediaid, $this->lkid))
        	return false;

        return true;
    }

	/**
	 * Sets the module information
	 * @return
	 */
	final private function SetModuleInformation()
	{
		$db =& $this->db;
		$type = $this->type;

		if ($type == '')
			return $this->SetError(__('Unable to create Module [No type given] - please refer to the Module Documentation.'));

		$SQL = sprintf("SELECT * FROM module WHERE Module = '%s'", $db->escape_string($type));

		if (!$result = $db->query($SQL))
			return $this->SetError(__('Unable to create Module [Cannot find type in the database] - please refer to the Module Documentation.'));

		if ($db->num_rows($result) != 1)
			return $this->SetError(__('Unable to create Module [No registered modules of this type] - please refer to the Module Documentation.'));

		$row = $db->get_assoc_row($result);

		$this->schemaVersion 		= Kit::ValidateParam($row['SchemaVersion'], _INT);
		$this->regionSpecific 		= Kit::ValidateParam($row['RegionSpecific'], _INT);
		$this->validExtensionsText 	= Kit::ValidateParam($row['ValidExtensions'], _STRING);
		$this->validExtensions 		= explode(',', $this->validExtensionsText);
		$this->validExtensionsText	= str_replace(',', ', ', $this->validExtensionsText);
        $this->previewEnabled = Kit::ValidateParam($row['PreviewEnabled'], _INT);
        $this->assignable = Kit::ValidateParam($row['assignable'], _INT);

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

            //Debug::LogEntry('audit', 'Layout XML retrieved: ' . $layoutXml);

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
                return $this->SetError(__('Cannot find this media item. Please refresh the region options.'));

            // Create a Media node in the DOMDocument for us to replace
            $xmlDoc->loadXML('<root/>');

            $mediaNode = $mediaNodeXpath->item(0);
            $mediaNode->setAttribute('schemaVersion', $this->schemaVersion);

            // Get the width and height of this region (original width and height)
            $this->width = $mediaNode->parentNode->getAttribute('width');
            $this->height = $mediaNode->parentNode->getAttribute('height');

            $this->duration = $mediaNode->getAttribute('duration');
            
            // Get the LK id if we do not have one provided
            if ($lkid == '')
                $this->lkid = $mediaNode->getAttribute('lkid');

            // If the userId is blank, then set it to be the layout user id
            if (!$this->originalUserId = $mediaNode->getAttribute('userId'))
                $this->originalUserId = $db->GetSingleValue(sprintf("SELECT userid FROM layout WHERE layoutid = %d", $this->layoutid), 'userid', _INT);

            // Make sure we have permissions
            $this->auth = $this->user->MediaAssignmentAuth($this->originalUserId, $this->layoutid, $this->regionid, $this->mediaid, true);

            $mediaNode = $xmlDoc->importNode($mediaNode, true);
            $xmlDoc->documentElement->appendChild($mediaNode);

            Debug::LogEntry('audit', 'Existing Assigned Media XML is: \n ' . $xmlDoc->saveXML(), 'module', 'SetMediaInformation');

            $this->xml = $xmlDoc;

            // If we are some library media, then always set the URI as the StoredAs value, so that the preview and other items that rely
            // on StoredAs work.
            if ($this->regionSpecific == 0) {
                $this->storedAs = $this->GetOption('uri');
            }
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

                try {
                    $dbh = PDOConnect::init();
                
                    // Load what we know about this media into the object
                    $sth = $dbh->prepare('SELECT duration, name, UserId, storedAs FROM media WHERE mediaID = :media_id');
                    $sth->execute(array(
                            'media_id' => $mediaid
                        ));
                    
                    $rows = $sth->fetchAll();
                
                    if (count($rows) != 1) {
                        return $this->SetError(__('Unable to find media record with the provided ID'));
                    }

                    $this->duration = $rows[0]['duration'];
                    $this->name = $rows[0]['name'];
                    $this->originalUserId = $rows[0]['UserId'];
                    $this->storedAs = $rows[0]['storedAs'];
                }
                catch (Exception $e) {
                    
                    Debug::LogEntry('error', $e->getMessage());
                
                    return $this->SetError(__('Unable to find media record with the provided ID'));
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
            $this->xml = $xmlDoc;
        }

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
		
		if ($name == '') 
			return;

		Debug::LogEntry('audit', sprintf('IN with Name=%s and value=%s', $name, $value), 'module', 'Set Option');

		// Get the options node from this document
		$optionNodes = $this->xml->getElementsByTagName('options');
		// There is only 1
		$optionNode = $optionNodes->item(0);

		// Create a new option node
		$newNode = $this->xml->createElement($name, $value);

		Debug::LogEntry('audit', sprintf('Created a new Option Node with Name=%s and value=%s', $name, $value), 'module', 'Set Option');

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

		if ($name == '') 
			return false;

		// Check to see if we already have this option or not
		$xpath = new DOMXPath($this->xml);

		// Xpath for it
		$userOptions = $xpath->query('//options/' . $name);

		if ($userOptions->length == 0)
		{
			// We do not have an option - return the default
			Debug::LogEntry('audit', 'GetOption ' . $name . ': Not Set - returning default ' . $default);
			return $default;
		}
		else
		{
			// Replace the old node we found with XPath with the new node we just created
			Debug::LogEntry('audit', 'GetOption ' . $name . ': Set - returning: ' . $userOptions->item(0)->nodeValue);
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
		if ($xml == '') 
			return;

		// Load the XML we are given into its own document
		$rawNode = new DOMDocument();
		if (!$rawNode->loadXML('<raw>' . $xml . '</raw>'))
            return $this->SetError(__('There is an error in the HTML/XML'));

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
            Debug::LogEntry('audit', 'Updating Region');

            // By this point we expect to have a MediaID, duration
            $layoutid = $this->layoutid;
            $regionid = $this->regionid;

            if ($this->deleteFromRegion)
            {
                    // We call region delete
                    if (!$this->region->RemoveMedia($layoutid, $regionid, $this->lkid, $this->mediaid))
                    {
                            return $this->SetError(__("Unable to Remove this media from the Layout"));
                    }
            }
            else
            {
                    if ($this->assignedMedia)
                    {
                            // We call region swap with the same media id
                            if (!$this->region->SwapMedia($layoutid, $regionid, $this->lkid, $this->mediaid, $this->mediaid, $this->AsXml()))
                            {
                                    return $this->SetError(__("Unable to assign to the Region"));
                            }
                    }
                    else
                    {
                            // We call region add
                            if (!$this->region->AddMedia($layoutid, $regionid, $this->regionSpecific, $this->AsXml()))
                            {
                                    return $this->SetError(__("Error adding this media to the library"));
                            }
                    }
            }
            Debug::LogEntry('audit', 'Finished Updating Region');

            return true;
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
            $msgWarn = __('Are you sure you want to remove this item?');
            $msgWarnLost = __('It will be lost');
            $msgYes = __('Yes');
            $msgNo = __('No');

            if ($this->regionSpecific)
            {
                $form = <<<END
                <form id="MediaDeleteForm" class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=DeleteMedia">
                        <input type="hidden" name="mediaid" value="$mediaid">
                        <input type="hidden" name="layoutid" value="$layoutid">
                        <input type="hidden" name="regionid" value="$regionid">
                        <p>$msgWarn <span class="required">$msgWarnLost</span>.</p>
                </form>
END;
                $this->response->AddButton(__('No'), 'XiboFormRender("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
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
                        $options .= ',unassignall|' . __('Unassign from all Layouts');
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
                   $this->response->AddButton(__('No'), 'XiboFormRender("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');

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

        Kit::ClassLoader('Media');
        $mediaObject = new Media($db);

        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

        // Check permissions
        if (!$this->auth->del)
        {
            $this->response->SetError('You do not have permission to delete this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // Extra work if we are on a layout
        if ($layoutid != '')
        {
            if (!$this->ApiDeleteRegionMedia($layoutid, $regionid, $mediaid)) {
				$this->response->keepOpen = true;
            	$this->response->SetError($this->errorMessage);
				return $this->response;
            }
        }

        // Are we region specific media?
        if (!$this->regionSpecific)
        {
            $options = Kit::GetParam('options', _POST, _WORD);

            // Unassigning Media needs to remove it from all Layouts the user has permission for.
            if ($options == 'unassignall') {
                if (!$this->UnassignFromAll($mediaid)) {
                    $this->response->SetError($mediaObject->GetErrorMessage());
                    $this->response->keepOpen = true;
                    return $this->response;
                }
            }
            // If we are set to retire we retire
			else if ($options == 'retire')
			{
	            if (!$mediaObject->Retire($mediaid)) {
	            	$this->response->SetError($mediaObject->GetErrorMessage());
					$this->response->keepOpen = true;
					return $this->response;
	            }
			}
			// If we are set to delete, we delete
			else if ($options == 'delete')
			{
                if (!$mediaObject->Delete($mediaid)) {
            		$this->response->SetError($mediaObject->GetErrorMessage());
	            	$this->response->keepOpen = true;
					return $this->response;
                }
			}

            $this->response->message = __('Completed Successfully');
        }

        // We want to load the region timeline form back again
        if ($layoutid != '')
        {
            $this->response->loadForm = true;
            $this->response->loadFormUri= "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
        }
                
        return $this->response;
	}

	public function ApiDeleteRegionMedia($layoutid, $regionid, $mediaid) {
		$db =& $this->db;

		Kit::ClassLoader('layoutmediagroupsecurity');
        $security = new LayoutMediaGroupSecurity($db);

        if (!$security->UnlinkAll($layoutid, $regionid, $this->mediaid)) {
        	return $this->SetError($security->GetErrorMessage());
        }

        $this->deleteFromRegion = true;

        // Attempt to update the region
        if (!$this->UpdateRegion())
        	return false;

        return true;
	}

    /**
     * Unassign from all Layouts
     * @param [int] $mediaId [The MediaID to Unassign]
     */
    public function UnassignFromAll($mediaId) {

        // Get a list of layouts with this media id on them that this user has permission for.
        $layouts = $this->user->LayoutList('', 0, 0, '', $mediaId);

        // Create a media object for each, and call delete
        foreach ($layouts as $layout) {

            Debug::LogEntry('audit', 'Unassigning MediaID ' . $mediaId . ' from Layout: ' . $layout['layout'], 'module', 'UnassignFromAll');

            $mod = new $this->type($this->db, $this->user, $mediaId, $layout['layoutid'], $layout['regionid'], $layout['lklayoutmediaid']);

            // Call to delete region media
            if (!$mod->ApiDeleteRegionMedia($layout['layoutid'], $layout['regionid'], $mediaId)) {
                $this->response->keepOpen = true;
                $this->response->SetError($this->errorMessage);
                return $this->response;
            }
        }

        $this->response->message = __('Media unassigned from all Layouts');
        return $this->response;
    }

	/**
	 * Default AddForm
	 * @return
	 */
	public function AddForm()
	{
		$form = '<p>' . __('Not yet implemented by this module.') . '</p>';

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

        // Check we have room in the library
        $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

        if ($libraryLimit > 0)
        {
            $fileSize = $this->db->GetSingleValue('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', 'SumSize', _INT);

            if (($fileSize / 1024) > $libraryLimit)
                trigger_error(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit), E_USER_ERROR);
        }

        // Would like to get the regions width / height
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;

        // Set the Session / Security information
        $sessionId = session_id();
        $securityToken = CreateFormToken();
        $backgroundImage = Kit::GetParam('backgroundImage', _GET, _BOOL, false);

        $session->setSecurityToken($securityToken);

        // Set some defaults based on the type of media we are
        // TODO: this should be passed in
        switch ($this->type) {
            case 'video':
            case 'localvideo':
            case 'genericfile':
                $defaultDuration = 0;
                break;

            case 'image':
                $defaultDuration = Config::GetSetting('jpg_length');
                break;

            case 'flash':
                $defaultDuration = Config::GetSetting('swf_length');
                break;

            case 'powerpoint':
                $defaultDuration = Config::GetSetting('ppt_length');
                break;

            default:
                $defaultDuration = '';
        }
        

        // Save button is different depending on if we are on a region or not
        if ($regionid != '' && $this->showRegionOptions)
        {
            setSession('content','mediatype', $this->type);

            $this->response->AddButton(__('Assign to Layout'), 'XiboAssignToLayout(' . $layoutid . ',"' . $regionid . '")');
            $this->response->AddButton(__('View Library'), 'XiboSwapDialog("index.php?p=content&q=LibraryAssignForm&layoutid=' . $layoutid . '&regionid=' . $regionid . '")');
            $this->response->AddButton(__('Close'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
		}
        elseif ($regionid != '' && !$this->showRegionOptions)
        {
        	$this->response->AddButton(__('Close'), 'XiboDialogClose()');
		}
        elseif ($backgroundImage)
        {
        	$this->response->AddButton(__('Close'), 'XiboSwapDialog("index.php?p=layout&q=BackgroundForm&modify=true&layoutid=' . $layoutid . '")');

        	// Background override url is used on the theme to add a button next to each uploaded file (if in background override)
			Theme::Set('background_override_url', "index.php?p=layout&q=BackgroundForm&modify=true&layoutid=$layoutid&backgroundOveride=");
        }
        else
        {
        	$this->response->AddButton(__('Close'), 'XiboSwapDialog("index.php?p=content&q=displayForms&sp=add");XiboRefreshAllGrids()');
        }

        // Setup the theme
		Theme::Set('form_upload_id', 'fileupload');
        Theme::Set('form_action', 'index.php?p=content&q=JqueryFileUpload&type=' . $this->type);
		Theme::Set('form_meta', '<input type="hidden" id="PHPSESSID" value="' . $sessionId . '" /><input type="hidden" id="SecurityToken" value="' . $securityToken . '" /><input type="hidden" name="type" value="' . $this->type . '"><input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" name="regionid" value="' . $regionid . '">');
		Theme::Set('form_valid_ext', '/(\.|\/)' . implode('|', $this->validExtensions) . '$/i');
		Theme::Set('form_max_size', Kit::ReturnBytes($this->maxFileSize));
		Theme::Set('valid_extensions', sprintf(__('This form accepts: %s files up to a maximum size of %s'), $this->validExtensionsText, $this->maxFileSize));
		Theme::Set('default_duration', $defaultDuration);

		$form = Theme::RenderReturn('library_form_media_add');

        $this->response->html = $form;
        $this->response->dialogTitle = sprintf(__('Add New %s'), __($this->displayType));
        $this->response->dialogSize = true;
        $this->response->dialogWidth = '450px';
        $this->response->dialogHeight = '280px';
        $this->response->callBack = 'MediaFormInitUpload';
        $this->response->dialogClass = 'modal-big';

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
        	// log the error
            trigger_error($db->error());
            trigger_error(__('Error querying for the Media information'), E_USER_ERROR);
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

            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        elseif ($regionid != '' && !$this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->AddButton(__('Save'), '$("#EditLibraryBasedMedia").submit()');

        $durationFieldEnabled = ($this->auth->modifyPermissions) ? '' : ' readonly';

		// Setup the theme
        Theme::Set('form_id', 'EditLibraryBasedMedia');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
		Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" name="regionid" value="' . $regionid . '"><input type="hidden" name="mediaid" value="' . $mediaid . '"><input type="hidden" name="lkid" value="' . $lkid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="txtFileName" name="txtFileName" readonly="true" /><input type="hidden" name="hidFileID" id="hidFileID" value="" />');

		Theme::Set('form_upload_id', 'file_upload');
        Theme::Set('form_upload_action', 'index.php?p=content&q=FileUpload');
		Theme::Set('form_upload_meta', '<input type="hidden" id="PHPSESSID" value="' . $sessionId . '" /><input type="hidden" id="SecurityToken" value="' . $securityToken . '" /><input type="hidden" name="MAX_FILE_SIZE" value="' . $this->maxFileSizeBytes . '" />');

		Theme::Set('name', $name);
		Theme::Set('duration', $this->duration);
		Theme::Set('is_duration_field_enabled', $durationFieldEnabled);
		Theme::Set('valid_extensions', 'This form accepts: ' . $this->validExtensionsText . ' files up to a maximum size of ' . $this->maxFileSize);
		Theme::Set('is_replace_field_checked', ((Config::GetSetting('LIBRARY_MEDIA_UPDATEINALL_CHECKB') == 'Checked') ? 'checked' : ''));
        Theme::Set('is_assignable', $this->assignable);

		$form = Theme::RenderReturn('library_form_media_edit');

        $this->response->html = $form;
        $this->response->dialogTitle = 'Edit ' . $this->displayType;
        $this->response->dialogSize = true;
        $this->response->dialogWidth = '450px';
        $this->response->dialogHeight = '280px';

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

	/**
	 * Adds Library Media
	 *  called from inside the FileUpload Handler
	 * @param [type] $fileId    [description]
	 * @param [type] $mediaName [description]
	 * @param [type] $duration  [description]
	 * @param [type] $fileName  [description]
	 * @return [int] [The ID of the Media Added]
	 */
    public function AddLibraryMedia($fileId, $mediaName, $duration, $fileName)
    {
        $db =& $this->db;
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;

        // The media name might be empty here, because the user isn't forced to select it
        if ($mediaName == '')
        	$mediaName = $fileName;

        // Hand off to the media module
        Kit::ClassLoader('media');
        $mediaObject = new Media($db);

        if (!$mediaid = $mediaObject->Add($fileId, $this->type, $mediaName, $duration, $fileName, $this->user->userid)) {
        	return $this->SetError($mediaObject->GetErrorMessage());
        }

        Debug::LogEntry('audit', 'Returned MediaId: ' . $mediaid, 'module', 'AddLibraryMedia');

        // Required Attributes
        $this->mediaid	= $mediaid;
        $this->duration = $duration;

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT StoredAs FROM `media` WHERE mediaid = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaid
                ));

            if (!$row = $sth->fetch())
                return $this->SetError(__('Unable to get the storage name'));
            
            // Find out what we stored this item as
            $storedAs = Kit::ValidateParam($row['StoredAs'], _STRING);          
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
        // Any Options
        $this->SetOption('uri', $storedAs);

        // Should have built the media object entirely by this time
        if ($regionid != '')
        {
            // This saves the Media Object to the Region
            if (!$this->UpdateRegion())
            	return false;
        }

        // Return the ID of this media
        return $mediaid;
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

        // Hand off to the media module
        Kit::ClassLoader('media');
        $mediaObject = new Media($db);

        // Stored As from the XML
        $storedAs = $this->GetOption('uri');

        // File data
        $tmpName = Kit::GetParam('hidFileID', _POST, _STRING);
        $name = Kit::GetParam('name', _POST, _STRING);
        
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

        // Revise this file?
        if ($tmpName != '') {

        	Debug::LogEntry('audit', 'Uploading a new revision', 'module', 'EditLibraryMedia');
        	
            // File name and extension (orignial name)
            $fileName = Kit::GetParam('txtFileName', _POST, _STRING);

            if ($name == '')
            	$name = $fileName;

            if (!$new_mediaid = $mediaObject->FileRevise($mediaid, $tmpName, $fileName)) {
            	$this->response->SetError($mediaObject->GetErrorMessage());
	            $this->response->keepOpen = true;
	            return $this->response;
            }            	

            // Are we on a region
            if ($regionid != '')
            {
                Kit::ClassLoader('layoutmediagroupsecurity');

                $security = new LayoutMediaGroupSecurity($db);
                $security->Copy($layoutid, $regionid, $mediaid, $new_mediaid);
            }

            // Required Attributes
        	$this->mediaid	= $new_mediaid;
        	
        	// Find out what we stored this item as
        	$storedAs = $db->GetSingleValue(sprintf("SELECT StoredAs FROM `media` WHERE mediaid = %d", $new_mediaid), 'StoredAs', _STRING);
        	$this->SetOption('uri', $storedAs);

        	Debug::LogEntry('audit', 'New revision uploaded: ' . $storedAs, 'module', 'EditLibraryMedia');
        }

        // Edit the media record
        if (!$mediaObject->Edit($this->mediaid, $name, $this->duration, $userid)) {
        	$this->response->SetError($mediaObject->GetErrorMessage());
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Should have built the media object entirely by this time
        if ($regionid != '' && $this->showRegionOptions)
        {
            // This saves the Media Object to the Region
            $this->UpdateRegion();

            $this->response->loadForm	 = true;
            $this->response->loadFormUri = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";;
        }
        elseif ($regionid != '' && !$this->showRegionOptions)
        {
            $this->UpdateRegion();
            $this->response->loadForm = false;
        }
        else
        {
            $this->response->message = 'Edited the ' . $this->displayType;
        }

        // Edit from the library - check to see if we are replacing this media in *all* layouts.
        if (Kit::GetParam('replaceInLayouts', _POST, _CHECKBOX) == 1)
            $this->ReplaceMediaInAllLayouts($mediaid, $this->mediaid, $this->duration);

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
        
        Debug::LogEntry('audit', sprintf('Replacing mediaid %s with mediaid %s in all layouts', $oldMediaId, $newMediaId), 'module', 'ReplaceMediaInAllLayouts');

        // Create a region object for later use
        $region = new region($db);

        try {
            $dbh = PDOConnect::init();
        
            // Some update statements to use
            $sth = $dbh->prepare('SELECT lklayoutmediaid, regionid FROM lklayoutmedia WHERE mediaid = :media_id AND layoutid = :layout_id');
            $sth_update = $dbh->prepare('UPDATE lklayoutmedia SET mediaid = :media_id WHERE lklayoutmediaid = :lklayoutmediaid');

            // Loop through a list of layouts this user has access to
            foreach($this->user->LayoutList() as $layout)
            {
                $layoutId = $layout['layoutid'];
                
                // Does this layout use the old media id?
                $sth->execute(array(
                        'media_id' => $oldMediaId,
                        'layout_id' => $layoutId
                    ));

                $results = $sth->fetchAll();
                
                if (count($results) <= 0)
                    continue;

                Debug::LogEntry('audit', sprintf('%d linked media items for layoutid %d', count($results), $layoutId), 'module', 'ReplaceMediaInAllLayouts');
                
                // Loop through each media link for this layout
                foreach ($results as $row)
                {
                    // Get the LKID of the link between this layout and this media.. could be more than one?
                    $lkId = $row['lklayoutmediaid'];
                    $regionId = $row['regionid'];
    
                    // Get the Type of this media
                    if (!$type = $region->GetMediaNodeType($layoutId, '', '', $lkId))
                        continue;
    
                    // Create a new media node use it to swap the nodes over
                    Debug::LogEntry('audit', 'Creating new module with MediaID: ' . $newMediaId . ' LayoutID: ' . $layoutId . ' and RegionID: ' . $regionId, 'region', 'ReplaceMediaInAllLayouts');
                    require_once('modules/' . $type . '.module.php');
    
                    // Create a new module as if we were assigning it for the first time
                    if (!$module = new $type($db, $this->user, $newMediaId))
                        return false;
    
                    // Sets the URI field
                    if (!$module->SetRegionInformation($layoutId, $regionId))
                        return false;
    
                    // Get the media xml string to use in the swap.
                    $mediaXmlString = $module->AsXml();
    
                    // Swap the nodes
                    if (!$region->SwapMedia($layoutId, $regionId, $lkId, $oldMediaId, $newMediaId, $mediaXmlString))
                        return false;
    
                    // Update the LKID with the new media id
                    $sth_update->execute(array(
                        'media_id' => $newMediaId,
                        'lklayoutmediaid' => $row['lklayoutmediaid']
                    ));
    
                    $count++;
                }
            }
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }

        Debug::LogEntry('audit', sprintf('Replaced media in %d layouts', $count), 'module', 'ReplaceMediaInAllLayouts');
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

        Debug::LogEntry('audit', sprintf('Module name returned for MediaID: %s is %s', $this->mediaid, $this->name), 'Module', 'GetName');

        return $this->name;
    }

        /**
         * Preview code for a module
         * @param <type> $width
         * @param <type> $height
         */
        public function Preview($width, $height)
        {
            return '<div style="text-align:center;"><img alt="' . $this->type . ' thumbnail" src="theme/default/img/forms/' . $this->type . '.gif" /></div>';
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
     * Default code for the hover preview
     */
    public function HoverPreview()
    {
        $msgType = __('Type');
        $msgName = __('Name');
        $msgDuration = __('Duration');

        // Default Hover window contains a thumbnail, media type and duration
        $output = '<div class="well">';
        $output .= '<div class="preview-module-image"><img alt="' . $this->displayType . ' thumbnail" src="theme/default/img/forms/' . $this->type . '.gif"></div>';
        $output .= '<div class="info">';
        $output .= '    <ul>';
        $output .= '    <li>' . $msgType . ': ' . $this->displayType . '</li>';

        if (!$this->regionSpecific)
            $output .= '    <li>' . $msgName . ': ' . $this->name . '</li>';

        $output .= '    <li>' . $msgDuration . ': ' . $this->duration . ' ' . __('seconds') . '</li>';
        $output .= '    </ul>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    public function ImageThumbnail()
    {
        return '<img alt="' . $this->displayType . ' thumbnail" src="theme/default/img/forms/' . $this->type . '.gif">';
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
	$form .= '  <table class="table table-bordered">';
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

        Debug::LogEntry('audit', $SQL, 'module', 'PermissionsForm');

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
        $form .= '</form>';

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . (($this->layoutid != 0) ? $helpManager->Link('LayoutMedia', 'Permissions') : $helpManager->Link('Media', 'Permissions')) . '")');
        
        if ($this->assignedMedia) {
        	$response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
    	}
    	else {
			$response->AddButton(__('Cancel'), 'XiboDialogClose()');
    	}

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
        $databaseDir = Config::GetSetting('LIBRARY_LOCATION');

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

    public function GetResource($displayId = 0)
    {
        return false;
    }
    
    /**
     * Form to Edit a transition
     */
    public function TransitionEditForm()
    {
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this media.');
            $this->response->keepOpen = false;
            return $this->response;
        }
        
        // Are we dealing with an IN or an OUT
        $type = Kit::GetParam('type', _REQUEST, _WORD);
        
        switch ($type)
        {
            case 'in':
                $transition = $this->GetOption('transIn');
                $duration = $this->GetOption('transInDuration', 0);
                $direction = $this->GetOption('transInDirection');
                
                break;
            
            case 'out':
                $transition = $this->GetOption('transOut');
                $duration = $this->GetOption('transOutDuration', 0);
                $direction = $this->GetOption('transOutDirection');
                
                break;
            
            default:
                trigger_error(_('Unknown transition type'), E_USER_ERROR);
        }
        
        // Add none to the list
        $transitions = $this->user->TransitionAuth($type);
        $transitions[] = array('code' => '', 'transition' => 'None', 'class' => '');
        
        // Prepare a list of options
        $transitionDropdown = Kit::SelectList('transitionType', $transitions, 'code', 'transition', $transition, '', 'class');
        
        // Compass points for direction
        $compassPoints = array(
            array('id' => 'N', 'name' => __('North')), 
            array('id' => 'NE', 'name' => __('North East')), 
            array('id' => 'E', 'name' => __('East')), 
            array('id' => 'SE', 'name' => __('South East')), 
            array('id' => 'S', 'name' => __('South')), 
            array('id' => 'SW', 'name' => __('South West')), 
            array('id' => 'W', 'name' => __('West')),
            array('id' => 'NW', 'name' => __('North West'))
        );
        
        // Prepare a list of compass points
        $directionDropdown = Kit::SelectList('transitionDirection', $compassPoints, 'id', 'name', $direction);
        
        // Some messages for the form
        $msgTransition = __('What transition should be applied to this media item?');
        $msgDuration = __('The duration for this transition, in milliseconds.');
        $msgDirection = __('The direction for this transition.');
        
        // Construct the form
        $form = <<<END
        <form id="TransitionForm" class="XiboTextForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=TransitionEdit">
            <input type="hidden" name="type" value="$type">
            <input type="hidden" name="layoutid" value="$this->layoutid">
            <input type="hidden" name="mediaid" value="$this->mediaid">
            <input type="hidden" name="lkid" value="$this->lkid">
            <input type="hidden" id="iRegionId" name="regionid" value="$this->regionid">
            <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" /> 
            
            <table>
                <tr>
                    <td><label for="tranisitionType" title="$msgTransition">$msgTransition</label></td>
                    <td>$transitionDropdown</td>
                </tr>
                <tr class="transitionDuration">
                    <td><label for="transitionDuration">$msgDuration</label></td>
                    <td><input type="text" class="numeric" name="transitionDuration" id="transitionDuration" value="$duration" /></td>
                </tr>
                <tr class="transitionDirection">
                    <td><label for="transitionDirection">$msgDirection</label></td>
                    <td>$directionDropdown</td>
                </tr>
            </table>
        </form>
END;
        
        // Decide where the cancel button will take us
        if ($this->showRegionOptions)
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
        else
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');

        // Always include the save button
        $this->response->AddButton(__('Save'), '$("#TransitionForm").submit()');
        
        // Output the form and dialog
        $this->response->html = $form;
        $this->response->callBack = 'transitionFormLoad';
        $this->response->dialogTitle = 'Edit ' . $type . ' Transition for ' . $this->displayType;
        $this->response->dialogSize = true;
        $this->response->dialogWidth = '450px';
        $this->response->dialogHeight = '280px';
        
        return $this->response;
    }
    
    /**
     * Edit a transition
     */
    public function TransitionEdit()
    {
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this media.');
            $this->response->keepOpen = false;
            return $this->response;
        }
        
        // Get the transition type
        $transitionType = Kit::GetParam('transitionType', _POST, _WORD);
        $duration = Kit::GetParam('transitionDuration', _POST, _INT, 0);
        $direction = Kit::GetParam('transitionDirection', _POST, _WORD, '');
        $type = Kit::GetParam('type', _REQUEST, _WORD);
        
        switch ($type)
        {
            case 'in':
                $this->SetOption('transIn', $transitionType);
                $this->SetOption('transInDuration', $duration);
                $this->SetOption('transInDirection', $direction);
                
                break;
            
            case 'out':
                $this->SetOption('transOut', $transitionType);
                $this->SetOption('transOutDuration', $duration);
                $this->SetOption('transOutDirection', $direction);
                
                break;
            
            default:
                trigger_error(_('Unknown transition type'), E_USER_ERROR);
        }
        
        // This saves the Media Object to the Region
        $this->UpdateRegion();
        
        if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = 'index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions';
        }

        return $this->response;
    }
    
    /**
     * Get the the Transition for this media
     * @param string Either "in" or "out"
     */
    public function GetTransition($type)
    {
        switch ($type)
        {
            case 'in':
                $code = $this->GetOption('transIn');
                break;
            
            case 'out':
                $code = $this->GetOption('transOut');
                break;
            
            default:
                trigger_error(_('Unknown transition type'), E_USER_ERROR);
        }
        
        if ($code == '')
            return __('None');
        
        // Look up the real transition name
        $transition = $this->user->TransitionAuth('', $code);
        
        return __($transition[0]['transition']);
    }

    /**
     * Add/Edit via setting the entire XLF
     * @param [type] $xml [description]
     */
    public function SetMediaXml($xml) {
    	$db =& $this->db;

        // Validation
        if ($xml == '')
            return $this->SetError(__('No XLF provided'));

        Debug::LogEntry('audit', 'Setting the media XML for this item directly', 'module', 'SetMediaXml');

    	// Load the XML into a document
    	$xmlDoc = new DOMDocument();

    	if (!$xmlDoc->loadXML($xml))
            return $this->SetError(__('Invalid XLF'));

        Debug::LogEntry('audit', 'Provided XML Loaded', 'module', 'SetMediaXml');

    	// Validate the XML Document
    	if (!$this->ValidateMediaXml($xmlDoc))
    		return false;

    	// Switch the XML with the XML currently held for this media node
    	$this->xml = $xmlDoc;

    	// Call region update
    	if (!$this->UpdateRegion())
    		return false;

        // Send back the media id
    	return $this->mediaid;
    }

    protected function ValidateMediaXml($xmlDoc) {
        $db =& $this->db;

        Debug::LogEntry('audit', 'Validating provided XLF', 'module', 'ValidateMediaXml');

    	// Compare the XML we have been given, with the XML of the existing media item OR compare as a new item
    	$mediaNodes = $xmlDoc->getElementsByTagName('media');

    	if ($mediaNodes->length > 1)
    		return $this->SetError(__('Too many media nodes'));

    	$mediaNode = $mediaNodes->item(0);

    	// Do some basic checks regardless of whether it is an add or edit
    	// Check the schema version
		if ($mediaNode->getAttribute('schemaVersion') != $this->schemaVersion)
			return $this->SetError(__('SchemaVersion does not match'));

		// Check the type
		if ($mediaNode->getAttribute('type') != $this->type)
			return $this->SetError(__('Media Type does not match'));

		// Do we have a new item or an existing item
    	if ($this->assignedMedia) {
    		// An existing item
    		Debug::LogEntry('audit', 'An existing media entry', 'module', 'ValidateMediaXml');

    		// Check the ID
    		if ($mediaNode->getAttribute('id') != $this->mediaid)
    			return $this->SetError(sprintf(__('ID does not match [%s vs %s]'), $mediaNode->getAttribute('id'), $this->mediaid));

    		// Check that the "owner" userId on the media item has not changed
    		if ($mediaNode->getAttribute('userId') != $this->originalUserId)
    			return $this->SetError(__('UserId does not match'));
    	}
    	else {
    		// A new item
    		Debug::LogEntry('audit', 'A new media entry', 'module', 'ValidateMediaXml');
    		
    		// New media items may not have a media id on them (region media is born without an ID)
    		if ($this->regionSpecific == 1) {
    			// Create a new media id and set it on this object
    			$this->mediaid = md5(uniqid());
				$mediaNode->setAttribute('id', $this->mediaid);
    		}
    		else {
    			// We already know that the media id exists, now check it matches
    			if ($mediaNode->getAttribute('id') != $this->mediaid)
	    			return $this->SetError(__('ID does not match'));
    		}

            // The user ID should be that of the new user
            $this->originalUserId = $this->user->userid;
    	}

    	// Check we have some core attributes (and set them on the media object - this gives us the new values to save)
    	// (we have already validated that the media id and the type are the same, we dont need to check them again)
		$this->duration = $mediaNode->getAttribute('duration');

		if ($this->duration == '' || !is_numeric($this->duration))
			return $this->SetError(__('Duration not provided or not a number'));

        if ($this->duration < 0)
            return $this->SetError(__('Cannot be less than zero'));

		// The "core" items appear to be ok
		return true;
    }

    /**
	 * Gets the error state
	 * @return 
	 */
	public function IsError()
	{
		return $this->error;
	}
	
	/**
	 * Gets the Error Number
	 * @return 
	 */
	public function GetErrorNumber()
	{
		return $this->errorNo;
	}
	
	/**
	 * Gets the Error Message
	 * @return 
	 */
	public function GetErrorMessage()
	{
		return $this->errorMessage;
	}
	
	/**
	 * Sets the Error for this Data object
	 * @return 
	 * @param $errNo Object
	 * @param $errMessage Object
	 */
	protected function SetError($errNo, $errMessage = '')
	{
		$this->error		= true;

		// Is an error No provided?
		if (!is_numeric($errNo)) {
			$errMessage = $errNo;
			$errNo = -1;
		}

		$this->errorNo 		= $errNo;
		$this->errorMessage	= $errMessage;
		
		Debug::LogEntry('audit', sprintf('Module Class: Error Number [%d] Error Message [%s]', $errNo, $errMessage), 'Media Module', 'SetError');

        // Return false so that we can use this method as the return call for parent methods
		return false;
	}

    public function IsValid() {
        // Defaults: Stored media is valid, region specific is unknown
        return ($this->regionSpecific) ? 0 : 1;
    }
    
    /**
	 * Return filebased media items to the browser for Download/Preview
	 * @return 
	 * @param $download Boolean
	 */
    public function ReturnFile($fileName = '') {
        // Return the raw flash file with appropriate headers
    	$library = Config::GetSetting("LIBRARY_LOCATION");

        # If we weren't passed in a filename then use the default
    	if ($fileName == '') {
            $fileName = $library . $this->storedAs;
        }
        
        $download = Kit::GetParam('download', _REQUEST, _BOOLEAN, false);

        $size = filesize($fileName);
        
        if ($download) {
            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: Binary"); 
            header("Content-disposition: attachment; filename=\"" . basename($fileName) . "\"");
        }
        else {
            $fi = new finfo( FILEINFO_MIME_TYPE );
            $mime = $fi->file( $fileName );
            header("Content-Type: {$mime}");
        }

        //Output a header
        header('Pragma: public');
        header('Cache-Control: max-age=86400');
        header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
        header('Content-Length: ' . $size);
        
        // Send via Apache X-Sendfile header?
        if (Config::GetSetting('SENDFILE_MODE') == 'Apache') {
            header("X-Sendfile: $fileName");
            exit();
        }
        
        // Send via Nginx X-Accel-Redirect?
        if (Config::GetSetting('SENDFILE_MODE') == 'Nginx') {
            header("X-Accel-Redirect: /download/" . $this->storedAs);
            exit();
        }
        
        // Return the file with PHP
        // Disable any buffering to prevent OOM errors.
        @ob_end_clean();
        @ob_end_flush();
        readfile($fileName);
    }
}
?>
