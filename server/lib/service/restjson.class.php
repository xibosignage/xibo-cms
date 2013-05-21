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
class RestJson extends Rest
{
    public function Respond(DOMElement $xmlElement)
    {
        // Prepare an array to be JSON encoded
        return '';
    }

    public function Error($errorNo)
    {
        // Error array
        $errorMessage = '';

        $array = array('resp' => array('status'));

        return json_encode($array);
    }

    /**
     *
     * @param DOMElement $xmlElement
     * @return <string>
     */
    public function Respond($array)
    {
        header('Content-Type: text/json; charset=utf8');

        $array['rsp']['status'] = 'error';
        

        // Log it
        Debug::LogEntry($this->db, 'audit', $xmlDoc->saveXML(), 'RestXml', 'Respond');

        // Return it as a string
        return $xmlDoc->saveXML();
    }

    public function Error($errorNo, $errorMessage = '')
    {
        Debug::LogEntry($this->db, 'audit', $errorMessage, 'RestXml', 'Error');

        header('Content-Type: text/json; charset=utf8');

        // Output the error doc
        $xmlDoc = new DOMDocument('1.0');
        $xmlDoc->formatOutput = true;

        $response['rsp']['status'] = 'error';
        $response['rsp']['status']['error']['code'] = $errorNo;
        $response['rsp']['status']['error']['message'] = $errorMessage;

        $return = json_encode($response);

        // Log it
        Debug::LogEntry($this->db, 'audit', $return);

        // Return it as a string
        return $return;
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
        return array($nodeName => array($idAttributeName => $id));
    }

    /**
     * Returns a single node with the attributes contained in a key/value array
     * @param <type> $nodeName
     * @param <type> $attributes
     * @return <DOMDocument::XmlElement>
     */
    protected function ReturnAttributes($nodeName, $attributes)
    {
        return array($nodeName => $attributes);
    }

    /**
     * Creates a node list from an array
     * @param <type> $array
     * @param <type> $node
     */
    protected function NodeListFromArray($array, $nodeName)
    {
        Debug::LogEntry($this->db, 'audit', sprintf('Building node list containing %d items', count($array)));

        return array($nodeName => $array);
    }
}
?>
