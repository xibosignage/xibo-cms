<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011 Daniel Garner
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

class datasetDAO
{
    private $db;
    private $user;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;

        Kit::ClassLoader('dataset');
    }

    function on_page_load()
    {
            return "";
    }

    function echo_page_heading()
    {
            echo __("Layouts");
            return true;
    }

    function displayPage()
    {
        require('template/pages/dataset_view.php');
    }

    public function DataSetFilter()
    {
        $id = uniqid();

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
                <div class="XiboFilter">
                        <form onsubmit="return false">
				<input type="hidden" name="p" value="dataset">
				<input type="hidden" name="q" value="DataSetGrid">
                        </form>
                </div>
                <div class="XiboData">

                </div>
        </div>
HTML;
        echo $xiboGrid;
    }

    public function DataSetGrid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $msgPermissions = __('Permissions');

        $output = <<<END
        <div class="info_table">
        <table style="width:100%">
            <thead>
                <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Owner</th>
                <th>$msgPermissions</th>
                <th>Action</th>
                </tr>
            </thead>
            <tbody>
END;

        foreach($this->user->DataSetList() as $dataSet)
        {
            $auth = $user->DataSetAuth($dataSet['datasetid'], true);
            $owner = $user->getNameFromID($dataSet['ownerid']);
            $groups = $this->GroupsForDataSet($dataSet['datasetid']);

            $output .= '<tr>';
            $output .= '    <td>' . $dataSet['dataset'] . '</td>';
            $output .= '    <td>' . $dataSet['description'] . '</td>';
            $output .= '    <td>' . $owner . '</td>';
            $output .= '    <td>' . $groups . '</td>';
            $output .= '    <td>';

            if ($auth->modifyPermissions)
                $output .= '<button class="XiboFormButton" href="index.php?p=dataset&q=PermissionsForm&datasetid=' . $dataSet['datasetid'] . '"><span>' . $msgPermissions . '</span></button>';

            $output .= '    </td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';
        $response->SetGridResponse($output);
        $response->Respond();
    }

    public function AddDataSetForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $helpManager = new HelpManager($db, $user);

        $msgName = __('Name');
        $msgDesc = __('Description');

        $form = <<<END
        <form id="AddDataSetForm" class="XiboForm" method="post" action="index.php?p=dataset&q=Add">
            <table>
                <tr>
                    <td><label for="dataset" accesskey="n">$msgName<span class="required">*</span></label></td>
                    <td><input name="dataset" class="required" type="text" id="dataset" tabindex="1" /></td>
                </tr>
                <tr>
                    <td><label for="description" accesskey="d">$msgDesc</label></td>
                    <td><input name="description" type="text" id="description" tabindex="2" /></td>
                </tr>
            </table>
        </form>
