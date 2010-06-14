<?php

class Step23 extends UpgradeStep
{

	public function Boot()
	{
		$SQL = "UPDATE `setting` SET `value` = '%d' WHERE `setting`.`setting` = 'MAINTENANCE_ENABLED' LIMIT 1";
        $SQL = sprintf($SQL, $this->db->escape_string($this->a[0]));
		$this->db->query($SQL);

        $SQL = "UPDATE `setting` SET `value` = '%d' WHERE `setting`.`setting` = 'MAINTENANCE_LOG_MAXAGE' LIMIT 1";
        $SQL = sprintf($SQL, $this->db->escape_string($this->a[1]));
		$this->db->query($SQL);

        $SQL = "UPDATE `setting` SET `value` = '%d' WHERE `setting`.`setting` = 'MAINTENANCE_STAT_MAXAGE' LIMIT 1";
        $SQL = sprintf($SQL, $this->db->escape_string($this->a[2]));
		$this->db->query($SQL);

        $SQL = "UPDATE `setting` SET `value` = '%d' WHERE `setting`.`setting` = 'MAINTENANCE_EMAIL_ALERTS' LIMIT 1";
        $SQL = sprintf($SQL, $this->db->escape_string($this->a[3]));
		$this->db->query($SQL);

        $SQL = "UPDATE `setting` SET `value` = '%s' WHERE `setting`.`setting` = 'mail_to' LIMIT 1";
        $SQL = sprintf($SQL, $this->db->escape_string($this->a[4]));
		$this->db->query($SQL);

        $SQL = "UPDATE `setting` SET `value` = '%s' WHERE `setting`.`setting` = 'mail_from' LIMIT 1";
        $SQL = sprintf($SQL, $this->db->escape_string($this->a[5]));
		$this->db->query($SQL);

        $SQL = "UPDATE `setting` SET `value` = '%d' WHERE `setting`.`setting` = 'MAINTENANCE_ALERT_TOUT' LIMIT 1";
        $SQL = sprintf($SQL, $this->db->escape_string($this->a[6]));
		$this->db->query($SQL);

		return true;
	}

	public function Questions()
	{
        // TODO: Fix the "more info" URL to a page in the wiki
		$this->q[0]['question'] = "Allow the periodic maintenance and alerts script to run if called. See <a href=\"http://wiki.xibo.org.uk/wiki/Manual:Admin:Settings_Help#Maintenance\">here</a> for more information.";
		$this->q[0]['type'] = _CHECKBOX;
		$this->q[0]['default'] = true;
        $this->q[1]['question'] = "How long in days should we keep log data for? 0 keeps logs indefinitely. (Requires maintenance script to be run to work)";
		$this->q[1]['type'] = _INPUTBOX;
		$this->q[1]['default'] = "30";
        $this->q[2]['question'] = "How long in days should we keep statistics for? 0 keeps statistics indefinitely. (Requires maintenance script to be run to work)";
		$this->q[2]['type'] = _INPUTBOX;
		$this->q[2]['default'] = "30";
        $this->q[3]['question'] = "Should the maintenance and alerts script notify you by email of problems?";
		$this->q[3]['type'] = _CHECKBOX;
		$this->q[3]['default'] = true;
        $this->q[4]['question'] = "Email address to send alerts to";
		$this->q[4]['type'] = _INPUTBOX;
		$this->q[4]['default'] = "admin@yourdomain.com";
        $this->q[5]['question'] = "Email address alerts should be sent from";
		$this->q[5]['type'] = _INPUTBOX;
		$this->q[5]['default'] = "xibo@yourdomain.com";
        $this->q[6]['question'] = "How long in minutes after the last time a client connects should we send an alert? (Can be overridden on each display)";
		$this->q[6]['type'] = _INPUTBOX;
		$this->q[6]['default'] = "12";
		return $this->q;
	}

	public function ValidateQuestion($questionNumber,$response)
	{
		switch ($questionNumber) {
			case 0:
                if (Kit::ValidateParam($response, _BOOL)) {
                    $this->a[0] = "Protected";
                }
                else {
                    $this->a[0] = "Off";
                }
				return true;
            case 1:
                $this->a[1] = Kit::ValidateParam($response, _INT, 30);
                return true;
            case 2:
                $this->a[2] = Kit::ValidateParam($response, _INT, 30);
                return true;
            case 3:
                $this->a[3] = Kit::ValidateParam($response, _BOOL);
                return true;
            case 4:
                // TODO: Teach Kit how to validate email addresses?
                $this->a[4] = Kit::ValidateParam($response, _PASSWORD);
                return true;
            case 5:
                // TODO: Teach Kit how to validate email addresses?
                $this->a[5] = Kit::ValidateParam($response, _PASSWORD);
                return true;
            case 6:
                $this->a[6] = Kit::ValidateParam($response, _INT, 12);
                return true;
		}

		return false;
	}
}

?>
