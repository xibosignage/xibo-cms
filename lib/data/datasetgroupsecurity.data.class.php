<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-13 Daniel Garner
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
defined('XIBO') or die('Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.');

class DataSetGroupSecurity extends Data
{
    public function ListSecurity($dataSetId, $groupId) {

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));

        $userGroup = new UserGroup();
        if (!$result = $userGroup->GetPermissionsForObject('lkdatasetgroup', 'DataSetID', $dataSetId))
            return $this->SetError($userGroup->GetErrorMessage());

        $security = array();

        foreach($result as $row) {
            $security[] = array(
                    'groupid' => Kit::ValidateParam($row['groupid'], _INT),
                    'group' => Kit::ValidateParam($row['group'], _STRING),
                    'view' => Kit::ValidateParam($row['view'], _INT),
                    'edit' => Kit::ValidateParam($row['edit'], _INT),
                    'del' => Kit::ValidateParam($row['del'], _INT),
                    'isuserspecific' => Kit::ValidateParam($row['isuserspecific'], _INT),
                );
        }
      
        return $security;
    }

    /**
     * Links a Display Group to a Group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Link($dataSetId, $groupId, $view, $edit, $del)
    {
        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));

        if ($groupId == 0 || $groupId == '')
            return $this->SetError(25001, __('Missing groupId'));

        try {
            $dbh = PDOConnect::init();

            $SQL  = "";
            $SQL .= "INSERT INTO lkdatasetgroup (DataSetID, GroupID, View, Edit, Del) ";
            $SQL .= " VALUES (:datasetid, :groupid, :view, :edit, :del) ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'datasetid' => $dataSetId,
                    'groupid' => $groupId,
                    'view' => $view,
                    'edit' => $edit,
                    'del' => $del
              ));

            Debug::LogEntry('audit', 'OUT', 'DataSetGroupSecurity', 'Link');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25024, __('Could not Link DataSet to Group'));
        }
    }

    /**
     * Links everyone to the layout specified
     * @param <type> $layoutId
     * @param <type> $view
     * @param <type> $edit
     * @param <type> $del
     * @return <type>
     */
    public function LinkEveryone($dataSetId, $view, $edit, $del)
    {
        Debug::LogEntry('audit', 'IN', 'DataSetGroupSecurity', 'LinkEveryone');

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));
        
        try {
            $dbh = PDOConnect::init();

            // Get the Group ID for Everyone
            $sth = $dbh->prepare('SELECT GroupID FROM `group` WHERE IsEveryone = 1');
            $sth->execute();

            if (!$row = $sth->fetch())
                throw new Exception('Missing Everyone group');

            // Link
            return $this->Link($dataSetId, Kit::ValidateParam($row['GroupID'], _INT), $view, $edit, $del);
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25024, __('Could not Link DataSet to Group'));
        }
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Unlink($dataSetId, $groupId)
    {
        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));

        if ($groupId == 0 || $groupId == '')
            return $this->SetError(25001, __('Missing groupId'));
        
        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM lkdatasetgroup WHERE DataSetID = :datasetid AND GroupID = :groupid');
            $sth->execute(array(
                    'datasetid' => $dataSetId,
                    'groupid' => $groupId
                ));

            Debug::LogEntry('audit', 'OUT', 'DataSetGroupSecurity', 'Unlink');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25025, __('Could not Unlink DataSet from Group'));
        }
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($dataSetId)
    {
        Debug::LogEntry('audit', 'IN', 'DataSetGroupSecurity', 'UnlinkAll');

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM lkdatasetgroup WHERE DataSetID = :datasetid');
            $sth->execute(array(
                    'datasetid' => $dataSetId
                ));

            Debug::LogEntry('audit', 'OUT', 'DataSetGroupSecurity', 'UnlinkAll');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25025, __('Could not Unlink DataSet from Group'));
        }
    }
}
?>