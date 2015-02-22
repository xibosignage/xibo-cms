<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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

abstract class Module implements ModuleInterface
{
    // Module Information
    /**
     * @var \Xibo\Entity\Module $module
     */
    protected $module;

    /**
     * @var \Xibo\Entity\Widget $widget Widget
     */
    private $widget;

    /**
     * @var PermissionManager $auth Widget Permissions
     */
    protected $auth;

    // The Schema Version of this code
    protected $codeSchemaVersion = -1;

    /**
     * Set the Widget
     * @param \Xibo\Entity\Widget $widget
     */
    final public function setWidget($widget)
    {
        $this->widget = $widget;
    }

    /**
     * Set Widget Permissions
     * @param PermissionManager $auth
     */
    final public function setPermissions($auth)
    {
        $this->auth = $auth;
    }

    /**
     * Set the Module
     * @param \Xibo\Entity\Module $module
     */
    final public function setModule($module)
    {
        $this->module = $module;
    }

    /**
     * Save the Module
     */
    protected final function saveSettings()
    {
        // Save
        try {
            $this->module->save();
        }
        catch (Exception $e) {
            trigger_error(__('Cannot Save Settings'), E_USER_ERROR);
        }
    }

    /**
     * Set Option
     * @param string $name
     * @param string $value
     */
    final protected function SetOption($name, $value)
    {
        $this->widget->setOptionValue($name, 'attrib', $value);
    }

    /**
     * Get Option or Default
     * @param string $name
     * @param mixed[Optional] $default
     */
    final protected function GetOption($name, $default = null)
    {
        $this->widget->getOptionValue($name, $default);
    }

    /**
     * Get Raw Node Value
     * @param $name
     * @param $default
     */
    final protected function getRawNode($name, $default)
    {
        $this->widget->getOptionValue($name, $default);
    }

    /**
     * Set Raw Node Value
     * @param $name
     * @param $value
     */
    final protected function setRawNode($name, $value)
    {
        $this->widget->setOptionValue($name, 'cdata', $value);
    }

    /**
     * Get WidgetId
     * @return int
     */
    final protected function getWidgetId()
    {
        return $this->widget->widgetId;
    }

    /**
     * Save the Widget
     */
    final protected function saveWidget()
    {
        $this->widget->save();
    }

