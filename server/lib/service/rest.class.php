<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2010 Daniel Garner
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

    public function MediaList()
    {
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
        // TODO: Does this user have permission to call this webservice method? Do via PageAuth?

        Kit::ClassLoader('file');

        $file           = new File($this->db);
        $fileId         = $this->GetParam('fileId', _INT);
        $checkSum       = $this->GetParam('checkSum', _STRING);
        $payload        = $this->GetParam('payload', _STRING);

        // Checksum the payload
        if (md5($payload) != $checkSum)
            return $this->Error(2);

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
        // TODO: Does this user have permission to call this webservice method? Do via PageAuth?
        Kit::ClassLoader('Media');

        // Create a media object and gather the required parameters.
        $media          = new Media($this->db);
        $fileId         = $this->GetParam('fileId', _INT);
        $type           = $this->GetParam('type', _WORD);
        $name           = $this->GetParam('name', _STRING);
        $duration       = $this->GetParam('duration', _INT);
        $fileName       = $this->GetParam('fileName', _FILENAME);
        $permissionId   = $this->GetParam('permissionID', _INT);

        // Check permissions
        if (!$this->user->FileAuth($fileId))
            return $this->Error(1, 'Access Denied');

        // Add the media.
        if (!$mediaId = $media->Add($fileId, $type, $name, $duration, $fileName, $permissionId, $this->user->userid))
            return $this->Error($media->GetErrorNumber());

        // Return the mediaId.
        return $this->Respond($this->ReturnId('media', $mediaId));
    }

    /**
     * Edit a media file in the library
     */
    public function LibraryMediaEdit()
    {

    }

    /**
     * Retire a media file in the library
     */
    public function LibraryMediaRetire()
    {

    }

    /**
     * Delete a Media file from the library
     */
    public function LibraryMediaDelete()
    {

    }

    public function LayoutList()
    {
        $layout = $this->user->LayoutList();

        if (!is_array($layout))
            return $this->Error(2);

        return $this->Respond($this->NodeListFromArray($layout, 'layout'));
    }

    public function LayoutAdd()
    {
        Kit::ClassLoader('layout');
        
        $layout         = $this->GetParam('layout', _STRING);
        $description    = $this->GetParam('description', _STRING);
        $permissionid   = $this->GetParam('permissionid', _INT);
        $tags           = $this->GetParam('tags', _STRING);
        $templateId     = $this->GetParam('templateid', _INT, 0);

        // Add this layout
        $layoutObject = new Layout($this->db);

        if(!$id = $layoutObject->Add($layout, $description, $permissionid, $tags, $this->user->userid, $templateId))
            return $this->Error(3, $layoutObject->GetErrorMessage());

        Debug::LogEntry($this->db, 'audit', 'Added new layout with id' . $id);

        return $this->Respond($this->ReturnId('layout', $id));
    }

    public function LayoutEdit()
    {

    }

    public function LayoutUpdateXlf()
    {

    }

    public function LayoutBackground()
    {

    }

    public function LayoutDelete()
    {

    }

    public function LayoutRegionAdd()
    {

    }

    public function LayoutRegionEdit()
    {

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
