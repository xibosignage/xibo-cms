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
class RestXml extends Rest
{
    /**
     *
     * @param DOMElement $xmlElement
     * @return <string>
     */
    public function Respond(DOMElement $xmlElement)
    {
        $xmlDoc = new DOMDocument('1.0');
        $xmlDoc->formatOutput = true;

        // Create the response node
        $rootNode = $xmlDoc->createElement('rsp');

        // Set the status to OK
        $rootNode->setAttribute('status', 'ok');

        // Append the response node as the root
        $xmlDoc->appendChild($rootNode);

        // Import the node we got from the method call
        $node = $xmlDoc->importNode($xmlElement, true);

        // Append it to the response node (root node)
        $xmlDoc->documentElement->appendChild($node);

        // Log it
        Debug::LogEntry($this->db, 'audit', $xmlDoc->saveXML(), 'RestXml', 'Respond');

        // Return it as a string
        return $xmlDoc->saveXML();
    }

    public function Error($errorNo, $errorMessage = '')
    {
        Debug::LogEntry($this->db, 'audit', $errorMessage, 'RestXml', 'Error');

        // Output the error doc
        $xmlDoc = new DOMDocument('1.0');
        $xmlDoc->formatOutput = true;

        // Create the response node
        $rootNode = $xmlDoc->createElement('rsp');

        // Set the status to OK
        $rootNode->setAttribute('status', 'error');

        // Append the response node as the root
        $xmlDoc->appendChild($rootNode);

        // Create the error node
        $errorNode = $xmlDoc->createElement('error');
        $errorNode->setAttribute('code', $errorNo);
        $errorNode->setAttribute('message', $errorMessage);

        // Add the error node to the document
        $rootNode->appendChild($errorNode);

        // Log it
        Debug::LogEntry($this->db, 'audit', $xmlDoc->saveXML());

        // Return it as a string
        return $xmlDoc->saveXML();
    }
}
?>