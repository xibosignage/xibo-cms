<?php

class Step2 extends UpgradeStep
{

	public function Boot()
	{
		$SQL = "UPDATE `setting` SET `value` = '" . md5(uniqid(rand(), true)) . "' WHERE `setting`.`setting` = 'PHONE_HOME_KEY' LIMIT 1";
		$this->db->query($SQL);

		if (! $this->a[0]) {
			$SQL = "UPDATE `setting` SET `value` = 'Off' WHERE `setting`.`setting` = 'PHONE_HOME' LIMIT 1" ;
			$this->db->query($SQL);
		}

		return true;
	}

	public function Questions()
	{
		$this->q[0]['question'] = "Please tick the box if we may collect anonymous usage statistics?";
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
