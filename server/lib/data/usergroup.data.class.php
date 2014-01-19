<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-11 Daniel Garner
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

class UserGroup extends Data
{
    /**
     * Adds a User Group to Xibo
     * @return
     * @param $UserGroup Object
     * @param $isDisplaySpecific Object
     * @param $description Object[optional]
     */
    public function Add($group, $isUserSpecific)
    {
        Debug::LogEntry('audit', 'IN', 'UserGroup', 'Add');
        
        try {
            $dbh = PDOConnect::init();
        
            // Validation
            if ($group == '')
                $this->ThrowError(__('Group Name cannot be empty.'));

            $sth = $dbh->prepare('INSERT INTO `group` (`group`, IsUserSpecific) VALUES (:group, :isuserspecific)');
            $sth->execute(array(
                    'group' => $group,
                    'isuserspecific' => $isUserSpecific
                ));

            $groupID = $dbh->lastInsertId();
    
            Debug::LogEntry('audit', 'OUT', 'UserGroup', 'Add');
    
            return $groupID;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                return $this->SetError(25000, __('Could not add User Group'));
        
            return false;
        }
    }

    /**
     * Edits an existing Xibo Display Group
     * @return
     * @param $userGroupID Object
     * @param $UserGroup Object
     */
    public function Edit($userGroupID, $userGroup)
    {
        Debug::LogEntry('audit', 'IN', 'UserGroup', 'Edit');

        try {
            $dbh = PDOConnect::init();

            // Validation
            if ($userGroupID == 0)
                $this->ThrowError(__('User Group not selected'));
            
            if ($userGroup == '')
                $this->ThrowError(__('User Group Name cannot be empty.'));
        
            $sth = $dbh->prepare('UPDATE `group` SET `group` = :group WHERE groupid = :groupid');
            $sth->execute(array(
                    'group' => $userGroup,
                    'groupid' => $userGroupID
                ));
    
            Debug::LogEntry('audit', 'OUT', 'UserGroup', 'Edit');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                return $this->SetError(25005, __('Could not edit User Group'));
        
            return false;
        }
    }

    /**
     * Deletes an Xibo User Group
     * @return
     * @param $userGroupID Object
     */
    public function Delete($userGroupID)
    {
        Debug::LogEntry('audit', 'IN', 'UserGroup', 'Delete');

        try {
            $dbh = PDOConnect::init();
            
            $params = array('groupid' => $userGroupID);

            // Delete all menu links
            $sth = $dbh->prepare('DELETE FROM lkmenuitemgroup WHERE GroupID = :groupid');
            $sth->execute($params);

            // Delete all page links
            $sth = $dbh->prepare('DELETE FROM lkpagegroup WHERE GroupID = :groupid');
            $sth->execute($params);

            // Delete the user group
            $sth = $dbh->prepare('DELETE FROM `group` WHERE GroupID = :groupid');
            $sth->execute($params);
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                return $this->SetError(25015,__('Unable to delete User Group.'));
        
            return false;
        }
    }

    /**
     * Links a User to a User Group
     * @return
     * @param $userGroupID Object
     * @param $userID Object
     */
    public function Link($userGroupID, $userID)
    {
        Debug::LogEntry('audit', 'IN', 'UserGroup', 'Link');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('INSERT INTO   lkusergroup (GroupID, UserID) VALUES (:groupid, :userid)');
            $sth->execute(array(
                    'groupid' => $userGroupID,
                    'userid' => $userID
                ));

            Debug::LogEntry('audit', 'OUT', 'UserGroup', 'Link');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25005, __('Could not Link User Group to User'));
        
            return false;
        }
    }

    /**
     * Unlinks a Display from a Display Group
     * @return
     * @param $userGroupID Object
     * @param $userID Object
     */
    public function Unlink($userGroupID, $userID)
    {
        Debug::LogEntry('audit', 'IN', 'UserGroup', 'Unlink');
        
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lkusergroup WHERE GroupID = :groupid AND UserID = :userid');
            $sth->execute(array(
                    'groupid' => $userGroupID,
                    'userid' => $userID
                ));
        
            Debug::LogEntry('audit', 'OUT', 'UserGroup', 'Unlink');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25007, __('Could not Unlink User from User Group'));
        
            return false;
        }
    }

    /**
     * Unlinks all users from the speficied group
     * @param <type> $userGroupId
     */
    public function UnlinkAllUsers($userGroupId)
    {
        Debug::LogEntry('audit', 'IN', 'UserGroup', 'UnlinkAllUsers');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lkusergroup WHERE GroupID = :groupid');
            $sth->execute(array(
                    'groupid' => $userGroupID
                ));

            Debug::LogEntry('audit', 'OUT', 'UserGroup', 'UnlinkAllUsers');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25007, __('Could not Unlink all Users from User Group'));
        
            return false;
        }
    }

    /**
     * Unliks all groups from the specified user
     * @param <type> $userId
     */
    public function UnlinkAllGroups($userId)
    {
        Debug::LogEntry('audit', 'IN', 'UserGroup', 'UnlinkAllGroups');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lkusergroup WHERE UserID = :userid');
            $sth->execute(array(
                    'userid' => $userID
                ));

            Debug::LogEntry('audit', 'OUT', 'UserGroup', 'UnlinkAllGroups');

            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25007, __('Could not Unlink Groups from User'));
        
            return false;
        }
    }

    /**
     * Edits the User Group associated with a User
     * @return
     * @param $userID Object
     * @param $userName Object
     */
    public function EditUserGroup($userID, $userName)
    {
        Debug::LogEntry('audit', 'IN', 'UserGroup', 'EditUserGroup');

        try {
            $dbh = PDOConnect::init();

            // Get the UserGroupID for this UserID
            $SQL  = "SELECT `group`.GroupID ";
            $SQL .= "FROM   `group` ";
            $SQL .= "       INNER JOIN lkusergroup ";
            $SQL .= "       ON     lkusergroup.GroupID = `group`.groupID ";
            $SQL .= "WHERE  `group`.IsUserSpecific     = 1 ";
            $SQL .= "   AND lkusergroup.UserID = :userid";


            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                   'userid'  => $userID
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(25005, __('Unable to get the UserGroup for this User.'));
    
            $userGroupID = Kit::ValidateParam($row['GroupID'], _INT);
    
            if ($userGroupID == 0)
            {
                // We should always have 1 display specific UserGroup for a display.
                // Do we a) Error here and give up?
                //         b) Create one and link it up?
                // $this->SetError(25006, __('Unable to get the UserGroup for this Display'));
    
                if (!$userGroupID = $this->Add($userName, 1))
                    $this->ThrowError(25001, __('Could not add a user group for this user.'));
    
                // Link the Two together
                if (!$this->Link($userGroupID, $userID))
                    $this->ThrowError(25001, __('Could not link the new user with its group.'));
            }
            else
            {
                if (!$this->Edit($userGroupID, $userName)) 
                    throw new Exception("Error Processing Request", 1);
            }
            
            Debug::LogEntry('audit', 'OUT', 'UserGroup', 'EditUserGroup');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }
}
?>