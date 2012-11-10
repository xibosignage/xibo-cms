<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2012 Daniel Garner
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

class transitionDAO 
{
	private $db;
	private $user;
	private $transition;

    /**
     * Transition constructor.
     * @return
     * @param $db Object
     */
    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;

        return true;
    }
	
    /**
     * No display page functionaility
     * @return
     */
    function displayPage()
    {
        include('template/pages/transition_view.php');
        return false;
    }

    /**
     * No onload
     * @return
     */
    function on_page_load()
    {
        return '';
    }

    /**
     * No page heading
     * @return
     */
    function echo_page_heading()
    {
        return true;
    }

    public function Filter()
    {
        $filterForm = <<<END
        <div id="GroupFilter" class="FilterDiv">
                <form>
                        <input type="hidden" name="p" value="transition">
                        <input type="hidden" name="q" value="Grid">
                </form>
        </div>
END;
        $id = uniqid();
        $pager = ResponseManager::Pager($id);

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
                <div class="XiboFilter">
                        $filterForm
                </div>
                $pager
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

        $SQL = '';
        $SQL .= 'SELECT TransitionID, ';
        $SQL .= '   Transition, ';
        $SQL .= '   Code, ';
        $SQL .= '   HasDuration, ';
        $SQL .= '   HasDirection, ';
        $SQL .= '   AvailableAsIn, ';
        $SQL .= '   AvailableAsOut ';
        $SQL .= '  FROM `transition` ';
        $SQL .= ' ORDER BY Transition ';

        if (!$rows = $db->GetArray($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get the list of transitions'), E_USER_ERROR);
        }

        $output  = '<div class="info_table"><table style="width:100%">';
        $output .= '    <thead>';
        $output .= '    <tr>';
        $output .= '    <th>' . __('Name') .'</th>';
        $output .= '    <th>' . __('Has Duration') .'</th>';
        $output .= '    <th>' . __('Has Direction') .'</th>';
        $output .= '    <th>' . __('Enabled for In') .'</th>';
        $output .= '    <th>' . __('Enabled for Out') .'</th>';
        $output .= '    <th>' . __('Actions') .'</th>';
        $output .= '    </tr>';
        $output .= '    </thead>';
        $output .= '    <tbody>';

        foreach($rows as $transition)
        {
            $transitionId = Kit::ValidateParam($transition['TransitionID'], _INT);
            $name = Kit::ValidateParam($transition['Transition'], _STRING);
            $hasDuration = Kit::ValidateParam($transition['HasDuration'], _INT);
            $hasDirection = Kit::ValidateParam($transition['HasDirection'], _INT);
            $enabledForIn = Kit::ValidateParam($transition['AvailableAsIn'], _INT);
            $enabledForOut = Kit::ValidateParam($transition['AvailableAsOut'], _INT);

            $output .= '<tr>';
            $output .= '<td>' . $name . '</td>';
            $output .= '<td>' . (($hasDuration == 1) ? '<img src="img/act.gif" />' : '<img src="img/disact.gif" />') . '</td>';
            $output .= '<td>' . (($hasDirection == 1) ? '<img src="img/act.gif" />' : '<img src="img/disact.gif" />') . '</td>';
            $output .= '<td>' . (($enabledForIn == 1) ? '<img src="img/act.gif" />' : '<img src="img/disact.gif" />') . '</td>';
            $output .= '<td>' . (($enabledForOut == 1) ? '<img src="img/act.gif" />' : '<img src="img/disact.gif" />') . '</td>';
            $output .= '<td>' . ((Config::GetSetting($db, 'TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked') ? __('Transition Config Locked') : '<button class="XiboFormButton" href="index.php?p=transition&q=EditForm&TransitionID=' . $transitionId . '"><span>' . __('Edit') . '</span></button>') . '</td>';
            $output .= '</tr>';
        }

        $output .= "</tbody></table></div>";

        $response->SetGridResponse($output);
        $response->Respond();
    }

    public function EditForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        // Can we edit?
        if (Config::GetSetting($db, 'TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Transition Config Locked'), E_USER_ERROR);

        $transitionId = Kit::GetParam('TransitionID', _GET, _INT);

        // Pull the currently known info from the DB
        $SQL = '';
        $SQL .= 'SELECT Transition, ';
        $SQL .= '   AvailableAsIn, ';
        $SQL .= '   AvailableAsOut ';
        $SQL .= '  FROM `transition` ';
        $SQL .= ' WHERE TransitionID = %d ';

        $SQL = sprintf($SQL, $transitionId);

        if (!$row = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Transition'));
        }

        $name = Kit::ValidateParam($row['Transition'], _STRING);
        $enabledForIn = Kit::ValidateParam($row['AvailableAsIn'], _INT);
        $enabledForOut = Kit::ValidateParam($row['AvailableAsOut'], _INT);
        
        // Check boxes
        $enabledForInChecked = ($enabledForIn) ? 'checked' : '';
        $enabledForOutChecked = ($enabledForOut) ? 'checked' : '';
        
        // Messages
        $msgSave = __('Save');
        $msgCancel = __('Cancel');
        
        $msgEnabledForIn = __('Available for In Transitions?');
        $msgEnabledForOut = __('Available for Out Transitions?');
        
        $form = <<<END
        <form id="TransitionEditForm" class="XiboForm" action="index.php?p=transition&q=Edit" method="post">
            <input type="hidden" name="TransitionID" value="$transitionId" />
            <table>
                <tr>
                    <td>$msgEnabledForIn</span></td>
                    <td><input type="checkbox" name="EnabledForIn" $enabledForInChecked></td>
                </tr>
                <tr>
                    <td>$msgEnabledForOut</span></td>
                    <td><input type="checkbox" name="EnabledForOut" $enabledForOutChecked></td>
                </tr>
            </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Edit ') . $name, '350px', '325px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Transition', 'Edit') . '")');
        $response->AddButton($msgCancel, 'XiboDialogClose()');
        $response->AddButton($msgSave, '$("#TransitionEditForm").submit()');
        $response->Respond();
    }

    /**
     * Edit Transition
     */
    public function Edit()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        // Can we edit?
        if (Config::GetSetting($db, 'TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Transition Config Locked'), E_USER_ERROR);

        $transitionId = Kit::GetParam('TransitionID', _POST, _INT);
        $enabledForIn = Kit::GetParam('EnabledForIn', _POST, _CHECKBOX);
        $enabledForOut = Kit::GetParam('EnabledForOut', _POST, _CHECKBOX);

        // Validation
        if ($transitionId == 0 || $transitionId == '')
            trigger_error(__('Transition ID is missing'), E_USER_ERROR);

        // Deal with the Edit
        $SQL = "UPDATE `transition` SET AvailableAsIn = %d, AvailableAsOut = %d WHERE TransitionID = %d";
        $SQL = sprintf($SQL, $enabledForIn, $enabledForOut, $transitionId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to update transition'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Transition Edited'), false);
        $response->Respond();
    }
}
?>