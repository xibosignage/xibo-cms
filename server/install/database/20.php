<?php

class Step20 extends UpgradeStep
{

	public function Boot()
	{
		// TODO: Need to work out what includes the upgrade.php file contains. Will need Data classes.
		
		// TODO: Will need to write some PHP to add FromDT & TODT columns and to remove the starttime/endtime columns
		
		
		// TODO: Will need to add some upgrade PHP to create a DisplayGroup (+ link record) for every Currently existing display.
			
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
