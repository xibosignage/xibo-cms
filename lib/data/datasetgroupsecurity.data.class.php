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
use Xibo\Helper\Log;


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
                    'groupid' => \Xibo\Helper\Sanitize::int($row['groupid']),
                    'group' => \Xibo\Helper\Sanitize::string($row['group']),
                    'view' => \Xibo\Helper\Sanitize::int($row['view']),
                    'edit' => \Xibo\Helper\Sanitize::int($row['edit']),
                    'del' => \Xibo\Helper\Sanitize::int($row['del']),
                    'isuserspecific' => \Xibo\Helper\Sanitize::int($row['isuserspecific']),
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
            $dbh = \Xibo\Storage\PDOConnect::init();

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

            Log::notice('OUT', 'DataSetGroupSecurity', 'Link');

            return true;
        }
        catch (Exception $e) {
            Log::error($e->getMessage());
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
        Log::notice('IN', 'DataSetGroupSecurity', 'LinkEveryone');

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));
        
        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            // Get the Group ID for Everyone
            $sth = $dbh->prepare('SELECT GroupID FROM `group` WHERE IsEveryone = 1');
            $sth->execute();

            if (!$row = $sth->fetch())
                throw new Exception('Missing Everyone group');

            // Link
            return $this->Link($dataSetId, \Xibo\Helper\Sanitize::int($row['GroupID']), $view, $edit, $del);
        }
        catch (Exception $e) {
            Log::error($e->getMessage());
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
            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM lkdatasetgroup WHERE DataSetID = :datasetid AND GroupID = :groupid');
            $sth->execute(array(
                    'datasetid' => $dataSetId,
                    'groupid' => $groupId
                ));

            Log::notice('OUT', 'DataSetGroupSecurity', 'Unlink');

            return true;
        }
        catch (Exception $e) {
            Log::error($e->getMessage());
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
        Log::notice('IN', 'DataSetGroupSecurity', 'UnlinkAll');

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM lkdatasetgroup WHERE DataSetID = :datasetid');
            $sth->execute(array(
                    'datasetid' => $dataSetId
                ));

            Log::notice('OUT', 'DataSetGroupSecurity', 'UnlinkAll');

            return true;
        }
        catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->SetError(25025, __('Could not Unlink DataSet from Group'));
        }
    }
}
?>