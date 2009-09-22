<?php

class Step20 extends UpgradeStep
{

	public function Boot()
	{
		// Will need to include the Data classes.
		require_once("lib/data/data.class.php");
		require_once('lib/data/displaygroup.data.class.php');
		
		
		// Will need to add some upgrade PHP to create a DisplayGroup (+ link record) for every Currently existing display.
		$dg = new DisplayGroup($db);

		// Get all displays
		$SQL = "SELECT DisplayID, Display FROM display";
		
		if (!$result = $db->query($SQL))
		{
			trigger_error("Error creating display groups", E_USER_ERROR);
		}
		
		while ($row = $db->get_assoc_row($result))
		{
			// For each display create a display group and link it to the display
			$displayID		= Kit::ValidateParam($row['DisplayID'], _INT);
			$display		= Kit::ValidateParam($row['Display'], _STRING);
			
			$displayGroupID	= $dg->Add($display, 1);
			
			$dg->Link($displayGroupID, $displayID);
		}
		
		return true;
	}
}
?>