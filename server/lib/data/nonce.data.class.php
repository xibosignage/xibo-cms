<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2012 Daniel Garner
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

class Nonce extends Data {
    
    // Xmds Nonce statements
    private $insertFileStatement;
    private $insertLayoutStatement;
    private $insertResourceStatement;

    private $validateNonceStatement;
    private $validateFileStatement;
    private $validateLayoutStatement;
    private $validateResourceStatement;

    public function RemoveAllXmdsNonce($displayId) {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM `xmdsnonce` WHERE displayId = :displayId');
            $sth->execute(array('displayId' => $displayId));
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        }
    }

    public function AddXmdsNonce($type, $displayId, $fileId = NULL, $size, $storedAs, $layoutId = NULL, $regionId = NULL, $mediaId = NULL) {
        try {
            $dbh = PDOConnect::init();

            $nonce = md5(uniqid() . SECRET_KEY . time() . $fileId . $layoutId . $regionId . $mediaId);

            $params = array(
                    'nonce' => $nonce,
                    'expiry' => time() + 86400,
                    'displayId' => $displayId
                );

            switch ($type) {
                case 'file':
                    if ($this->insertFileStatement == NULL) {
                        $this->insertFileStatement = $dbh->prepare('
                            INSERT INTO `xmdsnonce` (nonce, expiry, displayId, fileId, size, storedAs) 
                              VALUES (:nonce, :expiry, :displayId, :fileId, :size, :storedAs)');
                    }

                    $sth = $this->insertFileStatement;
                    $params['fileId'] = $fileId;
                    $params['size'] = $size;
                    $params['storedAs'] = $storedAs;
                    break;

                case 'layout':
                    if ($this->insertLayoutStatement == NULL) {
                        $this->insertLayoutStatement = $dbh->prepare('
                            INSERT INTO `xmdsnonce` (nonce, expiry, displayId, layoutId, size) 
                              VALUES (:nonce, :expiry, :displayId, :layoutId, :size)');
                    }

                    $sth = $this->insertLayoutStatement;
                    $params['layoutId'] = $layoutId;
                    $params['size'] = $size;
                    break;
                    
                case 'resource':
                    if ($this->insertResourceStatement == NULL) {
                        $this->insertResourceStatement = $dbh->prepare('
                            INSERT INTO `xmdsnonce` (nonce, expiry, displayId, layoutId, regionId, mediaId) 
                              VALUES (:nonce, :expiry, :displayId, :layoutId, :regionId, :mediaId)');
                    }

                    $sth = $this->insertResourceStatement;
                    $params['layoutId'] = $layoutId;
                    $params['regionId'] = $regionId;
                    $params['mediaId'] = $mediaId;
                    break;

                default:
                    trigger_error('Missing Nonce Type');
                    return false;
            }

            // Debug::LogEntry('audit', var_export($params, true), get_class(), __FUNCTION__);
        
            // Insert
            $sth->execute($params);

            return $nonce;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
            return false;
        }
    }

    public function AllowedFile($type, $displayId, $fileId = NULL, $layoutId = NULL, $regionId = NULL, $mediaId = NULL) {
        try {
            $dbh = PDOConnect::init();

            $params = array();

            switch ($type) {
                
                case 'file':
                    if ($this->validateFileStatement == NULL) {
                        $this->validateFileStatement = $dbh->prepare('
                            SELECT nonceId, nonce, expiry, lastUsed FROM `xmdsnonce` WHERE displayId = :displayId AND fileId = :fileId');
                    }

                    $sth = $this->validateFileStatement;
                    $params['fileId'] = $fileId;
                    $params['displayId'] = $displayId;
                    break;

                case 'oldfile':
                    if ($this->validateFileStatement == NULL) {
                        $this->validateFileStatement = $dbh->prepare('
                            SELECT nonceId, nonce, expiry, lastUsed FROM `xmdsnonce` INNER JOIN `media` ON media.mediaid = xmdsnonce.fileId WHERE displayId = :displayId AND media.storedAs = :fileId');
                    }

                    $sth = $this->validateFileStatement;
                    $params['fileId'] = $fileId;
                    $params['displayId'] = $displayId;
                    break;

                case 'layout':
                    if ($this->validateLayoutStatement == NULL) {
                        $this->validateLayoutStatement = $dbh->prepare('
                            SELECT nonceId, nonce, expiry, lastUsed FROM `xmdsnonce` WHERE displayId = :displayId AND layoutId = :layoutId ');
                    }

                    $sth = $this->validateLayoutStatement;
                    $params['displayId'] = $displayId;
                    $params['layoutId'] = $layoutId;
                    break;
                    
                case 'resource':
                    if ($this->validateResourceStatement == NULL) {
                        $this->validateResourceStatement = $dbh->prepare('
                            SELECT nonceId, nonce, expiry, lastUsed 
                              FROM `xmdsnonce`
                             WHERE displayId = :displayId 
                                AND layoutId = :layoutId 
                                AND regionId = :regionId 
                                AND mediaId = :mediaId');
                    }

                    $sth = $this->validateResourceStatement;
                    $params['displayId'] = $displayId;
                    $params['layoutId'] = $layoutId;
                    $params['regionId'] = $regionId;
                    $params['mediaId'] = $mediaId;
                    break;

                default:
                    trigger_error('Missing Nonce Type');
                    return false;
            }

            Debug::LogEntry('audit', var_export($params, true), get_class(), __FUNCTION__);
        
            // Check
            $sth->execute($params);
            $results = $sth->fetchAll();
            
            return (count($results) > 0);
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
            return false;
        }
    }

    public function Details($nonce) {

        try {
            $dbh = PDOConnect::init();
        
            if ($this->validateNonceStatement == NULL) {
                $this->validateNonceStatement = $dbh->prepare('
                    SELECT nonceId, nonce, expiry, IFNULL(lastUsed, 0) AS lastUsed, displayId, size, storedAs 
                      FROM `xmdsnonce` 
                     WHERE nonce = :nonce');
            }

            $sth = $this->validateNonceStatement;
            $sth->execute(array('nonce' => $nonce));

            $results = $sth->fetchAll();


            if (count($results) <= 0)
                return false;

            // Mark it as used
            $row = $results[0];

            $this->MarkUsed($row['nonceId']);

            // Check whether its valid or not
            if ($row['lastUsed'] != 0 || $row['expiry'] < time())
            //if ($row['expiry'] < time())
                return false;

            return $row;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    private function MarkUsed($id) {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('UPDATE `xmdsnonce` SET lastUsed = :lastUsed WHERE nonceId = :id');
            $sth->execute(array(
                    'id' => $id,
                    'lastUsed' => time()
                ));
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }
}
?>
