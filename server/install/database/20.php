<?php

class Step20 extends UpgradeStep
{

	public function Boot()
	{
		// TODO: Need to work out what includes the upgrade.php file contains. Will need Data classes.
		
		// TODO: Will need to write some PHP to add FromDT & TODT columns and to remove the starttime/endtime columns
		
		
		// TODO: Will need to add some upgrade PHP to create a DisplayGroup (+ link record) for every Currently existing display.
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

	public function Questions()
	{
		$this->q[0]['question'] = "Xibo will now try to convert your database to 1.1";
		$this->q[0]['type'] = _CHECKBOX;
		$this->q[0]['default'] = true;
		return $this->q;
	}

	public function ValidateQuestion($questionNumber,$response)
	{
		switch ($questionNumber) {
			case 0:
				$this->a[0] = Kit::ValidateParam($response, _BOOL);
				return true;
		}

		return false;
	}
}

?>
