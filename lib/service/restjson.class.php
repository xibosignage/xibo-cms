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
class RestJson extends Rest
{
    /**
     * Output the Response
     * @param array $array
     * @return string
     */
    public function Respond($array)
    {
        header('Content-Type: text/json; charset=utf8');

        // Commit back any open transactions if we are in an error state
        try {
            $dbh = PDOConnect::init();
            $dbh->commit();
        }
        catch (Exception $e) {
            Debug::LogEntry('audit', 'Unable to commit');
        }

        // Create a response
        $response = json_encode($array);

        // Log it
        Debug::LogEntry('audit', $response, 'RestJson', 'Respond');

        // Return it as a string
        return $response;
    }

    public function Error($errorNo, $errorMessage = '')
    {
        header('Content-Type: text/json; charset=utf8');
        
        Debug::LogEntry('audit', $errorMessage, 'RestJson', 'Error');

        // Roll back any open transactions if we are in an error state
        try {
            $dbh = PDOConnect::init();
            $dbh->rollBack();
        }
        catch (Exception $e) {
            Debug::LogEntry('audit', 'Unable to rollback', 'RestJson', 'Error');
        }

        // Error
        $array = array(
                'status' => 'error', 
                'error' => array(
                    'code' => $errorNo, 
                    'message' => $errorMessage
                )
            );

        $return = json_encode($array);

        // Log it
        Debug::LogEntry('audit', $return, 'RestJson', 'Error');

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
        Debug::LogEntry('audit', sprintf('Building node list containing %d items', count($array)));

        return array($nodeName => $array);
    }
}
?>
