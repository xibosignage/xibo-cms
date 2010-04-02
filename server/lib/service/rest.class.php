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

    public function __construct(database $db, User $user, $_POST)
    {
        $this->db =& $db;
        $this->user =& $user;

        // Hold the POST data
        $POST = $_POST;
    }

    public function MediaList()
    {
        if (!$media = $this->user->MediaList())
            return $this->Error(1);

        $xmlDoc = new DOMDocument();
        $xmlElement = $xmlDoc->createElement('mediaItems');
        $xmlElement->setAttribute('length', count($media));
        
        // Create the XML nodes
        foreach($media as $mediaItem)
        {
            $mediaNode = $xmlDoc->createElement('media');
            foreach($mediaItem as $key => $value)
            {
                $mediaNode->setAttribute($key, $value);
            }
            $xmlElement->appendChild($mediaNode);
        }

        return $this->Respond($xmlElement);
    }

    /**
     * Media File Upload
     * Upload a media file in parts
     * @return <XiboAPIResponse>
     */
    public function MediaFileUpload()
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
            // New upload
            if (!$fileId = $file->NewFile($payload, $this->user->userid))
                return $this->Error($file->GetErrorNumber());
        }
        else
        {
            // Continue upload
            if (!$file->Append($fileId, $payload, $this->user->userid))
                return $this->Error($file->GetErrorNumber());
        }

        // Return the fileId
        return $this->Respond($this->ReturnId('file', $fileId));
    }

    /**
     * Add a media file to the library
     */
    public function MediaAdd()
    {
        // TODO: Does this user have permission to call this webservice method? Do via PageAuth?
        Kit::ClassLoader('Media');

        // Create a media object and gather the required parameters.
        $media          = new Media($this->db);
        $type           = $this->GetParam('type', _WORD);
        $name           = $this->GetParam('name', _STRING);
        $duration       = $this->GetParam('duration', _INT);
        $fileName       = $this->GetParam('fileName', _FILENAME);
        $permissionId   = $this->GetParam('permissionID', _INT);

        // Add the media.
        if (!$mediaId = $media->Add($type, $name, $duration, $fileName, $permissionId, $this->user->userid))
            return $this->Error($media->GetErrorNumber());

        // Return the mediaId.
        return $this->Respond($this->ReturnId('media', $mediaId));
    }

    /**
     * Edit a media file in the library
     */
    public function MediaEdit()
    {

    }

    /**
     * Retire a media file in the library
     */
    public function MediaRetire()
    {

    }

    /**
     * Delete a Media file from the library
     */
    public function MediaDelete()
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
    protected function GetParam($param, $type, $default = '')
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
}
?>