    /**
     * Delete Form
     * All widgets are deleted in a generic way
     */
    public function DeleteForm()
    {
        $response = new ResponseManager();
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Media', 'Delete') . '")');

        // Can this user delete?
        if (!$this->auth->del)
            throw new Exception('You do not have permission to delete this media.');

        Theme::Set('form_id', 'MediaDeleteForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->module->type . '&q=Exec&method=DeleteMedia');
        Theme::Set('form_meta', '<input type="hidden" name="widgetId" value="' . $this->getWidgetId() . '"><input type="hidden" name"regionId" value="' . Kit::GetParam('regionId', _POST, _INT) . '">');
        $formFields = array(
            FormManager::AddMessage(__('Are you sure you want to remove this widget?')),
            FormManager::AddMessage(__('This action cannot be undone.')),
        );

        // If we have linked media items, should we also delete them?
        if (count($this->widget->mediaIds) > 0) {
            $formFields[] = FormManager::AddCheckbox('deleteMedia', __('Also delete from the Library?'), 0, __('This widget is linked to Media in the Library. Check this option to also delete that Media.'), 'd');
        }

        Theme::Set('form_fields', $formFields);
        $form = Theme::RenderReturn('form_render');

        $response->SetFormRequestResponse($form, __('Delete Widget'), '300px', '200px');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#LayoutDeleteForm").submit()');
        $response->Respond();
    }

    /**
     * Delete Widget
     */
    public function DeleteMedia()
    {
        $response = new ResponseManager();

        // Can this user delete?
        if (!$this->auth->del)
            throw new Exception('You do not have permission to delete this media.');

        // Delete associated media?
        if (Kit::GetParam('deleteMedia', _POST, _CHECKBOX) == 1) {
            $media = new Media();
            foreach ($this->widget->mediaIds as $mediaId) {
                $media->Delete($mediaId);
            }
        }

        // Delete the widget
        $this->widget->delete();

        // Return
        $response->SetFormSubmitResponse(__('The Widget has been Deleted'));
        $response->loadForm = true;
        $response->loadFormUri= 'index.php?p=timeline&q=Timeline&regionid=' . Kit::GetParam('regionId', _POST, _INT);
        $response->Respond();
    }



    protected function EditFormForLibraryMedia($extraFormFields = NULL)
    {
        global $session;
        $db =& $this->db;
        $user =& $this->user;
        
        if ($response == null)
            $response = new ResponseManager();

        // Would like to get the regions width / height
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;
        $lkid = $this->lkid;
        $userid	= $this->user->userid;

        // Can this user delete?
        if (!$this->auth->edit)
        {
            $response->SetError('You do not have permission to edit this media.');
            $response->keepOpen = false;
            return $response;
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

            $response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        elseif ($regionid != '' && !$this->showRegionOptions)
        {
            $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
        else
        {
            $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $response->AddButton(__('Save'), '$("#EditLibraryBasedMedia").submit()');

        // Setup the theme
        Theme::Set('form_id', 'EditLibraryBasedMedia');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" name="regionid" value="' . $regionid . '"><input type="hidden" name="mediaid" value="' . $mediaid . '"><input type="hidden" name="lkid" value="' . $lkid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="txtFileName" name="txtFileName" readonly="true" /><input type="hidden" name="hidFileID" id="hidFileID" value="" />');

        Theme::Set('form_upload_id', 'file_upload');
        Theme::Set('form_upload_action', 'index.php?p=content&q=FileUpload');
        Theme::Set('form_upload_meta', '<input type="hidden" id="PHPSESSID" value="' . $sessionId . '" /><input type="hidden" id="SecurityToken" value="' . $securityToken . '" /><input type="hidden" name="MAX_FILE_SIZE" value="' . $this->maxFileSizeBytes . '" />');

        Theme::Set('prepend', Theme::RenderReturn('form_file_upload_single'));

        $formFields = array();
        $formFields[] = FormManager::AddMessage(sprintf(__('This form accepts: %s files up to a maximum size of %s'), $this->validExtensionsText, $this->maxFileSize));
        
        $formFields[] = FormManager::AddText('name', __('Name'), $name, 
            __('The Name of this item - Leave blank to use the file name'), 'n');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this item should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        $formFields[] = FormManager::AddText('tags', __('Tags'), $this->tags, 
            __('Tag this media. Comma Separated.'), 'n');

        if ($this->assignable) {
            $formFields[] = FormManager::AddCheckbox('replaceInLayouts', __('Update this media in all layouts it is assigned to?'), 
                ((Config::GetSetting('LIBRARY_MEDIA_UPDATEINALL_CHECKB') == 'Checked') ? 1 : 0), 
                __('Note: It will only be replaced in layouts you have permission to edit.'), 
                'r');
        }

        $formFields[] = FormManager::AddCheckbox('deleteOldVersion', __('Delete the old version?'), 
                ((Config::GetSetting('LIBRARY_MEDIA_UPDATEINALL_CHECKB') == 'Checked') ? 1 : 0), 
                __('Completely remove the old version of this media item if a new file is being uploaded.'), 
                '');

        // Add in any extra form fields we might have provided by the super-class
        if ($extraFormFields != NULL && is_array($extraFormFields)) {
            foreach($extraFormFields as $field) {
                $formFields[] = $field;
            }
        }

        Theme::Set('form_fields', $formFields);

        $response->html = Theme::RenderReturn('form_render');
        $response->dialogTitle = 'Edit ' . $this->displayType;
        $response->dialogSize = true;
        $response->dialogWidth = '450px';
        $response->dialogHeight = '280px';

        return $response;
    }

    protected function EditLibraryMedia()
    {
        $response = new ResponseManager();
        $db =& $this->db;
        $user =& $this->user;
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;
        $userid = $this->user->userid;

        if (!$this->auth->edit)
        {
            $response->SetError('You do not have permission to edit this media.');
            $response->keepOpen = false;
            return $response;
        }

        // Hand off to the media module
        $mediaObject = new Media($db);

        // Stored As from the XML
        $storedAs = $this->GetOption('uri');

        // File data
        $tmpName = Kit::GetParam('hidFileID', _POST, _STRING);
        $name = Kit::GetParam('name', _POST, _STRING);
        $tags = Kit::GetParam('tags', _POST, _STRING);
        
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
                $response->SetError($mediaObject->GetErrorMessage());
                $response->keepOpen = true;
                return $response;
            }            	

            // Are we on a region
            if ($regionid != '') {
                $security = new LayoutMediaGroupSecurity($db);
                $security->Copy($layoutid, $regionid, $mediaid, $new_mediaid);
            }

            // Required Attributes
            $this->mediaid	= $new_mediaid;

            // Find out what we stored this item as
            try {
                $dbh = PDOConnect::init();

                $sth = $dbh->prepare('SELECT StoredAs FROM `media` WHERE mediaid = :mediaId');
                $sth->execute(array('mediaId' => $new_mediaid));
                
                $storedAs = Kit::ValidateParam($sth->fetchColumn(0), _FILENAME);
                $this->SetOption('uri', $storedAs);
            }
            catch (Exception $e) {
                Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
             
                trigger_error(__('Unable to find uploaded file.'), E_USER_ERROR);
            }

            Debug::LogEntry('audit', 'New revision uploaded: ' . $storedAs, 'module', 'EditLibraryMedia');
        }

        // Edit the media record
        if (!$mediaObject->Edit($this->mediaid, $name, $this->duration, $userid, $tags)) {
            $response->SetError($mediaObject->GetErrorMessage());
            $response->keepOpen = true;
            return $response;
        }

        // Should have built the media object entirely by this time
        if ($regionid != '' && $this->showRegionOptions)
        {
            // This saves the Media Object to the Region
            $this->UpdateRegion();

            $response->loadForm	 = true;
            $response->loadFormUri = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";;
        }
        elseif ($regionid != '' && !$this->showRegionOptions)
        {
            $this->UpdateRegion();
            $response->loadForm = false;
        }
        else
        {
            $response->message = 'Edited the ' . $this->displayType;
        }

        // Edit from the library - check to see if we are replacing this media in *all* layouts.
        $replaceInLayouts = (Kit::GetParam('replaceInLayouts', _POST, _CHECKBOX) == 1);
        $replaceBackgroundImages = (Kit::GetParam('replaceBackgroundImages', _POST, _CHECKBOX) == 1);

        if ($replaceInLayouts || $replaceBackgroundImages)
            $this->ReplaceMediaInAllLayouts($replaceInLayouts, $replaceBackgroundImages, $mediaid, $this->mediaid, $this->duration);

        // Do we need to delete the old media item?
        if ($tmpName != '' && Kit::GetParam('deleteOldVersion', _POST, _CHECKBOX) == 1) {
            if (!$mediaObject->Delete($mediaid))
                $response->message .= ' ' . __('Failed to remove old media');
        }
        
        return $response;
    }

    /**
     * Replace media in all layouts.
     * @param <type> $oldMediaId
     * @param <type> $newMediaId
     */
    private function ReplaceMediaInAllLayouts($replaceInLayouts, $replaceBackgroundImages, $oldMediaId, $newMediaId)
    {
        $count = 0;
        
        Debug::LogEntry('audit', sprintf('Replacing mediaid %s with mediaid %s in all layouts', $oldMediaId, $newMediaId), 'module', 'ReplaceMediaInAllLayouts');

        try {
            $dbh = PDOConnect::init();
        
            // Some update statements to use
            $sth = $dbh->prepare('SELECT lklayoutmediaid, regionid FROM lklayoutmedia WHERE mediaid = :media_id AND layoutid = :layout_id');
            $sth_update = $dbh->prepare('UPDATE lklayoutmedia SET mediaid = :media_id WHERE lklayoutmediaid = :lklayoutmediaid');

            // Loop through a list of layouts this user has access to
            foreach($this->user->LayoutList() as $layout) {
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
                
                // Create a region object for later use (new one each time)
                $layout = new Layout();
                $region = new region($this->db);

                // Loop through each media link for this layout
                foreach ($results as $row)
                {
                    // Get the LKID of the link between this layout and this media.. could be more than one?
                    $lkId = $row['lklayoutmediaid'];
                    $regionId = $row['regionid'];

                    if ($regionId == 'background') {

                        Debug::Audit('Replacing background image');

                        if (!$replaceBackgroundImages)
                            continue;

                        // Straight swap this background image node.
                        if (!$layout->EditBackgroundImage($layoutId, $newMediaId))
                            return false;
                    }
                    else {

                        if (!$replaceInLayouts)
                            continue;

                        // Get the Type of this media
                        if (!$type = $region->GetMediaNodeType($layoutId, '', '', $lkId))
                            continue;
        
                        // Create a new media node use it to swap the nodes over
                        Debug::LogEntry('audit', 'Creating new module with MediaID: ' . $newMediaId . ' LayoutID: ' . $layoutId . ' and RegionID: ' . $regionId, 'region', 'ReplaceMediaInAllLayouts');
                        try {
                            $module = ModuleFactory::createForMedia($type, $newMediaId, $this->db, $this->user);
                        }
                        catch (Exception $e) {
                            Debug::Error($e->getMessage());
                            return false;
                        }
        
                        // Sets the URI field
                        if (!$module->SetRegionInformation($layoutId, $regionId))
                            return false;
        
                        // Get the media xml string to use in the swap.
                        $mediaXmlString = $module->AsXml();
        
                        // Swap the nodes
                        if (!$region->SwapMedia($layoutId, $regionId, $lkId, $oldMediaId, $newMediaId, $mediaXmlString))
                            return false;
                    }
    
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
    public function Preview($width, $height, $scaleOverride = 0)
    {
        if ($this->previewEnabled == 0)
            return '<div style="text-align:center;"><img alt="' . $this->type . ' thumbnail" src="theme/default/img/forms/' . $this->type . '.gif" /></div>';
            
        return $this->PreviewAsClient($width, $height, $scaleOverride);
    }

    /**
     * Preview as the Client
     * @param <double> $width
     * @param <double> $height
     * @return <string>
     */
    public function PreviewAsClient($width, $height, $scale_override = 0)
    {
        $widthPx    = $width .'px';
        $heightPx   = $height .'px';

        return '<iframe scrolling="no" src="index.php?p=module&mod=' . $this->type . '&q=Exec&method=GetResource&raw=true&preview=true&scale_override=' . $scale_override . '&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&mediaid=' . $this->mediaid . '&lkid=' . $this->lkid . '&width=' . $width . '&height=' . $height . '" width="' . $widthPx . '" height="' . $heightPx . '" style="border:0;"></iframe>';
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
        $output .= '<div class="preview-module-image"><img alt="' . $this->displayType . ' thumbnail" src="theme/default/img/' . $this->imageUri . '"></div>';
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
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        if (!$this->auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this media'), E_USER_ERROR);

        // List of all Groups with a view / edit / delete check box
        $permissions = new UserGroup();
        
        if ($this->assignedMedia) {
            if (!$result = $permissions->GetPermissionsForObject('lklayoutmediagroup', NULL, NULL, sprintf(" AND lklayoutmediagroup.MediaID = '%s' AND lklayoutmediagroup.RegionID = '%s' AND lklayoutmediagroup.LayoutID = %d ", $this->mediaid, $this->regionid, $this->layoutid)))
                trigger_error($permissions->GetErrorMessage(), E_USER_ERROR);
        }
        else {
            if (!$result = $permissions->GetPermissionsForObject('lkmediagroup', 'MediaID', $this->mediaid))
                trigger_error($permissions->GetErrorMessage(), E_USER_ERROR); 
        }

        if (count($result) <= 0)
            trigger_error(__('Unable to get permissions'), E_USER_ERROR);

        $checkboxes = array();

        foreach ($result as $row) {
            $groupId = $row['groupid'];
            $rowClass = ($row['isuserspecific'] == 0) ? 'strong_text' : '';

            $checkbox = array(
                    'id' => $groupId,
                    'name' => Kit::ValidateParam($row['group'], _STRING),
                    'class' => $rowClass,
                    'value_view' => $groupId . '_view',
                    'value_view_checked' => (($row['view'] == 1) ? 'checked' : ''),
                    'value_edit' => $groupId . '_edit',
                    'value_edit_checked' => (($row['edit'] == 1) ? 'checked' : ''),
                    'value_del' => $groupId . '_del',
                    'value_del_checked' => (($row['del'] == 1) ? 'checked' : ''),
                );

            $checkboxes[] = $checkbox;
        }

        $formFields = array();
        $formFields[] = FormManager::AddPermissions('groupids[]', $checkboxes);
        Theme::Set('form_fields', $formFields);

        // Set some information about the form
        Theme::Set('form_id', 'LayoutPermissionsForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=Permissions');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '" /><input type="hidden" name="regionid" value="' . $this->regionid . '" /><input type="hidden" name="mediaid" value="' . $this->mediaid . '" />');

        $response->SetFormRequestResponse(NULL, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . (($this->layoutid != 0) ? HelpManager::Link('LayoutMedia', 'Permissions') : HelpManager::Link('Media', 'Permissions')) . '")');
        
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
        $response = new ResponseManager();

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
        $response = new ResponseManager();

        if (!$this->auth->edit)
        {
            $response->SetError('You do not have permission to edit this media.');
            $response->keepOpen = false;
            return $response;
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
        
        Theme::Set('form_id', 'TransitionForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=TransitionEdit');
        Theme::Set('form_meta', '
            <input type="hidden" name="type" value="' . $type . '">
            <input type="hidden" name="layoutid" value="' . $this->layoutid . '">
            <input type="hidden" name="mediaid" value="' . $this->mediaid . '">
            <input type="hidden" name="lkid" value="' . $this->lkid . '">
            <input type="hidden" id="iRegionId" name="regionid" value="' . $this->regionid . '">
            <input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />
            ');

        $formFields[] = FormManager::AddCombo(
                    'transitionType', 
                    __('Transition'), 
                    $transition,
                    $transitions,
                    'code',
                    'transition',
                    __('What transition should be applied when this region is finished?'), 
                    't');

        $formFields[] = FormManager::AddNumber('transitionDuration', __('Duration'), $duration, 
            __('The duration for this transition, in milliseconds.'), 'l', '', 'transition-group');
        
        $formFields[] = FormManager::AddCombo(
                    'transitionDirection', 
                    __('Direction'), 
                    $direction,
                    $compassPoints,
                    'id',
                    'name',
                    __('The direction for this transition. Only appropriate for transitions that move, such as Fly.'),
                    'd',
                    'transition-group transition-direction');

        // Add some dependencies
        $response->AddFieldAction('transitionType', 'init', '', array('.transition-group' => array('display' => 'none')));
        $response->AddFieldAction('transitionType', 'init', '', array('.transition-group' => array('display' => 'block')), 'not');
        $response->AddFieldAction('transitionType', 'change', '', array('.transition-group' => array('display' => 'none')));
        $response->AddFieldAction('transitionType', 'change', '', array('.transition-group' => array('display' => 'block')), 'not');

        // Decide where the cancel button will take us
        if ($this->showRegionOptions)
            $response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
        else
            $response->AddButton(__('Cancel'), 'XiboDialogClose()');

        // Always include the save button
        $response->AddButton(__('Save'), '$("#TransitionForm").submit()');
        
        // Output the form and dialog
        Theme::Set('form_fields', $formFields);
        $response->html = Theme::RenderReturn('form_render');
        $response->dialogTitle = sprintf(__('Edit %s Transition for %s'), $type, $this->displayType);
        $response->dialogSize = true;
        $response->dialogWidth = '450px';
        $response->dialogHeight = '280px';
        
        return $response;
    }
    
    /**
     * Edit a transition
     */
    public function TransitionEdit()
    {
        $response = new ResponseManager();

        if (!$this->auth->edit)
        {
            $response->SetError('You do not have permission to edit this media.');
            $response->keepOpen = false;
            return $response;
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
            $response->loadForm = true;
            $response->loadFormUri = 'index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions';
        }

        return $response;
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
     * @param string $xml The XML
     * @return string The MediaId
     */
    public function SetMediaXml($xml)
    {
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

    /**
     * Validate the Media XML Provided
     * @param $xmlDoc DOMDocument The Media XML
     * @return bool
     */
    protected function ValidateMediaXml($xmlDoc)
    {
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
                // This is library media that we want to assign or update
                // We need to check that the mediaId exists and if so, store the mediaId on this media object
                $mediaIdInXlf = $mediaNode->getAttribute('id');
                $entries = Media::Entries(null, array('mediaId' => $mediaIdInXlf));

                if (count($entries) <=0)
                    return $this->SetError(__(sprintf('MediaId %s provided in XLF does not exist.', $mediaIdInXlf)));
                else
                    $this->mediaid = $mediaIdInXlf;
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
     * @return string
     */
    public function GetErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Sets the Error for this Data object
     * @return bool
     * @param $errNo mixed
     * @param $errMessage string
     */
    protected function SetError($errNo, $errMessage = '')
    {
        $this->error = true;

        // Is an error No provided?
        if (!is_numeric($errNo)) {
            $errMessage = $errNo;
            $errNo = -1;
        }

        $this->errorNo = $errNo;
        $this->errorMessage	= $errMessage;

        Debug::LogEntry('audit', sprintf('Module Class: Error Number [%d] Error Message [%s]', $errNo, $errMessage), 'Media Module', 'SetError');

        // Return false so that we can use this method as the return call for parent methods
        return false;
    }

    protected function ThrowError($errNo, $errMessage = '') {
        $this->SetError($errNo, $errMessage);
        throw new Exception(sprintf('%s [%d]', $this->GetErrorMessage(), $this->GetErrorNumber()));
    }

    public function IsValid() {
        // Defaults: Stored media is valid, region specific is unknown
        return ($this->regionSpecific) ? 0 : 1;
    }

    /**
     * Default behaviour for install / upgrade
     * this should be overridden for new modules
     */
    public function InstallOrUpdate() {

        if ($this->render_as != 'native')
            return $this->SetError(1, __('Module must implement InstallOrUpgrade'));

        return true;
    }

    /**
     * Installs any files specific to this module
     */
    public function InstallFiles()
    {

    }

    public function InstallModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings) {
        
        Debug::LogEntry('audit', 'Request to install module with name: ' . $name, 'module', 'InstallModule');

        try {
            // Validate some things.
            if ($this->type == '')
                $this->ThrowError(__('Module has not set the module type'));

            if ($name == '')
                $this->ThrowError(__('Module has not set the module name'));

            if ($description == '')
                $this->ThrowError(__('Module has not set the description'));

            if (!is_numeric($previewEnabled))
                $this->ThrowError(__('Preview Enabled variable must be a number'));

            if (!is_numeric($assignable))
                $this->ThrowError(__('Assignable variable must be a number'));

            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                    INSERT INTO `module` (`Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, 
                        `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`, `settings`) 
                    VALUES (:module, :name, :enabled, :region_specific, :description, 
                        :image_uri, :schema_version, :valid_extensions, :preview_enabled, :assignable, :render_as, :settings);
                ');

            Debug::LogEntry('audit', 'Executing SQL', 'module', 'InstallModule');

            $sth->execute(array(
                    'module' =>  $this->type,
                    'name' =>  $name,
                    'enabled' =>  1,
                    'region_specific' =>  1,
                    'description' =>  $description, 
                    'image_uri' =>  $imageUri,
                    'schema_version' =>  $this->codeSchemaVersion,
                    'valid_extensions' =>  '',
                    'preview_enabled' =>  $previewEnabled,
                    'assignable' =>  $assignable,
                    'render_as' =>  'html',
                    'settings' => json_encode($settings)
                ));
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            throw new Exception(__('Unable to install module. Please check the Error Log'));
        }
    }

    public function UpgradeModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings) {
        
        try {
            // Validate some things.
            if ($this->module_id == '')
                $this->ThrowError(__('This module does not exist - should you have called Install?'));

            if ($name == '')
                $this->ThrowError(__('Module has not set the module name'));

            if ($description == '')
                $this->ThrowError(__('Module has not set the description'));

            if (!is_numeric($previewEnabled))
                $this->ThrowError(__('Preview Enabled variable must be a number'));

            if (!is_numeric($assignable))
                $this->ThrowError(__('Assignable variable must be a number'));

            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                    UPDATE `module` SET `Name` = :name, `Description` = :description, 
                        `ImageUri` = :image_uri, `SchemaVersion` = :schema_version, `PreviewEnabled` = :preview_enabled, 
                        `assignable` = :assignable, `settings` = :settings
                     WHERE ModuleID = :module_id
                ');

            $sth->execute(array(
                    'name' =>  $name,
                    'description' =>  $description, 
                    'image_uri' =>  $imageUri,
                    'schema_version' =>  $this->codeSchemaVersion,
                    'preview_enabled' =>  $previewEnabled,
                    'assignable' =>  $assignable,
                    'settings' => $settings,
                    'module_id' => $this->module_id
                ));
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Form for updating the module settings
     */
    public function ModuleSettingsForm() {
        return array();
    }

    /**
     * Process any module settings
     */
    public function ModuleSettings() {
        return array();
    }

    /**
     * Updates the settings on the module
     * @param [array] $settings [The Settings]
     */
    public function UpdateModuleSettings($settings) {
        if (!is_array($settings))
            return $this->SetError(__('Module settings must be an array'));

        // Update the settings on the module record.
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('UPDATE `module` SET settings = :settings WHERE ModuleID = :module_id');
            $sth->execute(array(
                    'settings' => json_encode($settings),
                    'module_id' => $this->module_id
                ));
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function GetSetting($setting, $default = NULL) {
        if (isset($this->settings[$setting]))
            return $this->settings[$setting];
        else
            return $default;
    }
    
    /**
     * Return file based media items to the browser for Download/Preview
     * @return
     * @param $download Boolean
     */
    public function ReturnFile($fileName = '')
    {
        // Return the raw flash file with appropriate headers
        $library = Config::GetSetting("LIBRARY_LOCATION");

        # If we weren't passed in a filename then use the default
        if ($fileName == '') {
            $fileName = $library . $this->storedAs;
        }
        
        $download = Kit::GetParam('download', _REQUEST, _BOOLEAN, false);
        $downloadFromLibrary = Kit::GetParam('downloadFromLibrary', _REQUEST, _BOOLEAN, false);

        $size = filesize($fileName);
        
        if ($download) {
            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: Binary"); 
            header("Content-disposition: attachment; filename=\"" . (($downloadFromLibrary) ? $this->originalFilename : basename($fileName)) . "\"");
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
            header("X-Accel-Redirect: /download/" . basename($fileName));
            exit();
        }
        
        // Return the file with PHP
        // Disable any buffering to prevent OOM errors.
        @ob_end_clean();
        readfile($fileName);
    }
}
?>
