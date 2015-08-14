<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2010-2013 Daniel Garner
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
class Rest
{
    protected $user;
    protected $POST;

    public function __construct(User $user, $postData)
    {
        $this->user =& $user;

        // Hold the POST data
        $this->POST = $postData;
    }

    /**
     * Raise an API Error
     * This should be implemented by the child class
     * @param $errorNo mixed
     * @param string $errorMessage string
     * @return bool
     */
    public function Error($errorNo, $errorMessage = '')
    {
        return true;
    }

    /**
     * Returns an ID only response
     * @param string $nodeName
     * @param string $id
     * @param string $idAttributeName
     * @return DOMElement
     */
    protected function ReturnId($nodeName, $id, $idAttributeName = 'id')
    {
        return false;
    }

    /**
     * Returns a single node with the attributes contained in a key/value array
     * @param string $nodeName
     * @param array $attributes
     * @return DOMElement
     */
    protected function ReturnAttributes($nodeName, $attributes)
    {
        return false;
    }

    /**
     * Creates a node list from an array
     * @param array $array
     * @param string $nodeName
     * @return DOMElement
     */
    protected function NodeListFromArray($array, $nodeName)
    {
        return false;
    }

    /**
     * List all Displays for this user
     * @return <XiboAPIResponse>
     */
    public function DisplayList()
    {
        if (!$this->user->PageAuth('display'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Display');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Edit Display
     * @return <XiboAPIResponse>
     */
    public function DisplayEdit()
    {
        if (!$this->user->PageAuth('display'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Display');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Retire Display
     * @return <XiboAPIResponse>
     */
    public function DisplayRetire()
    {
        if (!$this->user->PageAuth('display'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Display');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Delete a Display
     * @return <XiboAPIResponse>
     */
    public function DisplayDelete()
    {
        if (!$this->user->PageAuth('display'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Display');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Display Wake On LAN
     * @return <XiboAPIResponse>
     */
    public function DisplayWakeOnLan()
    {
        if (!$this->user->PageAuth('display'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Display');

        $displayObject = new Display();
        $displayId = $this->GetParam('displayId', _INT);

        // Try to issue the WOL command
        if (!$displayObject->WakeOnLan($displayId))
            return $this->Error($displayObject->GetErrorNumber(), $displayObject->GetErrorMessage());

        // Return True
        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * List Display User Group Security
     * @return <XiboAPIResponse>
     */
    public function DisplayUserGroupSecurity()
    {
        if (!$this->user->PageAuth('display'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Display');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Edit Display User Group Security
     * @return <XiboAPIResponse>
     */
    public function DisplayUserGroupEdit()
    {
        if (!$this->user->PageAuth('display'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Display');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * List Display Groups
     * @return <XiboAPIResponse>
     */
    public function DisplayGroupList()
    {
        if (!$this->user->PageAuth('displaygroup'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('DisplayGroup');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Add Display Group
     * @return <XiboAPIResponse>
     */
    public function DisplayGroupAdd()
    {
        if (!$this->user->PageAuth('displaygroup'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('DisplayGroup');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Edit Display Group
     * @return <XiboAPIResponse>
     */
    public function DisplayGroupEdit()
    {
        if (!$this->user->PageAuth('displaygroup'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('DisplayGroup');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Delete Display Group
     * @return <XiboAPIResponse>
     */
    public function DisplayGroupDelete()
    {
        if (!$this->user->PageAuth('displaygroup'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('DisplayGroup');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * List Display Group Members
     * @return <XiboAPIResponse>
     */
    public function DisplayGroupMembersList()
    {
        if (!$this->user->PageAuth('displaygroup'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('DisplayGroup');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Edit Display Group Members
     * @return <XiboAPIResponse>
     */
    public function DisplayGroupMembersEdit()
    {
        if (!$this->user->PageAuth('displaygroup'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('DisplayGroup');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * List Display Group User Groups
     * @return <XiboAPIResponse>
     */
    public function DisplayGroupUserGroupList()
    {
        if (!$this->user->PageAuth('displaygroup'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('DisplayGroup');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * List Display Group User Group Edit
     * @return <XiboAPIResponse>
     */
    public function DisplayGroupUserGroupEdit()
    {
        if (!$this->user->PageAuth('displaygroup'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('DisplayGroup');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * List Library Media
     * @return <XiboAPIResponse>
     */
    public function LibraryMediaList()
    {
        if (!$this->user->PageAuth('content'))
            return $this->Error(1, 'Access Denied');

        $media = $this->user->MediaList();

        if (!is_array($media))
            return $this->Error(1);

        return $this->Respond($this->NodeListFromArray($media, 'media'));
    }

    /**
     * Media File Upload
     * Upload a media file in parts
     * @return <XiboAPIResponse>
     */
    public function LibraryMediaFileUpload()
    {
        // Does this user have permission to call this webservice method?
        if (!$this->user->PageAuth('content'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('file');

        $file           = new File();
        $fileId         = $this->GetParam('fileId', _INT);
        $checkSum       = $this->GetParam('checksum', _STRING);
        $payload        = $this->GetParam('payload', _STRING);
        $payloadMd5     = md5($payload);

        // Checksum the payload
        if ($payloadMd5 != $checkSum)
        {
            // Debug::LogEntry('audit', 'Sent Checksum: ' . $checkSum, 'RestXml', 'LibraryMediaFileUpload');
            // Debug::LogEntry('audit', 'Calculated Checksum: ' . $payloadMd5, 'RestXml', 'LibraryMediaFileUpload');
            // Debug::LogEntry('audit', 'Payload: ' . $payload, 'RestXml', 'LibraryMediaFileUpload');

            return $this->Error(2);
        }

        // Payload will be encoded in base64. Need to decode before handing to File class
        $payload = base64_decode($payload);

        if ($fileId == 0)
        {
            // New upload. All users have permissions to upload files if they have gotten this far
            if (!$fileId = $file->NewFile($payload, $this->user->userid))
                return $this->Error($file->GetErrorNumber());
        }
        else
        {
            // Check permissions
            if (!$this->user->FileAuth($fileId))
                return $this->Error(1, 'Access Denied');

            // Continue upload
            if (!$file->Append($fileId, $payload))
                return $this->Error($file->GetErrorNumber());
        }

        // Current offset
        $size = $file->Size($fileId);

        // Return the fileId
        return $this->Respond($this->ReturnAttributes('file', array('id' => $fileId, 'offset' => $size)));
    }

    /**
     * Add a media file to the library
     */
    public function LibraryMediaAdd()
    {
        // Does this user have permission to call this webservice method?
        if (!$this->user->PageAuth('content'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        // Create a media object and gather the required parameters.
        $media          = new Media();
        $fileId         = $this->GetParam('fileId', _INT);
        $type           = $this->GetParam('type', _WORD);
        $name           = $this->GetParam('name', _STRING);
        $duration       = $this->GetParam('duration', _INT);
        $fileName       = $this->GetParam('fileName', _FILENAME);

        // Check permissions
        if (!$this->user->FileAuth($fileId))
            return $this->Error(1, 'Access Denied');

        // Add the media.
        if (!$mediaId = $media->Add($fileId, $type, $name, $duration, $fileName, $this->user->userid))
            return $this->Error($media->GetErrorNumber(), $media->GetErrorMessage());

        // Return the mediaId.
        return $this->Respond($this->ReturnId('media', $mediaId));
    }

    /**
     * Edit a media file in the library
     */
    public function LibraryMediaEdit()
    {
        if (!$this->user->PageAuth('content'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        // Create a media object and gather the required parameters.
        $media          = new Media();
        $mediaId        = $this->GetParam('mediaId', _INT);
        $name           = $this->GetParam('name', _STRING);
        $duration       = $this->GetParam('duration', _INT);

        // Check permissions
        if (!$this->user->MediaAuth($mediaId))
            return $this->Error(1, 'Access Denied');

        // Add the media.
        if (!$media->Edit($mediaId, $name, $duration, $this->user->userid))
            return $this->Error($media->GetErrorNumber(), $media->GetErrorMessage());

        // Return the mediaId.
        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * Retire a media file in the library
     */
    public function LibraryMediaRetire()
    {
        if (!$this->user->PageAuth('content'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        $media      = new Media();
        $mediaId    = $this->GetParam('mediaId', _INT);

        if (!$this->user->MediaAuth($mediaId))
            return $this->Error(1, 'Access Denied');

        if (!$media->Retire($mediaId))
            return $this->Error($media->GetErrorNumber(), $media->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * Delete a Media file from the library
     */
    public function LibraryMediaDelete()
    {
        if (!$this->user->PageAuth('content'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        $media      = new Media();
        $mediaId    = $this->GetParam('mediaId', _INT);

        if (!$this->user->MediaAuth($mediaId))
            return $this->Error(1, 'Access Denied');

        if (!$media->Delete($mediaId))
            return $this->Error($media->GetErrorNumber(), $media->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * Replace a Media items file with a new revision
     * @return <XiboAPIResponse>
     */
    public function LibraryMediaFileRevise()
    {
        // Does this user have permission to call this webservice method?
        if (!$this->user->PageAuth('content'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        // Create a media object and gather the required parameters.
        $media          = new Media();
        $mediaId        = $this->GetParam('mediaId', _INT);
        $fileId         = $this->GetParam('fileId', _INT);
        $fileName       = $this->GetParam('fileName', _FILENAME);

        // Check permissions
        if (!$this->user->FileAuth($fileId))
            return $this->Error(1, 'Access Denied');

        // Add the media.
        if (!$mediaId = $media->FileRevise($mediaId, $fileId, $fileName, $this->user->userid))
            return $this->Error($media->GetErrorNumber(), $media->GetErrorMessage());

        // Return the mediaId.
        return $this->Respond($this->ReturnId('media', $mediaId));
    }

    /**
     * List Layouts
     * @return <XiboAPIResponse>
     */
    public function LayoutList()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layout = $this->user->LayoutList();

        if (!is_array($layout))
            return $this->Error(2, 'No layouts');

        // Remove the XML from the array
        for ($i = 0; $i < count($layout); $i++)
            unset($layout[$i]['xml']);

        return $this->Respond($this->NodeListFromArray($layout, 'layout'));
    }

    /**
     * Add Layout
     * @return <XiboAPIResponse>
     */
    public function LayoutAdd()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('layout');

        $layout         = $this->GetParam('layout', _STRING);
        $description    = $this->GetParam('description', _STRING);
        $tags           = $this->GetParam('tags', _STRING);
        $templateId     = $this->GetParam('templateid', _INT, 0);
        $resolutionId = $this->GetParam('resolutionid', _INT, 0);

        // Add this layout
        $layoutObject = new Layout();

        if(!$id = $layoutObject->Add($layout, $description, $tags, $this->user->userid, $templateId, $resolutionId))
            return $this->Error($layoutObject->GetErrorNumber(), $layoutObject->GetErrorMessage());

        Debug::LogEntry('audit', 'Added new layout with id' . $id);

        return $this->Respond($this->ReturnId('layout', $id));
    }

    /**
     * Edit Layout
     * @return <XiboAPIResponse>
     */
    public function LayoutEdit()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Layout');

        $layout     = new Layout();
        $layoutId   = $this->GetParam('layoutId', _INT);

        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Copy Layout
     * @return <XiboAPIResponse>
     */
    public function LayoutCopy()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Layout');

        $layout      = new Layout();
        $layoutId    = $this->GetParam('layoutId', _INT);
        $name        = $this->GetParam('layout', _STRING);
        $description = $this->GetParam('description', _STRING);
        $copyMedia   = $this->GetParam('copyMedia', _INT);

        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Copy this layout
        $layoutObject = new Layout();

        if(!$id = $layoutObject->Copy($layoutId, $name, $description, $this->user->userid, $copyMedia))
            return $this->Error($layoutObject->GetErrorNumber(), $layoutObject->GetErrorMessage());

        Debug::LogEntry('audit', 'Copied layout with id' . $id);

        return $this->Respond($this->ReturnId('layout', $id));
    }

    /**
     * Delete Layout
     * @return <XiboAPIResponse>
     */
    public function LayoutDelete()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Layout');

        $layout     = new Layout();
        $layoutId   = $this->GetParam('layoutId', _INT);

        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        if (!$layout->Delete($layoutId))
            return $this->Error($layout->GetErrorNumber(), $layout->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * Retire Layout
     * @return <XiboAPIResponse>
     */
    public function LayoutRetire()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Layout');

        $layout     = new Layout();
        $layoutId   = $this->GetParam('layoutId', _INT);

        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * List possible layout backgrounds
     * @return <XiboAPIResponse>
     */
    public function LayoutBackgroundList()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Layout');

        $layout     = new Layout();
        $layoutId   = $this->GetParam('layoutId', _INT);

        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Edit layout background
     * @return <XiboAPIResponse>
     */
    public function LayoutBackgroundEdit()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Layout');

        $layout     = new Layout();
        $layoutId   = $this->GetParam('layoutId', _INT);

        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * List Regions on a layout
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionList()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);

        // Does the user have permissions to view this region?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Get a list of region items
        Kit::ClassLoader('layout');
        $layout = new Layout();

        // Get the list of regions for this layout
        $regions = $layout->GetRegionList($layoutId);

        if (!is_array($regions))
            return $this->Error(10019, 'Unable to get regions');

        $regionsWithPermissions = array();

        // Go through each one and say if we have permissions to use it or not
        foreach ($regions as $region) {

            $auth = $this->user->RegionAssignmentAuth($region['ownerid'], $layoutId, $region['regionid'], true);
            if (!$auth->view)
                continue;

            // Add in the permissions model
            $mediaItem['permission_edit'] = (int)$auth->edit;
            $mediaItem['permissions_del'] = (int)$auth->del;
            $mediaItem['permissions_update_permissions'] = (int)$auth->modifyPermissions;

            $regionsWithPermissions[] = $region;
        }

        return $this->Respond($this->NodeListFromArray($regionsWithPermissions, 'region'));
    }

    /**
     * Add Region to a Layout
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionAdd()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);
        $width = $this->GetParam('width', _INT, 100);
        $height = $this->GetParam('height', _INT, 100);
        $top = $this->GetParam('top', _INT, 50);
        $left = $this->GetParam('left', _INT, 50);
        $name = $this->GetParam('name', _STRING);

        // Does the user have permissions to view this region?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Create a region object
        Kit::ClassLoader('region');
        $region = new Region();

        if (!$regionId = $region->AddRegion($layoutId, $this->user->userid, '', $width, $height, $top, $left, $name))
            return $this->Error($region->GetErrorNumber(), $region->GetErrorMessage());

        return $this->Respond($this->ReturnId('region', $regionId));
    }

    /**
     * Edit Region on a layout
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionEdit()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);
        $regionId = $this->GetParam('regionId', _STRING);
        $width = $this->GetParam('width', _INT);
        $height = $this->GetParam('height', _INT);
        $top = $this->GetParam('top', _INT);
        $left = $this->GetParam('left', _INT);
        $name = $this->GetParam('name', _STRING);

        // Does the user have permissions to view this region?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Create a region object
        Kit::ClassLoader('region');
        $region = new Region();

        // Region Assignment needs the Owner Id
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            return $this->Error(1, 'Access Denied');

        // Edit the region
        if (!$regionId = $region->EditRegion($layoutId, $regionId, $width, $height, $top, $left, $name = ''))
            return $this->Error($region->GetErrorNumber(), $region->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * Delete Region on a layout
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionDelete()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);
        $regionId = $this->GetParam('regionId', _STRING);

        // Does the user have permissions to view this region?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Create a region object
        Kit::ClassLoader('region');
        $region = new Region();

        // Region Assignment needs the Owner Id
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->del)
            return $this->Error(1, 'Access Denied');

        // Edit the region
        if (!$regionId = $region->DeleteRegion($layoutId, $regionId))
            return $this->Error($region->GetErrorNumber(), $region->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * List the items on a region timeline
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionTimelineList()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);
        $regionId = $this->GetParam('regionId', _STRING);

        // Does the user have permissions to view this region?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Create a region object
        Kit::ClassLoader('region');
        $region = new Region();

        // Region Assignment needs the Owner Id
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            return $this->Error(1, 'Access Denied');

        // We have permission to be here.
        // Return a list of media items
        if (!$items = $region->GetMediaNodeList($layoutId, $regionId))
            return false;

        $regionItems = array();

        foreach ($items as $mediaNode) {
            // Get the Type, ID, duration, etc (the generic information)
            $mediaItem['mediaid'] = $mediaNode->getAttribute('id');
            $mediaItem['lkid'] = $mediaNode->getAttribute('lkid');
            $mediaItem['mediatype'] = $mediaNode->getAttribute('type');
            $mediaItem['mediaduration'] = $mediaNode->getAttribute('duration');
            $mediaItem['mediaownerid'] = $mediaNode->getAttribute('userId');

            // Permissions for this assignment
            $auth = $this->user->MediaAssignmentAuth($mediaItem['mediaownerid'], $layoutId, $regionId, $mediaItem['mediaid'], true);

            // Skip over media assignments that we do not have permission to see
            if (!$auth->view)
                continue;

            $mediaItem['permission_edit'] = (int)$auth->edit;
            $mediaItem['permissions_del'] = (int)$auth->del;
            $mediaItem['permissions_update_duration'] = (int)$auth->modifyPermissions;
            $mediaItem['permissions_update_permissions'] = (int)$auth->modifyPermissions;

            // Add these items to an array
            $regionItems[] = $mediaItem;
        }

        return $this->Respond($this->NodeListFromArray($regionItems, 'media'));
    }

    /**
     * Add Media to a Region
     * @return XiboAPIResponse
     */
    public function LayoutRegionMediaAdd()
    {
        // Does this user have permission to call this webservice method?
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);
        $regionId = $this->GetParam('regionId', _STRING);
        $type = $this->GetParam('type', _WORD);
        $xlf = $this->GetParam('xlf', _HTMLSTRING);

        // Does the user have permissions to view this layout?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Check the user has permission
        $region = new region();
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            return $this->Error(1, 'Access Denied');

        // Create a new module based on the XLF we have been given
        try {
            $module = ModuleFactory::createForLayout($type, $layoutId, $regionId);
        }
        catch (Exception $e) {
            return $this->Error($e->getMessage());
        }

        // Set the XML (causes save)
        if (!$id = $module->SetMediaXml($xlf))
            return $this->Error($module->GetErrorNumber(), $module->GetErrorMessage());

        return $this->Respond($this->ReturnId('media', $id));
    }

    /**
     * Edit Media on a Region
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionMediaEdit()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);
        $regionId = $this->GetParam('regionId', _STRING);
        $mediaId = $this->GetParam('mediaId', _STRING);
        $type = $this->GetParam('type', _WORD);
        $xlf = $this->GetParam('xlf', _HTMLSTRING);

        // Does the user have permissions to view this layout?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Check the user has permission
        Kit::ClassLoader('region');
        $region = new region();
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            return $this->Error(1, 'Access Denied');

        // Create the module
        try {
            $module = ModuleFactory::load($type, $layoutId, $regionId, $mediaId, NULL, NULL, $this->user);
        }
        catch (Exception $e) {
            return $this->Error($e->getMessage());
        }

        if (!$module->auth->edit)
            return $this->Error(1, 'Access Denied');

        // Set the XML (causes save)
        if (!$id = $module->SetMediaXml($xlf))
            return $this->Error($module->GetErrorNumber(), $module->GetErrorMessage());

        return $this->Respond($this->ReturnId('media', $id));
    }

    /**
     * Details of Media on a Region
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionMediaDetails()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);
        $regionId = $this->GetParam('regionId', _STRING);
        $mediaId = $this->GetParam('mediaId', _STRING);
        $type = $this->GetParam('type', _WORD);

        // Does the user have permissions to view this layout?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Check the user has permission
        Kit::ClassLoader('region');
        $region = new region();
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            return $this->Error(1, 'Access Denied');

        // Create the module
        try {
            $module = ModuleFactory::load($type, $layoutId, $regionId, $mediaId);
        }
        catch (Exception $e) {
            return $this->Error($e->getMessage());
        }

        if (!$module->auth->view)
            return $this->Error(1, 'Access Denied');

        return $this->Respond($this->ReturnAttributes('media', array('id' => $mediaId, 'base64Xlf' => base64_encode($module->AsXml()))));
    }

    /**
     * Reorder media on a region
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionMediaReorder()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);
        $regionId = $this->GetParam('regionId', _STRING);
        $mediaList = $this->GetParam('mediaList', _ARRAY);

        // Does the user have permissions to view this region?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Check the user has permission
        Kit::ClassLoader('region');
        $region = new region();
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            return $this->Error(1, 'Access Denied');

        // TODO: Validate the media list in some way (make sure there are the correct number of items)


        // Hand off to the region object to do the actual reorder
        if (!$region->ReorderTimeline($layoutId, $regionId, $mediaList))
            return $this->Error($region->GetErrorNumber(), $region->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * Delete media from a region
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionMediaDelete()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);
        $regionId = $this->GetParam('regionId', _STRING);
        $mediaId = $this->GetParam('mediaId', _STRING);
        $lkId = $this->GetParam('lkId', _INT);

        // Does the user have permissions to view this region?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Check the user has permission
        Kit::ClassLoader('region');
        $region = new region();
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            return $this->Error(1, 'Access Denied');

        // Load the media information from the provided ids
        // Get the type from this media
        $entry = Media::Entries(null, array('mediaId' => $mediaId));

        if (count($entry) <= 0)
            return $this->SetError(__('Error getting type from a media item.'));

        // Create a module
        try {
            $module = ModuleFactory::load($entry[0]->mediaType, $layoutId, $regionId, $mediaId, $lkId, null, $this->user);
        }
        catch (Exception $e) {
            return $this->Error($e->getMessage());
        }

        if (!$module->auth->del)
            return $this->Error(1, 'Access Denied');

        // Delete the assignment from the region
        if (!$module->ApiDeleteRegionMedia($layoutId, $regionId, $mediaId)) {
            return $this->Error($module->errorMessage);
        }

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * Add media to a region from the Library
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionLibraryAdd()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        $layoutId = $this->GetParam('layoutId', _INT);
        $regionId = $this->GetParam('regionId', _STRING);
        $mediaList = $this->GetParam('mediaList', _ARRAY);

        // Does the user have permissions to view this region?
        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        // Make sure we have permission to edit this region
        Kit::ClassLoader('region');
        $region = new region();
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            return $this->Error(1, 'Access Denied');

        if (!$region->AddFromLibrary($this->user, $layoutId, $regionId, $mediaList))
            return $this->Error($region->GetErrorNumber(), $region->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * List Schedule
     * @return <XiboAPIResponse>
     */
    public function ScheduleList()
    {
        if (!$this->user->PageAuth('schedule'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Add Schedule
     * @return <XiboAPIResponse>
     */
    public function ScheduleAdd()
    {
        if (!$this->user->PageAuth('schedule'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Edit Schedule
     * @return <XiboAPIResponse>
     */
    public function ScheduleEdit()
    {
        if (!$this->user->PageAuth('schedule'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Delete Schedule
     * @return <XiboAPIResponse>
     */
    public function ScheduleDelete()
    {
        if (!$this->user->PageAuth('schedule'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Delete Template
     * @return <XiboAPIResponse>
     */
    public function TemplateDelete()
    {
        if (!$this->user->PageAuth('template'))
            return $this->Error(1, 'Access Denied');

        $layout     = new Layout();
        $layoutId   = $this->GetParam('templateId', _INT);

        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        if (!$layout->Delete($layoutId))
            return $this->Error($layout->GetErrorNumber(), $layout->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * Lists enabled modules
     * @return <XiboAPIResponse>
     */
    public function ModuleList()
    {
        // Does this user have permission to call this webservice method?
        if (!$this->user->PageAuth('content'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        // Create a media object and gather the required parameters.
        $media = new Media();

        if (!$modules = $media->ModuleList())
            return $this->Error($media->GetErrorNumber(), $media->GetErrorMessage());

        return $this->Respond($this->NodeListFromArray($modules, 'module'));
    }

    /**
     * DataSet List
     * @return <XiboAPIResponse>
     */
    public function DataSetList() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataset = $this->user->DataSetList();

        if (!is_array($dataset))
            return $this->Error(2, 'No datasets');

        return $this->Respond($this->NodeListFromArray($dataset, 'dataset'));
    }

    /**
     * DataSet Add
     * @return <XiboAPIResponse>
     */
    public function DataSetAdd() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSet = $this->GetParam('dataset', _STRING);
        $description = $this->GetParam('description', _STRING);

        Kit::ClassLoader('dataset');
        $dataSetObject = new DataSet();
        if (!$dataSetId = $dataSetObject->Add($dataSet, $description, $this->user->userid))
            return $this->Error($dataSetObject->GetErrorNumber(), $dataSetObject->GetErrorMessage());

        return $this->Respond($this->ReturnId('dataset', $dataSetId));
    }

    /**
     * DataSet Edit
     * @return <XiboAPIResponse>
     */
    public function DataSetEdit() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            return $this->Error(1, 'Access Denied');

        $dataSet = $this->GetParam('dataset', _STRING);
        $description = $this->GetParam('description', _STRING);

        Kit::ClassLoader('dataset');
        $dataSetObject = new DataSet();
        if (!$dataSetObject->Edit($dataSetId, $dataSet, $description))
            return $this->Error($dataSetObject->GetErrorNumber(), $dataSetObject->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * DataSet Delete
     * @return <XiboAPIResponse>
     */
    public function DataSetDelete() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->del)
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('dataset');
        $dataSetObject = new DataSet();
        if (!$dataSetObject->Delete($dataSetId))
            return $this->Error($dataSetObject->GetErrorNumber(), $dataSetObject->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * DataSet Column List
     * @return <XiboAPIResponse>
     */
    public function DataSetColumnList() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->view)
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('datasetcolumn');
        $dataSetObject = new DataSetColumn();
        if (!$columns = $dataSetObject->GetColumns($dataSetId))
            return $this->Error($dataSetObject->GetErrorNumber(), $dataSetObject->GetErrorMessage());

        return $this->Respond($this->NodeListFromArray($columns, 'datasetcolumn'));
    }

    /**
     * DataSet Column Add
     * @return <XiboAPIResponse>
     */
    public function DataSetColumnAdd() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            return $this->Error(1, 'Access Denied');

        $heading = $this->GetParam('heading', _STRING);
        $listContent = $this->GetParam('listContent', _STRING);
        $columnOrder = $this->GetParam('columnOrder', _INT);
        $dataTypeId = $this->GetParam('dataTypeId', _INT);
        $dataSetColumnTypeId = $this->GetParam('datasetColumnTypeId', _INT);
        $formula = $this->GetParam('formula', _STRING);

        Kit::ClassLoader('datasetcolumn');
        $dataSetColumnObject = new DataSetColumn();
        if (!$dataSetColumnId = $dataSetColumnObject->Add($dataSetId, $heading, $dataTypeId, $listContent, $columnOrder, $dataSetColumnTypeId, $formula))
            return $this->Error($dataSetColumnObject->GetErrorNumber(), $dataSetColumnObject->GetErrorMessage());

        return $this->Respond($this->ReturnId('datasetcolumn', $dataSetColumnId));
    }

    /**
     * DataSet Column Edit
     * @return <XiboAPIResponse>
     */
    public function DataSetColumnEdit() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            return $this->Error(1, 'Access Denied');

        $dataSetColumnId = $this->GetParam('datasetColumnId', _POST, _INT);
        $heading = $this->GetParam('heading', _STRING);
        $listContent = $this->GetParam('listContent', _STRING);
        $columnOrder = $this->GetParam('columnOrder', _INT);
        $dataTypeId = $this->GetParam('dataTypeId', _INT);
        $dataSetColumnTypeId = $this->GetParam('datasetColumnTypeId', _INT);
        $formula = $this->GetParam('formula', _STRING);

        Kit::ClassLoader('datasetcolumn');
        $dataSetColumnObject = new DataSetColumn();
        if (!$dataSetColumnObject->Edit($dataSetColumnId, $heading, $dataTypeId, $listContent, $columnOrder, $dataSetColumnTypeId, $formula))
            return $this->Error($dataSetColumnObject->GetErrorNumber(), $dataSetColumnObject->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * DataSet Column Delete
     * @return <XiboAPIResponse>
     */
    public function DataSetColumnDelete() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            return $this->Error(1, 'Access Denied');

        $dataSetColumnId = $this->GetParam('datasetColumnId', _POST, _INT);

        Kit::ClassLoader('datasetcolumn');
        $dataSetColumnObject = new DataSetColumn();
        if (!$dataSetColumnId = $dataSetColumnObject->Delete($dataSetColumnId))
            return $this->Error($dataSetColumnObject->GetErrorNumber(), $dataSetColumnObject->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * DataSet Data List
     * @return <XiboAPIResponse>
     */
    public function DataSetDataList() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->view)
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('datasetdata');
        $dataSetObject = new DataSetData();
        if (!$columns = $dataSetObject->GetData($dataSetId))
            return $this->Error($dataSetObject->GetErrorNumber(), $dataSetObject->GetErrorMessage());

        return $this->Respond($this->NodeListFromArray($columns, 'datasetdata'));
    }

    /**
     * DataSet Data Add
     * @return <XiboAPIResponse>
     */
    public function DataSetDataAdd() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            return $this->Error(1, 'Access Denied');

        // Parameters
        $dataSetColumnId = $this->GetParam('dataSetColumnId', _INT);
        $rowNumber = $this->GetParam('rowNumber', _INT);
        $value = $this->GetParam('value', _STRING);

        // Use the DataSetData class to do the insert
        Kit::ClassLoader('datasetdata');
        $data = new DataSetData();

        if (!$id = $data->Add($dataSetColumnId, $rowNumber, $value))
            return $this->Error($data->GetErrorNumber(), $data->GetErrorMessage());

        return $this->Respond($this->ReturnId('datasetdata', $id));
    }

    /**
     * DataSet Data Edit
     * @return <XiboAPIResponse>
     */
    public function DataSetDataEdit() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            return $this->Error(1, 'Access Denied');

        // Parameters
        $dataSetColumnId = $this->GetParam('dataSetColumnId', _INT);
        $rowNumber = $this->GetParam('rowNumber', _INT);
        $value = $this->GetParam('value', _STRING);

        // Use the DataSetData class to do the update
        Kit::ClassLoader('datasetdata');
        $data = new DataSetData();

        if (!$data->Edit($dataSetColumnId, $rowNumber, $value))
            return $this->Error($data->GetErrorNumber(), $data->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * DataSet Data Delete
     * @return <XiboAPIResponse>
     */
    public function DataSetDataDelete() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->del)
            return $this->Error(1, 'Access Denied');

        // Parameters
        $dataSetColumnId = $this->GetParam('dataSetColumnId', _INT);
        $rowNumber = $this->GetParam('rowNumber', _INT);

        // Use the DataSetData class to do the delete
        Kit::ClassLoader('datasetdata');
        $data = new DataSetData();

        if (!$id = $data->Delete($dataSetColumnId, $rowNumber))
            return $this->Error($data->GetErrorNumber(), $data->GetErrorMessage());

        return $this->Respond($this->ReturnId('datasetdata', $id));
    }

    /**
     * DataSet Security List
     * @return <XiboAPIResponse>
     */
    public function DataSetSecurityList() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->view)
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('datasetgroupsecurity');
        $security = new DataSetGroupSecurity();

        if (!$results = $security->ListSecurity($dataSetId, $this->user->getGroupFromId($this->user->userid, true))) {
            return $this->Error($security->GetErrorNumber(), $security->GetErrorMessage());
        }

        return $this->Respond($this->NodeListFromArray($results, 'datasetgroupsecurity'));
    }

    /**
     * DataSet Security Add
     * @return <XiboAPIResponse>
     */
    public function DataSetSecurityAdd() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->modifyPermissions)
            return $this->Error(1, 'Access Denied');

        $groupId = $this->GetParam('groupId', _INT);
        $view = $this->GetParam('view', _INT);
        $edit = $this->GetParam('edit', _INT);
        $del = $this->GetParam('delete', _INT);

        Kit::ClassLoader('datasetgroupsecurity');
        $security = new DataSetGroupSecurity();

        if (!$results = $security->Link($dataSetId, $groupId, $view, $edit, $del)) {
            return $this->Error($security->GetErrorNumber(), $security->GetErrorMessage());
        }

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * DataSet Security Delete
     * @return <XiboAPIResponse>
     */
    public function DataSetSecurityDelete() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->modifyPermissions)
            return $this->Error(1, 'Access Denied');

        $groupId = $this->GetParam('groupId', _INT);

        Kit::ClassLoader('datasetgroupsecurity');
        $security = new DataSetGroupSecurity();

        if (!$results = $security->Unlink($dataSetId, $groupId)) {
            return $this->Error($security->GetErrorNumber(), $security->GetErrorMessage());
        }

        return $this->Respond($this->ReturnId('success', true));
    }

    /**
     * DataSet Import CSV
     * @return <XiboAPIResponse>
     */
    public function DataSetImportCsv() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        $dataSetId = $this->GetParam('dataSetId', _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            return $this->Error(1, 'Access Denied');

        // Expect a file id
        $fileId = $this->GetParam('fileId', _INT);

        if (!$this->user->FileAuth($fileId))
            return $this->Error(1, 'Access Denied');

        $file = new File();

        if (!$csvFileLocation = $file->GetPath($fileId))
            return $this->Error($file->GetErrorNumber(), $file->GetErrorMessage());

        // Other parameters
        // Filter using HTML string because _STRING strips some of the JSON characters.
        $spreadSheetMapping = $this->GetParam('spreadSheetMapping', _HTMLSTRING);
        $overwrite = $this->GetParam('overwrite', _INT);
        $ignoreFirstRow = $this->GetParam('ignoreFirstRow', _INT);

        // Convert the spread sheet mapping into an Array
        $spreadSheetMapping = json_decode($spreadSheetMapping, true);

        // Check that the columns match the columns for this dataset
        $dataSetColumnObject = new DataSetColumn();

        // Make an array with the datasetcolumnid as the key
        $columns = array();
        foreach ($dataSetColumnObject->GetColumns($dataSetId) as $col) {
            $columns[$col['datasetcolumnid']] = true;
        }

        // Look through each column we have been provided and see if it matches
        foreach ($spreadSheetMapping as $key => $value) {
            if (!array_key_exists($value, $columns))
                return $this->Error(1000, __('The column mappings you have provided are invalid. Please ensure you have the correct DataSetColumnIDs.'));
        }

        $dataSetObject = new DataSetData();

        if (!$dataSetObject->ImportCsv($dataSetId, $csvFileLocation, $spreadSheetMapping, ($overwrite == 1), ($ignoreFirstRow == 1)))
            return $this->Error($dataSetObject->GetErrorNumber(), $dataSetObject->GetErrorMessage());

        return $this->Respond($this->ReturnId('success', true));
    }

    public function DataTypeList() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('dataset');
        $dataSet = new DataSet();

        return $this->Respond($this->NodeListFromArray($dataSet->GetDataTypes(), 'datatype'));
    }

    public function DataSetColumnTypeList() {
        // Auth
        if (!$this->user->PageAuth('dataset'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('dataset');
        $dataSet = new DataSet();

        return $this->Respond($this->NodeListFromArray($dataSet->GetDataSetColumnTypes(), 'datasetcolumntype'));
    }

    /**
     * Returns the Xibo Server version information
     * @return <type>
     */
    public function Version()
    {
        $version = Config::Version();

        Debug::LogEntry('audit', 'Called Version');

        $xmlDoc = new DOMDocument();
        $xmlElement = $xmlDoc->createElement('version');

        foreach ($version as $key => $value)
        {
            $xmlElement->setAttribute($key, $value);
        }

        return $this->Respond($xmlElement);
    }

    /**
     * GetParam
     * @param <string> $param
     * @param <int> $type
     * @param <type> $default
     * @return <type>
     */
    protected function GetParam($param, $type, $default = null)
    {
        return Kit::GetParam($param, $this->POST, $type, $default);
    }
}
?>
