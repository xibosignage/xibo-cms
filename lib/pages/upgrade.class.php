<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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

class upgradeDAO extends baseDAO {

    public $errorMessage;

    public function displayPage() {

        if (DBVERSION == WEBSITE_VERSION) {
            Theme::Set('message', sprintf(__('Sorry you have arrived at this page in error, please try to navigate away.'), Theme::GetConfig('app_name')));

            Theme::Render('message_box');
            return;
        }

        if ($this->user->usertypeid != 1) {
            // Make sure we actually need to do an upgrade
            Theme::Set('message', sprintf(__('The CMS is temporarily off-line as an upgrade is in progress. Please check with your system administrator for updates or refresh your page in a few minutes.'), Theme::GetConfig('app_name')));

            Theme::Render('message_box');
            return;
        }
        else {
            // We want a static form (traditional rather than ajax)
            Theme::Set('form_class', 'StaticForm');

            // What step are we on
            $xibo_step = Kit::GetParam('step', _REQUEST, _INT, 1);

            $content = '';
            
            switch ($xibo_step) {

                case 1:
                    // Checks environment
                    $content = $this->Step1();
                    break;

                case 2:
                    // Collect upgrade details
                    $content = $this->Step2();
                    break;

                case 3:
                    // Execute upgrade
                    try {
                        $content = $this->Step3();
                    }
                    catch (Exception $e) {
                        $this->errorMessage = $e->getMessage();

                        // Reload step 2
                        $content = $this->Step2();
                    }
                    break;
            }

            Theme::Set('step', $xibo_step);
            Theme::Set('page_content', $content);
            Theme::Render('upgrade_page');
        }
    }

    public function Step1() {
        Theme::Set('form_action', 'index.php?p=upgrade');
        // Check environment
        $config = new Config();

        $environment = $config->CheckEnvironment();

        $formFields = array();
        $formButtons = array();
        $formFields[] = FormManager::AddMessage(sprintf(__('First we need to re-check if your server meets %s\'s requirements. The CMS requirements may change from release to release. If this is the case there will be further information in the release notes.'), Theme::GetConfig('app_name')));

        $formFields[] = FormManager::AddRaw($environment);

        if ($config->EnvironmentFault()) {
            $formFields[] = FormManager::AddHidden('step', 1);
            $formButtons[] = FormManager::AddButton(__('Retest'));
        }
        else if ($config->EnvironmentWarning()) {
            $formFields[] = FormManager::AddHidden('step', 2);
            $formButtons[] = FormManager::AddButton(__('Retest'), 'link', 'index.php?p=upgrade&step=1');
            $formButtons[] = FormManager::AddButton(__('Next'));
        }
        else {
            $formFields[] = FormManager::AddHidden('step', 2);
            $formButtons[] = FormManager::AddButton(__('Next'));
        }

        // Return a rendered form
        Theme::Set('form_fields', $formFields);
        Theme::Set('form_buttons', $formButtons);
        return Theme::RenderReturn('form_render');
    }

    public function Step2() {
        Kit::ClassLoader('install');

        // Work out what is involved in this upgrade
        $_SESSION['upgradeFrom'] = Config::Version('DBVersion');

        if ($_SESSION['upgradeFrom'] < 1) {
            $_SESSION['upgradeFrom'] = 1;
        }

        // Get a list of .sql and .php files for the upgrade
        $sql_files = Install::ls('*.sql','install/database', false, array('return_files'));
        $php_files = Install::ls('*.php','install/database', false, array('return_files'));
        
        // Sort by natural filename (eg 10 is bigger than 2)
        natcasesort($sql_files);
        natcasesort($php_files);

        $_SESSION['phpFiles'] = $php_files;
        $_SESSION['sqlFiles'] = $sql_files;

        $max_sql = Kit::ValidateParam(substr(end($sql_files),0,-4),_INT);
        $max_php = Kit::ValidateParam(substr(end($php_files),0,-4),_INT);
        $_SESSION['upgradeTo'] = max($max_sql, $max_php);

        if (!$_SESSION['upgradeTo'])
            throw new Exception(__('Unable to calculate the upgradeTo value. Check for non-numeric SQL and PHP files in the "install / database" directory.'));

        if ($_SESSION['upgradeTo'] < $_SESSION['upgradeFrom'])
            $_SESSION['upgradeTo'] = $_SESSION['upgradeFrom'];

        // Form to collect some information.
        $formFields = array();
        $formButtons = array();

        // Put up an error message if one has been set (and then unset it)
        if ($this->errorMessage != '') {
            Theme::Set('message', $this->errorMessage);
            Theme::Set('prepend', Theme::RenderReturn('message_box'));
            $this->errorMessage == '';
        }

        $formFields[] = FormManager::AddHidden('step', 3);
        $formFields[] = FormManager::AddHidden('upgradeFrom', $_SESSION['upgradeFrom']);
        $formFields[] = FormManager::AddHidden('upgradeTo', $_SESSION['upgradeTo']);
        $formFields[] = FormManager::AddHidden('includes', true);

        $formFields[] = FormManager::AddMessage(sprintf(__('Upgrading from database version %d to %d'), $_SESSION['upgradeFrom'], $_SESSION['upgradeTo']));

        // Loop for $i between upgradeFrom + 1 and upgradeTo.
        // If a php file exists for that upgrade, make an instance of it and call Questions so we can
        // Ask the user for input.
        for ($i = $_SESSION['upgradeFrom'] + 1; $i <= $_SESSION['upgradeTo']; $i++) {
            if (file_exists('install/database/' . $i . '.php')) {
                include_once('install/database/' . $i . '.php');
                $stepName = 'Step' . $i;
                
                // Check that a class called Step$i exists
                if (class_exists($stepName)) {
                    $_SESSION['Step' . $i] = new $stepName($this->db);
                    // Call Questions on the object and send the resulting hash to createQuestions routine
                    $questionFields = $this->createQuestions($i, $_SESSION['Step' . $i]->Questions());
                    $formFields = array_merge($formFields, $questionFields);
                }
                else {
                    $formFields[] = FormManager::AddMessage(sprintf(__('Warning: We included %s.php, but it did not include a class of appropriate name.'), $i));
                }
            }
        }

        $formFields[] = FormManager::AddCheckbox('doBackup', 'I agree I have a valid database backup and can restore it should the upgrade process fail', 0, __('It is important to take a database backup before running the upgrade wizard. A backup is essential for recovering your CMS should there be a problem with the upgrade.'), 'b');

        // Return a rendered form
        Theme::Set('form_action', 'index.php?p=upgrade');
        Theme::Set('form_fields', $formFields);
        Theme::Set('form_buttons', array(FormManager::AddButton(__('Next'))));
        return Theme::RenderReturn('form_render');
    }