END;


        $response->SetFormRequestResponse($form, __('Add DataSet'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Add'), '$("#AddDataSetForm").submit()');
        $response->Respond();
    }

    public function Add()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $dataSet = Kit::GetParam('dataset', _POST, _STRING);
        $description = Kit::GetParam('description', _POST, _STRING);

        $dataSetObject = new DataSet($db);
        if (!$dataSetObject->Add($dataSet, $description, $this->user->userid))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);
            
        $response->SetFormSubmitResponse(__('DataSet Added'));
        $response->Respond();
    }

    /**
     * Get a list of group names for a layout
     * @param <type> $layoutId
     * @return <type>
     */
    private function GroupsForDataSet($dataSetId)
    {
        $db =& $this->db;

        $SQL = '';
        $SQL .= 'SELECT `group`.Group ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   INNER JOIN lkdatasetgroup ';
        $SQL .= '   ON `group`.GroupID = lkdatasetgroup.GroupID ';
        $SQL .= ' WHERE lkdatasetgroup.DataSetID = %d ';

        $SQL = sprintf($SQL, $dataSetId);

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get group information for dataset'), E_USER_ERROR);
        }

        $groups = '';

        while ($row = $db->get_assoc_row($results))
        {
            $groups .= $row['Group'] . ', ';
        }

        $groups = trim($groups);
        $groups = trim($groups, ',');

        return $groups;
    }

    public function PermissionsForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this dataset'), E_USER_ERROR);

        // Form content
        $form = '<form id="DataSetPermissionsForm" class="XiboForm" method="post" action="index.php?p=dataset&q=Permissions">';
	$form .= '<input type="hidden" name="datasetid" value="' . $dataSetId . '" />';
        $form .= '<div class="dialog_table">';
	$form .= '  <table style="width:100%">';
        $form .= '      <tr>';
        $form .= '          <th>' . __('Group') . '</th>';
        $form .= '          <th>' . __('View') . '</th>';
        $form .= '          <th>' . __('Edit') . '</th>';
        $form .= '          <th>' . __('Delete') . '</th>';
        $form .= '      </tr>';

        // List of all Groups with a view/edit/delete checkbox
        $SQL = '';
        $SQL .= 'SELECT `group`.GroupID, `group`.`Group`, View, Edit, Del, `group`.IsUserSpecific ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   LEFT OUTER JOIN lkdatasetgroup ';
        $SQL .= '   ON lkdatasetgroup.GroupID = group.GroupID ';
        $SQL .= '       AND lkdatasetgroup.DataSetID = %d ';
        $SQL .= ' WHERE `group`.GroupID <> %d ';
        $SQL .= 'ORDER BY `group`.IsEveryone DESC, `group`.IsUserSpecific, `group`.`Group` ';

        $SQL = sprintf($SQL, $dataSetId, $user->getGroupFromId($user->userid, true));

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get permissions for this dataset'), E_USER_ERROR);
        }

        while($row = $db->get_assoc_row($results))
        {
            $groupId = $row['GroupID'];
            $group = ($row['IsUserSpecific'] == 0) ? '<strong>' . $row['Group'] . '</strong>' : $row['Group'];

            $form .= '<tr>';
            $form .= ' <td>' . $group . '</td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_view" ' . (($row['View'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_edit" ' . (($row['Edit'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_del" ' . (($row['Del'] == 1) ? 'checked' : '') . '></td>';
            $form .= '</tr>';
        }

        $form .= '</table>';
        $form .= '</div>';
        $form .= '</form>';

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Layout', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DataSetPermissionsForm").submit()');
        $response->Respond();
    }

    public function Permissions()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        Kit::ClassLoader('datasetgroupsecurity');

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $groupIds = Kit::GetParam('groupids', _POST, _ARRAY);

        $auth = $this->user->DataSetAuth($dataSetId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this dataset'), E_USER_ERROR);

        // Unlink all
        $security = new DataSetGroupSecurity($db);
        if (!$security->UnlinkAll($dataSetId))
            trigger_error(__('Unable to set permissions'));

        // Some assignments for the loop
        $lastGroupId = 0;
        $first = true;
        $view = 0;
        $edit = 0;
        $del = 0;

        // List of groupIds with view, edit and del assignments
        foreach($groupIds as $groupPermission)
        {
            $groupPermission = explode('_', $groupPermission);
            $groupId = $groupPermission[0];

            if ($first)
            {
                // First time through
                $first = false;
                $lastGroupId = $groupId;
            }

            if ($groupId != $lastGroupId)
            {
                // The groupId has changed, so we need to write the current settings to the db.
                // Link new permissions
                if (!$security->Link($dataSetId, $groupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));

                // Reset
                $lastGroupId = $groupId;
                $view = 0;
                $edit = 0;
                $del = 0;
            }

            switch ($groupPermission[1])
            {
                case 'view':
                    $view = 1;
                    break;

                case 'edit':
                    $edit = 1;
                    break;

                case 'del':
                    $del = 1;
                    break;
            }
        }

        // Need to do the last one
        if (!$first)
        {
            if (!$security->Link($dataSetId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));
        $response->Respond();
    }
}
?>
