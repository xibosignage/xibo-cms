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

    public function MediaFileUpload()
    {
        Kit::ClassLoader('file');

        $file           = new File($this->db);
        $fileId         = $this->GetParam('fileId', _INT);
        $checkSum       = $this->GetParam('checkSum', _STRING);
        $payload        = $this->GetParam('payload', _STRING);

        if (md5($payload) != $checkSum)
            return $this->Error(2);

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
        $xmlDoc = new DOMDocument();
        $fileElement = $xmlDoc->createElement('file');
        $fileElement->setAttribute('id', $fileId);

        return $this->Respond($fileElement);
    }

    public function MediaAdd()
    {

    }

    public function MediaEdit()
    {

    }

    public function MediaRetire()
    {

    }
    
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
}
?>