    public function Step3() {
        Kit::ClassLoader('install');
        set_time_limit(0);
        $fault = false;
        $fault_string = '';

        foreach ($_POST as $key => $post) {
            // $key should be like 1-2, 1-3 etc
            // Split $key on - character.

            $parts = explode('-', $key);
            if (count($parts) == 2) {
                $step_num = 'Step' . $parts[0];
                include_once('install/database/' . $parts[0] . '.php');

                $response = $_SESSION[$step_num]->ValidateQuestion($parts[1], $post);
                if (! $response == true) {
                    // The upgrade routine for this step wasn't happy.
                    $fault = true;
                    $fault_string .= $response . "<br />\n";
                }
            }
        }

        if ($fault)
            throw new Exception($fault_string);

        $doBackup = Kit::GetParam('doBackup', $_POST, _CHECKBOX);

        if ($doBackup == 0)
            throw new Exception(__('You MUST have a valid database backup to continue. Please take and verify a backup and upgrade again.'));

        $sql_file = '';
        $sql = '';
        $i = 0;

        // Now loop over the entire upgrade. Run the SQLs and PHP interleaved.
        try {
            $dbh = PDOConnect::init();
            //$dbh->beginTransaction();

            for ($i = $_SESSION['upgradeFrom'] + 1; $i <= $_SESSION['upgradeTo']; $i++) {
                if (file_exists('install/database/' . $i . '.sql')) {

                    $delimiter = ';';
                    $sql_file = @file_get_contents('install/database/' . $i . '.sql');
                    $sql_file = Install::remove_remarks($sql_file);
                    $sql_file = Install::split_sql_file($sql_file, $delimiter);
                    
                    foreach ($sql_file as $sql) {
                        $dbh->exec($sql);
                    }
                }

                if (file_exists('install/database/' . $i . '.php')) {
                    $stepName = 'Step' . $i;
                    
                    if (!$_SESSION[$stepName]->Boot())
                        throw new Exception(__('Failed with %s', $stepName));
                }
            }

            //$dbh->commit();
        }
        catch (Exception $e) {
            //$dbh->rollBack();
            throw new Exception(sprintf(__('An error occurred running the upgrade. Please take a screen shot of this page and seek help. Statement number: %d. Error Message = [%s]. File = [%s]. SQL = [%s].'), $i, $e->getMessage(), $sql_file, $sql));
        }

        // Install files
        Media::installAllModuleFiles();

        // Delete install
        if (!unlink('install.php'))
            $formFields[] = FormManager::AddMessage(__("Unable to delete install.php. Please ensure the webserver has permission to unlink this file and retry"));
        
        $formFields[] = FormManager::AddMessage(__('The upgrade was a success!'));

        // Return a rendered form
        Theme::Set('form_fields', $formFields);
        return Theme::RenderReturn('form_render');
    }

    private function createQuestions($step, $questions) {
        // Takes a multi-dimensional array eg:
        // $q[0]['question'] = "May we collect anonymous usage statistics?";
        // $q[0]['type'] = _CHECKBOX;
        // $q[0]['default'] = true;
        $formFields = array();
        
        foreach ($questions as $qnum => $question) {
            
            $title = ($step < 80) ? __('Question %d of Step %s', $qnum + 1, $step) : $question['title'];

            if ($question['type'] == _INPUTBOX) {
                $formFields[] = FormManager::AddText($step . '-' . $qnum, $title, $question['default'],
                    $question['question'], 'q');
            }
            elseif ($question['type'] == _PASSWORD) {
                $formFields[] = FormManager::AddPassword($step . '-' . $qnum, $title, $question['default'],
                    $question['question'], 'q');
            }
            elseif ($question['type'] == _CHECKBOX) {
                $formFields[] = FormManager::AddCheckbox($step . '-' . $qnum, $title, (($question['default']) ? 1 : 0),
                    $question['question'], 'q');
            }
        }

        return $formFields;
    }
}
?>
