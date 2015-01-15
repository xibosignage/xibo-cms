<?php

// Will need to include the Data classes.
require_once("lib/data/data.class.php");
require_once('lib/data/displaygroup.data.class.php');
require_once('lib/data/usergroup.data.class.php');

class Step20 extends UpgradeStep
{
	public function Boot()
	{
		$db = &$this->db;

		// Will need to add some upgrade PHP to create a DisplayGroup (+ link record) for every Currently existing display.
		$dg = new DisplayGroup($db);

		// Get all displays
		$SQL = "SELECT DisplayID, Display FROM display";
		
		if (!$result = $db->query($SQL))
		{
			reportError('20.php', "Error creating display groups");
		}
		
		while ($row = $db->get_assoc_row($result))
		{
                    // For each display create a display group and link it to the display
                    $displayID		= Kit::ValidateParam($row['DisplayID'], _INT);
                    $display		= Kit::ValidateParam($row['Display'], _STRING);

                    $displayGroupID	= $dg->Add($display, 1);

                    $dg->Link($displayGroupID, $displayID);
		}

                // We also need to do a number on the schedule records
                // Each schedule record needs to be altered so that the displayID_list now reflects the displayGroupIDs
                $this->UpdateSchedules();

                // Create groups for all current users
                $this->UpdateUserGroups();

		return true;
	}

        /**
         * Updates all schedule records with the correct display group id
         */
        private function UpdateSchedules()
        {
            $db =& $this->db;

            // Get all schedules
            $SQL = "SELECT EventID, DisplayGroupIDs FROM schedule WHERE DisplayGroupIDs <> ''";

            if (!$result = $db->query($SQL))
            {
                reportError('20.php', "Error getting Schedules" . $db->error());
            }

            while ($row = $db->get_assoc_row($result))
            {
                // For each display create a display group and link it to the display
                $eventID		= Kit::ValidateParam($row['EventID'], _INT);
                $displayGroupIDs	= Kit::ValidateParam($row['DisplayGroupIDs'], _STRING);

                // For the display ids in the list make us up a comma seperated list of display groups
                $SQL = "SELECT displaygroup.DisplayGroupID FROM displaygroup ";
                $SQL .= " INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
                $SQL .= sprintf("WHERE lkdisplaydg.DisplayID IN (%s)", $displayGroupIDs);
                $SQL .= " AND IsDisplaySpecific = 1";

                if (!$dgResult = $db->query($SQL))
                {
                    reportError('20.php', "Error getting Display Groups" . $db->error());
                }

                $displayGroupIDs = array();

                while ($row = $db->get_assoc_row($dgResult))
                {
                    $displayGroupIDs[] = Kit::ValidateParam($row['DisplayGroupID'], _INT);
                }

                $displayGroupIDs = implode(',', $displayGroupIDs);

                // Update the schedule with the new IDs
                $SQL = "UPDATE schedule SET DisplayGroupIDs = '%s' WHERE EventID = %d";
                $SQL = sprintf($SQL, $displayGroupIDs, $eventID);

                if (!$db->query($SQL))
                {
                    reportError('20.php', "Error updating schedules." . $db->error());
                }
            }

            // Get all schedule details
            $SQL = "SELECT Schedule_DetailID, DisplayGroupID FROM schedule_detail";

            if (!$result = $db->query($SQL))
            {
                reportError('20.php', "Error getting Schedule Details" . $db->error());
            }

            while ($row = $db->get_assoc_row($result))
            {
                // For each display create a display group and link it to the display
                $eventID		= Kit::ValidateParam($row['Schedule_DetailID'], _INT);
                $displayGroupID 	= Kit::ValidateParam($row['DisplayGroupID'], _INT);

                // For the display ids in the list make us up a comma seperated list of display groups
                $SQL = "SELECT displaygroup.DisplayGroupID FROM displaygroup ";
                $SQL .= " INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
                $SQL .= sprintf("WHERE lkdisplaydg.DisplayID = %d", $displayGroupID);
                $SQL .= " AND IsDisplaySpecific = 1";

                if (!$dgResult = $db->query($SQL))
                {
                    reportError('20.php', "Error getting Display Groups. " . $db->error());
                }

                $row = $db->get_assoc_row($dgResult);

                $displayGroupID = Kit::ValidateParam($row['DisplayGroupID'], _INT);

                // Update the schedule with the new IDs
                $SQL = "UPDATE schedule_detail SET DisplayGroupID = %d WHERE schedule_detailID = %d";
                $SQL = sprintf($SQL, $displayGroupID, $eventID);

                if (!$db->query($SQL))
                {
                    reportError('20.php', "Error updating schedule_detail." . $db->error());
                }
            }
        }

        /**
         * We need to update the user groups
         */
        private function UpdateUserGroups()
        {
            $db =& $this->db;

            // Get all the current users in the system
            $SQL = "SELECT UserID, groupID, UserName FROM `user`";

            if (!$result = $db->query($SQL))
            {
                reportError('20.php', "Error creating user groups" . $db->error());
            }

            while ($row = $db->get_assoc_row($result))
            {
                // For each display create a display group and link it to the display
                $ugid           = 0;
                $userID		= Kit::ValidateParam($row['UserID'], _INT);
                $groupID	= Kit::ValidateParam($row['groupID'], _INT);
                $username  	= Kit::ValidateParam($row['UserName'], _STRING);

                $ug = new UserGroup($db);

                // For each one create a user specific group
                if (!$ugId = $ug->Add($username, 1))
                {
                    reportError('20.php', "Error creating user groups" . $db->error());
                }

                // Link to the users own userspecific group and also to the one they were already on
                $ug->Link($ugId, $userID);

                $ug->Link($groupID, $userID);
            }
        }
}
?>