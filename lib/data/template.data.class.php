<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

        try {
            $dbh = PDOConnect::init();
        
            $currentdate = date("Y-m-d H:i:s");
    
            // Validation
            if (strlen($template) > 50 || strlen($template) < 1)
                $this->ThrowError("Template Name must be between 1 and 50 characters");
            
            if (strlen($description) > 254) 
                $this->ThrowError("Description can not be longer than 254 characters");
            
            if (strlen($tags) > 254) 
                $this->ThrowError("Tags can not be longer than 254 characters");
            
            $sth = $dbh->prepare('SELECT template FROM template WHERE template = :template AND userID = :userid');
            $sth->execute(array(
                    'template' => $template,
                    'userid' => $userId
                ));

            if ($row = $sth->fetch())
                $this->ThrowError(__('You already own a template called "%s". Please choose another name.', $template));
            // End validation
            
            // Get the Layout XML (but reconstruct so that there are no media nodes in it)
            if (!$xml = $this->GetLayoutXmlNoMedia($layoutId))
                $this->ThrowError(__('Cannot get the Layout Structure.'));
            
            // Insert the template
            $SQL = "INSERT INTO template (template, tags, issystem, retired, description, createdDT, modifiedDT, userID, xml) ";
            $SQL.= "  VALUES (:template, :tags, :issystem, :retired, :description, :createddt, :modifieddt, :userid, :xml) ";
            
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'template' => $template,
                    'userid' => $userId,
                    'tags' => $tags,
                    'issystem' => 0,
                    'retired' => 0,
                    'description' => $description,
                    'createddt' => $currentdate,
                    'modifieddt' => $currentdate,
                    'xml' => $xml
                ));

            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                return $this->SetError("Unexpected error adding Template.");
        
            return false;
        }
    }

    /**
     * Deletes a layout
     * @param <type> $layoutId
     * @return <type>
     */
    public function Delete($templateId)
    {
        try {
            $dbh = PDOConnect::init();
        
            // Remove any permissions
            Kit::ClassLoader('templategroupsecurity');
            $security = new TemplateGroupSecurity($this->db);
            $security->UnlinkAll($templateId);
    
            // Remove the Template
            $sth = $dbh->prepare('DELETE FROM template WHERE TemplateId = :templateid');
            $sth->execute(array(
                    'templateid' => $templateId
                ));

            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25105, __('Unable to delete template'));
        
            return false;
        }
    }

    /**
     * Gets the Xml for the specified layout
     * @return 
     * @param $layoutid Object
     */
    private function GetLayoutXmlNoMedia($layoutid)
    {
        // Get the Xml for this Layout from the DB
        Kit::ClassLoader('layout');

        $layout = new Layout($this->db);
        $layoutXml = $layout->GetLayoutXml($layoutid);

        $xml = new DOMDocument("1.0");
        $xml->loadXML($layoutXml);
        
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
