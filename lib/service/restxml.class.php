<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
        header('Content-Type: text/xml; charset=utf8');

        // Commit back any open transactions if we are in an error state
        try {
            $dbh = PDOConnect::init();
            $dbh->commit();
        }
        catch (Exception $e) {
            Debug::LogEntry('audit', 'Unable to commit');
        }

        $xmlDoc = new DOMDocument();
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
        Debug::LogEntry('audit', $xmlDoc->saveXML(), 'RestXml', 'Respond');

        // Return it as a string
        return $xmlDoc->saveXML();
    }

    public function Error($errorNo, $errorMessage = '')
    {
        header('Content-Type: text/xml; charset=utf8');
        
        Debug::LogEntry('audit', $errorMessage, 'RestXml', 'Error');

        // Roll back any open transactions if we are in an error state
        try {
            $dbh = PDOConnect::init();
            $dbh->rollBack();
        }
        catch (Exception $e) {
            Debug::LogEntry('audit', 'Unable to rollback');
        }

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
        Debug::LogEntry('audit', $xmlDoc->saveXML());

        // Return it as a string
        return $xmlDoc->saveXML();
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
        $xmlDoc = new DOMDocument();
        $xmlElement = $xmlDoc->createElement($nodeName);
        $xmlElement->setAttribute($idAttributeName, $id);

        return $xmlElement;
    }

    /**
     * Returns a single node with the attributes contained in a key/value array
     * @param string $nodeName
     * @param array $attributes
     * @return DOMElement
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
     * @param array $array
     * @param string $nodeName
     * @return DOMElement
     */
    protected function NodeListFromArray($array, $nodeName)
    {
        Debug::LogEntry('audit', sprintf('Building node list containing %d items', count($array)));

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