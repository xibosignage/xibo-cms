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

    public function __construct(database $db, User $user)
    {
        $this->db =& $db;
        $this->user =& $user;
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
}
?>
