<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-14 Daniel Garner
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

class Resolution extends Data
{
    /**
     * Adds a resolution
     * @param string $resolution
     * @param int $width
     * @param int $height
     * @return <type>
     */
    public function Add($resolution, $width, $height)
    {
        try {
            $dbh = PDOConnect::init();

            if ($resolution == '' || $width == '' || $height == '')
                $this->ThrowError(__('All fields must be filled in'));
    
            // Alter the width / height to fit with 800 px
            $factor = min (800 / $width, 800 / $height);
    
            $final_width    = round ($width * $factor);
            $final_height   = round ($height * $factor);

            $sth = $dbh->prepare('INSERT INTO resolution (resolution, width, height, intended_width, intended_height, version) VALUES (:resolution, :width, :height, :intended_width, :intended_height, :version)');
            $sth->execute(array(
                    'resolution' => $resolution,
                    'width' => $final_width,
                    'height' => $final_height,
                    'intended_width' => $width,
                    'intended_height' => $height,
                    'version' => 2
                ));
            
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                return $this->SetError(25000, __('Cannot add this resolution.'));
        
            return false;
        }
    }

    /**
     * Edits a resolution
     * @param <type> $resolutionID
     * @param <type> $resolution
     * @param <type> $width
     * @param <type> $height
     * @return <type>
     */
    public function Edit($resolutionID, $resolution, $width, $height, $enabled)
    {
        try {
            $dbh = PDOConnect::init();

            if ($resolution == '' || $width == '' || $height == '')
                $this->ThrowError(__('All fields must be filled in'));
    
            // Alter the width / height to fit with 800 px
            $factor = min (800 / $width, 800 / $height);
    
            $final_width    = round ($width * $factor);
            $final_height   = round ($height * $factor);
    
            $sth = $dbh->prepare('
                UPDATE resolution SET resolution = :resolution, 
                    width = :width, 
                    height = :height, 
                    intended_width = :intended_width, 
                    intended_height = :intended_height,
                    enabled = :enabled
                 WHERE resolutionID = :resolutionid');

            $sth->execute(array(
                    'resolution' => $resolution,
                    'width' => $final_width,
                    'height' => $final_height,
                    'intended_width' => $width,
                    'intended_height' => $height,
                    'resolutionid' => $resolutionID,
                    'enabled' => $enabled
                ));
            
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                return $this->SetError(25000, __('Cannot edit this resolution.'));
        
            return false;
        }
    }

    /**
     * Deletes a Resolution
     * @param <type> $resolutionID
     * @return <type>
     */
    public function Delete($resolutionID)
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM resolution WHERE resolutionID = :resolutionid');
            $sth->execute(array(
                    'resolutionid' => $resolutionID
                ));

            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25000, __('Cannot delete this resolution.'));
        
            return false;
        }
    }
}
?>