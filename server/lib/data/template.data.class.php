<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2013 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class Template extends Data
{
    /**
     * Adds a template
     * @param string $template    [description]
     * @param string $description [description]
     * @param string $tags        [description]
     * @param int $layoutId    [description]
     * @param int $userId      [description]
     */
    public function Add($template, $description, $tags, $layoutId, $userId) {

        $db =& $this->db;
        $currentdate = date("Y-m-d H:i:s");

        Debug::LogEntry('audit', $template);

        // Validation
        if (strlen($template) > 50 || strlen($template) < 1)
            return $this->SetError("Template Name must be between 1 and 50 characters");
        
        if (strlen($description) > 254) 
            return $this->SetError("Description can not be longer than 254 characters");
        
        if (strlen($tags) > 254) 
            return $this->SetError("Tags can not be longer than 254 characters");
        
        // Check on the name the user has selected
        $SQL = sprintf("SELECT template FROM template WHERE template = '%s' AND userID = %d", $db->escape_string($template), $userId);
        
        if (!$result = $db->query($SQL)) {
            trigger_error($db->error());
            return $this->SetError(__('Validation check failed'));
        } 
        
        // Template with the same name?
        if($db->num_rows($result) != 0) 
            return $this->SetError(__('You already own a template called "%s". Please choose another name.', $template));
        // End validation
        
        // Get the Layout XML (but reconstruct so that there are no media nodes in it)
        if (!$xml = $this->GetLayoutXmlNoMedia($layoutId))
            return $this->SetError(__('Cannot get the Layout Structure.'));
        
        // Insert the template
        $SQL = "INSERT INTO template (template, tags, issystem, retired, description, createdDT, modifiedDT, userID, xml) ";
        $SQL.= sprintf("  VALUES ('%s', '%s', 0, 0, '%s', '%s', '%s', %d, '%s') ", $db->escape_string($template), $db->escape_string($tags), $db->escape_string($description), 
            $currentdate, $currentdate, $userId, $db->escape_string($xml));
        
        if (!$db->query($SQL)) 
        {
            trigger_error($db->error());
            return $this->SetError("Unexpected error adding Template.");
        }

        return true;
    }

    /**
     * Deletes a layout
     * @param <type> $layoutId
     * @return <type>
     */
    public function Delete($templateId)
    {
        $db =& $this->db;

        // Remove any permissions
        Kit::ClassLoader('templategroupsecurity');
        $security = new TemplateGroupSecurity($db);
        $security->UnlinkAll($templateId);

        // Remove the Template
        if (!$db->query(sprintf('DELETE FROM template WHERE TemplateId = %d', $templateId))) {
            trigger_error($db->error());
            return $this->SetError(25105, __('Unable to delete template'));
        }

        return true;
    }

    /**
     * Gets the Xml for the specified layout
     * @return 
     * @param $layoutid Object
     */
    private function GetLayoutXmlNoMedia($layoutid)
    {
        $db =& $this->db;
        
        //Get the Xml for this Layout from the DB
        $SQL = "SELECT xml FROM layout WHERE layoutID = $layoutid ";
        if (!$results = $db->query($SQL)) 
        {
            trigger_error($db->error());
            $errMsg = "Unable to Query for that layout, there is a database error.";
            return false;
        }
        $row = $db->get_row($results) ;
        
        $xml = new DOMDocument("1.0");
        $xml->loadXML($row[0]);
        
        $xpath = new DOMXPath($xml);
        
        //We want to get all the media nodes
        $mediaNodes = $xpath->query('//media');
        
        foreach ($mediaNodes as $node) 
        {
            $node->parentNode->removeChild($node);
        }
        
        return $xml->saveXML();
    }
}
?>
