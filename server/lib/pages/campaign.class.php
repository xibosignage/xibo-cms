<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2012 Daniel Garner
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

class campaignDAO
{
    private $db;
    private $user;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;
    }

    function on_page_load()
    {
        return "";
    }

    function echo_page_heading()
    {
        echo __("Campaign Administration");
        return true;
    }

    public function displayPage()
    {
        require("template/pages/campaign_view.php");

        return false;
    }

    /**
     * Shows the Filter form for display groups
     * @return
     */
    public function Filter()
    {
        $filterForm = <<<END
        <div class="FilterDiv" id="CampaignFilter">
            <form onsubmit="return false">
                    <input type="hidden" name="p" value="campaign">
                    <input type="hidden" name="q" value="Grid">
            </form>
        </div>
END;

        $id = uniqid();

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
            <div class="XiboFilter">
                    $filterForm
            </div>
            <div class="XiboData">

            </div>
        </div>
HTML;
        echo $xiboGrid;
    }

    public function Grid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $rows = $user->CampaignList();

        if (!is_array($rows))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get list of campaigns'), E_USER_ERROR);
        }

        $output  = '<div class="info_table"><table style="width:100%">';
        $output .= '    <thead>';
        $output .= '    <tr>';
        $output .= '    <th>' . __('Name') .'</th>';
        $output .= '    <th>' . __('# Layouts') .'</th>';
        $output .= '    <th>' . __('Actions') .'</th>';
        $output .= '    </tr>';
        $output .= '    </thead>';
        $output .= '    <tbody>';

        foreach($rows as $row)
        {
            if ($row['islayoutspecific'] == 1)
                continue;

            $campaignId = Kit::ValidateParam($row['campaignid'], _INT);
            $campaign = Kit::ValidateParam($row['campaign'], _STRING);
            $numLayouts = Kit::ValidateParam($row['numlayouts'], _INT);

            $buttons = '';
            $buttons .= '<button class="XiboFormButton" href="index.php?p=schedule&q=ScheduleNowForm&CampaignID=' . $campaignId . '"><span>' . __('Schedule Now') . '</span></button>';
            
            // Buttons based on permissions
            if ($row['edit'] == 1)
            {
                // Show the edit button, members button
                $buttons .= '<button class="XiboFormButton" href="index.php?p=campaign&q=MembersForm&CampaignID=' . $campaignId . '&Campaign=' . $campaign . '"><span>' . __('Layouts') . '</span></button>';
                $buttons .= '<button class="XiboFormButton" href="index.php?p=campaign&q=EditForm&CampaignID=' . $campaignId . '"><span>' . __('Edit') . '</span></button>';
            }

            if ($row['del'] == 1)
            {
                // Show the delete button
                $buttons .= '<button class="XiboFormButton" href="index.php?p=campaign&q=DeleteForm&CampaignID=' . $campaignId . '"><span>' . __('Delete') . '</span></button>';
            }

            if ($row['modifypermissions'] == 1)
            {
                // Show the modify permissions button
                $buttons .= '<button class="XiboFormButton" href="index.php?p=campaign&q=PermissionsForm&CampaignID=' . $campaignId . '"><span>' . __('Permissions') . '</span></button>';
            }

            $output .= '<tr>';
            $output .= '    <td>' . $campaign . '</td>';
            $output .= '    <td>' . $numLayouts . '</td>';
            $output .= '    <td>' . $buttons . '</td>';
            $output .= '</tr>';
        }

        $output .= "</tbody></table></div>";

        $response->SetGridResponse($output);
        $response->Respond();
    }

    /**
     * Campaign Add Form
     */
    public function AddForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        // Help UI
        $iconCampaignName = $helpManager->HelpIcon(__('The Name for this Campaign'), true);

        $msgSave = __('Save');
        $msgCancel = __('Cancel');
        $msgAction = __('Action');
        $msgEdit = __('Edit');
        $msgDelete = __('Delete');

        $msgCampaignName = __('Name');

        $form = <<<END
        <form id="CampaignAddForm" class="XiboForm" action="index.php?p=campaign&q=Add" method="post">
            <table>
                <tr>
                    <td>$msgCampaignName</span></td>
                    <td>$iconCampaignName <input class="required" type="text" name="Name" maxlength="254"></td>
                </tr>
            </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Add Campaign'), '350px', '325px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Add') . '")');
        $response->AddButton($msgCancel, 'XiboDialogClose()');
        $response->AddButton($msgSave, '$("#CampaignAddForm").submit()');
        $response->Respond();
    }

    /**
     * Add a Campaign
     */
    public function Add()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        $name = Kit::GetParam('Name', _POST, _STRING);

        // Validation
        if ($name == '')
            trigger_error(__('Name is a required field.'), E_USER_ERROR);

        Kit::ClassLoader('campaign');
        $campaignObject = new Campaign($db);

        if (!$campaignObject->Add($name, 0, $this->user->userid))
            trigger_error($campaignObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Campaign Added'), false);
        $response->Respond();
    }

    /**
     * Campaign Edit Form
     */
    public function EditForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $campaignId = Kit::GetParam('CampaignID', _GET, _INT);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this campaign'), E_USER_ERROR);

        // Pull the currently known info from the DB
        $SQL  = "SELECT CampaignID, Campaign, IsLayoutSpecific ";
        $SQL .= "  FROM `campaign` ";
        $SQL .= " WHERE CampaignID = %d ";

        $SQL = sprintf($SQL, $campaignId);

        if (!$row = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Campaign'));
        }

        $campaign = $campaign = Kit::ValidateParam($row['Campaign'], _STRING);
        $isLayoutSpecific = Kit::ValidateParam($row['IsLayoutSpecific'], _INT);

        // Help UI
        $iconCampaignName = $helpManager->HelpIcon(__('The Name for this Campaign'), true);

        $msgSave = __('Save');
        $msgCancel = __('Cancel');
        $msgAction = __('Action');
        $msgEdit = __('Edit');
        $msgDelete = __('Delete');

        $msgCampaignName = __('Name');

        $form = <<<END
        <form id="CampaignEditForm" class="XiboForm" action="index.php?p=campaign&q=Edit" method="post">
            <input type="hidden" name="CampaignID" value="$campaignId" />
            <table>
                <tr>
                    <td>$msgCampaignName</span></td>
                    <td>$iconCampaignName <input class="required" type="text" name="Name" value="$campaign" maxlength="254"></td>
                </tr>
            </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Edit Campaign'), '350px', '325px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Edit') . '")');
        $response->AddButton($msgCancel, 'XiboDialogClose()');
        $response->AddButton($msgSave, '$("#CampaignEditForm").submit()');
        $response->Respond();
    }

    /**
     * Edit a Campaign
     */
    public function Edit()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        $campaignId = Kit::GetParam('CampaignID', _POST, _INT);
        $name = Kit::GetParam('Name', _POST, _STRING);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this campaign'), E_USER_ERROR);

        // Validation
        if ($campaignId == 0 || $campaignId == '')
            trigger_error(__('Campaign ID is missing'), E_USER_ERROR);

        if ($name == '')
            trigger_error(__('Name is a required field.'), E_USER_ERROR);

        Kit::ClassLoader('campaign');
        $campaignObject = new Campaign($db);

        if (!$campaignObject->Edit($campaignId, $name))
            trigger_error($campaignObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Campaign Edited'), false);
        $response->Respond();
    }

    /**
     * Shows the Delete Group Form
     * @return
     */
    function DeleteForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $campaignId = Kit::GetParam('CampaignID', _GET, _INT);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to delete this campaign'), E_USER_ERROR);

        $msgWarn = __('Are you sure you want to delete?');

        //we can delete
        $form = <<<END
        <form id="CampaignDeleteForm" class="XiboForm" method="post" action="index.php?p=campaign&q=Delete">
            <input type="hidden" name="CampaignID" value="$campaignId" />
            <p>$msgWarn</p>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Delete Campaign'), '350px', '175px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#CampaignDeleteForm").submit()');
        $response->Respond();
    }

    /**
     * Delete Campaign
     */
    public function Delete()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        $campaignId = Kit::GetParam('CampaignID', _POST, _INT);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to delete this campaign'), E_USER_ERROR);

        // Validation
        if ($campaignId == 0 || $campaignId == '')
            trigger_error(__('Campaign ID is missing'), E_USER_ERROR);

        Kit::ClassLoader('campaign');
        $campaignObject = new Campaign($db);

        if (!$campaignObject->Delete($campaignId))
            trigger_error($campaignObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Campaign Deleted'), false);
        $response->Respond();
    }

    /**
     * Show the Permissions for this Campaign
     */
    public function PermissionsForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $campaignId = Kit::GetParam('CampaignID', _GET, _INT);

        $auth = $this->user->CampaignAuth($campaignId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this campaign'), E_USER_ERROR);

        // Form content
        $form = '<form id="CampaignPermissionsForm" class="XiboForm" method="post" action="index.php?p=campaign&q=Permissions">';
	$form .= '<input type="hidden" name="campaignId" value="' . $campaignId . '" />';
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
        $SQL .= '   LEFT OUTER JOIN lkcampaigngroup ';
        $SQL .= '   ON lkcampaigngroup.GroupID = group.GroupID ';
        $SQL .= '       AND lkcampaigngroup.CampaignID = %d ';
        $SQL .= 'ORDER BY `group`.IsEveryone DESC, `group`.IsUserSpecific, `group`.`Group` ';

        $SQL = sprintf($SQL, $campaignId);

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get permissions for this Campaign'), E_USER_ERROR);
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
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#CampaignPermissionsForm").submit()');
        $response->Respond();
    }

    public function Permissions()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $campaignId = Kit::GetParam('campaignId', _POST, _INT);
        $groupIds = Kit::GetParam('groupids', _POST, _ARRAY);

        $auth = $this->user->CampaignAuth($campaignId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this campaign'), E_USER_ERROR);

        // Unlink all
        Kit::ClassLoader('campaignsecurity');
        $security = new CampaignSecurity($db);
        if (!$security->UnlinkAll($campaignId))
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
                if (!$security->Link($campaignId, $lastGroupId, $view, $edit, $del))
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
            if (!$security->Link($campaignId, $lastGroupId, $view, $edit, $del))
                trigger_error(__('Unable to set permissions'));
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));
        $response->Respond();
    }

    /**
     * Show a form with all current members
     */
    public function MembersForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $campaignId = Kit::GetParam('CampaignID', _GET, _INT);

        // There needs to be two lists here.
        // One of which is the Layouts currently assigned to this group
        // The other is a list of layouts that are available to be assigned (i.e. the opposite of the first list)

        // Layouts in group
        $SQL  = "";
        $SQL .= "SELECT layout.LayoutID, ";
        $SQL .= "       layout.layout ";
        $SQL .= "FROM   layout ";
        $SQL .= "       INNER JOIN lkcampaignlayout ";
        $SQL .= "       ON     lkcampaignlayout.LayoutID = layout.LayoutID ";
        $SQL .= sprintf("WHERE  lkcampaignlayout.CampaignID   = %d", $campaignId);
        $SQL .= " ORDER BY lkcampaignlayout.DisplayOrder ";

        if (!$resultIn = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Layouts'), E_USER_ERROR);
        }

        // Layouts not in group
        $SQL  = "";
        $SQL .= "SELECT layout.LayoutID, ";
        $SQL .= "       layout.layout ";
        $SQL .= "FROM   layout ";
        $SQL .= " WHERE layout.LayoutID NOT       IN ";
        $SQL .= "       (SELECT layout.LayoutID ";
        $SQL .= "       FROM    layout ";
        $SQL .= "               INNER JOIN lkcampaignlayout ";
        $SQL .= "               ON      lkcampaignlayout.LayoutID = layout.LayoutID ";
        $SQL .= sprintf("	WHERE  lkcampaignlayout.CampaignID   = %d", $campaignId);
        $SQL .= "       )";
        $SQL .= " ORDER BY layout.layout ";

        if (!$resultOut = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Layouts'), E_USER_ERROR);
        }

        // Now we have an IN and an OUT results object which we can use to build our lists
        $listIn = '<ul id="layoutsIn" href="index.php?p=campaign&q=SetMembers&CampaignID=' . $campaignId . '" class="connectedSortable">';

        while($row = $db->get_assoc_row($resultIn))
        {
                // For each item output a LI
                $layoutId = Kit::ValidateParam($row['LayoutID'], _INT);
                $layout = Kit::ValidateParam($row['layout'], _STRING);

                $listIn	.= '<li id="LayoutID_' . $layoutId . '"class="li-sortable">' . $layout . '</li>';
        }
        $listIn	.= '</ul>';

        $listOut = '<ul id="layoutsOut" class="connectedSortable">';

        while($row = $db->get_assoc_row($resultOut))
        {
            // For each item output a LI
            $layoutId = Kit::ValidateParam($row['LayoutID'], _INT);
            $layout = Kit::ValidateParam($row['layout'], _STRING);

            // Authenticate
            $auth = $this->user->LayoutAuth($layoutId, true);
            if (!$auth->view)
                continue;

            $listOut .= '<li id="LayoutID_' . $layoutId . '" class="li-sortable">' . $layout . '</li>';
        }
        $listOut .= '</ul>';

        // Build the final form.
        $helpText = '<center>' . __('Drag or double click to move items between lists') . '</center>';
        $form = $helpText . '<div class="connectedlist"><h3>' . __('Assigned Layouts') . '</h3>' . $listIn . '</div><div class="connectedlist"><h3>' . __('Available Layouts') . '</h3>' . $listOut . '</div>';

        $response->SetFormRequestResponse($form, __('Layouts on Campaign'), '400', '375', 'ManageMembersCallBack');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Layouts') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), 'MembersSubmit()');
        $response->Respond();
    }

    /**
     * Sets the Members of a group
     * @return
     */
    public function SetMembers()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        Kit::ClassLoader('campaign');
        $campaignObject = new Campaign($db);

        $campaignId = Kit::GetParam('CampaignID', _REQUEST, _INT);
        $layouts = Kit::GetParam('LayoutID', _POST, _ARRAY, array());

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this campaign'), E_USER_ERROR);

        // Remove all current members
        $campaignObject->UnlinkAll($campaignId);

        // Add all new members
        $displayOrder = 1;

        foreach($layouts as $layoutId)
        {
            // Authenticate
            $auth = $this->user->LayoutAuth($layoutId, true);
            if (!$auth->view)
                trigger_error(__('Your permissions to view a layout you are adding have been revoked. Please reload the Layouts form.'), E_USER_ERROR);

            $campaignObject->Link($campaignId, $layoutId, $displayOrder);

            $displayOrder++;
        }

        $response->SetFormSubmitResponse(__('Layouts Added to Campaign'), false);
        $response->Respond();
    }
}
?>
