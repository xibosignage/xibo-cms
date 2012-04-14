<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2010-2012 Daniel Garner
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
    protected $db;
    protected $user;
    protected $POST;

    public function __construct(database $db, User $user, $postData)
    {
        $this->db =& $db;
        $this->user =& $user;

        // Hold the POST data
        $this->POST = $postData;
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

        $displayObject = new Display($this->db);
        $displaId = $this->GetParam('displayId', _INT);

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
        if (!$this->user->PageAuth('media'))
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
        if (!$this->user->PageAuth('media'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('file');

        $file           = new File($this->db);
        $fileId         = $this->GetParam('fileId', _INT);
        $checkSum       = $this->GetParam('checksum', _STRING);
        $payload        = $this->GetParam('payload', _STRING);
        $payloadMd5     = md5($payload);

        // Checksum the payload
        if ($payloadMd5 != $checkSum)
        {
            // Debug::LogEntry($this->db, 'audit', 'Sent Checksum: ' . $checkSum, 'RestXml', 'LibraryMediaFileUpload');
            // Debug::LogEntry($this->db, 'audit', 'Calculated Checksum: ' . $payloadMd5, 'RestXml', 'LibraryMediaFileUpload');
            // Debug::LogEntry($this->db, 'audit', 'Payload: ' . $payload, 'RestXml', 'LibraryMediaFileUpload');

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
        if (!$this->user->PageAuth('media'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        // Create a media object and gather the required parameters.
        $media          = new Media($this->db);
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
        if (!$this->user->PageAuth('media'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        // Create a media object and gather the required parameters.
        $media          = new Media($this->db);
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
        if (!$this->user->PageAuth('media'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        $media      = new Media($this->db);
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
        if (!$this->user->PageAuth('media'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        $media      = new Media($this->db);
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
        if (!$this->user->PageAuth('media'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        // Create a media object and gather the required parameters.
        $media          = new Media($this->db);
        $mediaId        = $this->GetParam('mediaId', _INT);
        $fileId         = $this->GetParam('fileId', _INT);
        $fileName       = $this->GetParam('fileName', _FILENAME);
        
        // Check permissions
        if (!$this->user->FileAuth($fileId))
            return $this->Error(1, 'Access Denied');

        // Add the media.
        if (!$mediaId = $media->FileRevise($mediaId, $fileId, $fileName))
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
            return $this->Error(2);

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

        // Add this layout
        $layoutObject = new Layout($this->db);

        if(!$id = $layoutObject->Add($layout, $description, $tags, $this->user->userid, $templateId))
            return $this->Error($layoutObject->GetErrorNumber(), $layoutObject->GetErrorMessage());

        Debug::LogEntry($this->db, 'audit', 'Added new layout with id' . $id);

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

        $layout     = new Layout($this->db);
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

        $layout     = new Layout($this->db);
        $layoutId   = $this->GetParam('layoutId', _INT);

        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
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

        $layout     = new Layout($this->db);
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

        $layout     = new Layout($this->db);
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

        $layout     = new Layout($this->db);
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

        $layout     = new Layout($this->db);
        $layoutId   = $this->GetParam('layoutId', _INT);

        if (!$this->user->LayoutAuth($layoutId))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Get the Xlf for a Layout
     * @return <XiboAPIResponse>
     */
    public function LayoutGetXlf()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Layout');

        $layout     = new Layout($this->db);
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

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Add Region to a Layout
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionAdd()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Edit Region on a layout
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionEdit()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Position Region on a Layout
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionPosition()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * List the items on a region timeline
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionTimelineList()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Add Media to a Region
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionMediaAdd()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Edit Media on a Region
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionMediaEdit()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Details of Media on a Region
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionMediaDetails()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Reorder media on a region
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionMediaReorder()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Delete media from a region
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionMediaDelete()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
    }

    /**
     * Add media to a region from the Library
     * @return <XiboAPIResponse>
     */
    public function LayoutRegionLibraryAdd()
    {
        if (!$this->user->PageAuth('layout'))
            return $this->Error(1, 'Access Denied');

        return $this->Error(1000, 'Not implemented');
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

        Kit::ClassLoader('Template');

        $template     = new Template($this->db);
        $templateId   = $this->GetParam('templateId', _INT);

        if (!$this->user->TemplateAuth($templateId))
            return $this->Error(1, 'Access Denied');

        if (!$template->Delete($templateId))
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
        if (!$this->user->PageAuth('media'))
            return $this->Error(1, 'Access Denied');

        Kit::ClassLoader('Media');

        // Create a media object and gather the required parameters.
        $media = new Media($this->db);

        if (!$modules = $media->ModuleList())
            return $this->Error($media->GetErrorNumber(), $media->GetErrorMessage());

        return $this->Respond($this->NodeListFromArray($modules, 'module'));
    }

    /**
     * Returns the Xibo Server version information
     * @return <type>
     */
    public function Version()
    {
        $version = Config::Version($this->db);

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

    /**
     * Returns an ID only response
     * @param <string> $nodeName
     * @param <string> $id
     * @param <string> $idAttributeName
     * @return <DOMDocument::XmlElement>
     */
    protected function ReturnId($nodeName, $id, $idAttributeName = 'id')
    {
        $xmlDoc = new DOMDocument();
        $xmlElement = $xmlDoc->createElement($nodeName);
        $xmlElement->setAttribute($idAttributeName, $id);

        return $xmlElement;
    }

    /**
     * Returns a single node with the attributes contained in a key/value array
     * @param <type> $nodeName
     * @param <type> $attributes
     * @return <DOMDocument::XmlElement>
     */
    protected function ReturnAttributes($nodeName, $attributes)
    {
        $xmlDoc = new DOMDocument();
        $xmlElement = $xmlDoc->createElement($nodeName);

        foreach ($attributes as $key => $value)
        {
            $xmlElement->setAttribute($key, $value);
        }

        return $xmlElement;
    }

    /**
     * Creates a node list from an array
     * @param <type> $array
     * @param <type> $node
     */
    protected function NodeListFromArray($array, $nodeName)
    {
        Debug::LogEntry($this->db, 'audit', sprintf('Building node list containing %d items', count($array)));

        $xmlDoc = new DOMDocument();
        $xmlElement = $xmlDoc->createElement($nodeName . 'Items');
        $xmlElement->setAttribute('length', count($array));

        // Create the XML nodes
        foreach($array as $arrayItem)
        {
            $node = $xmlDoc->createElement($nodeName);
            foreach($arrayItem as $key => $value)
            {
                $node->setAttribute($key, $value);
            }
            $xmlElement->appendChild($node);
        }

        return $xmlElement;
    }
}
?>
