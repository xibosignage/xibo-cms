<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
namespace Xibo\Controller;
use baseDAO;
use Maintenance;
use Setting;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Config;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;


class Settings extends Base
{
    function displayPage()
    {
        // Get all of the settings in an array
        $settings = Config::GetAll(NULL, array('userSee' => 1));

        $currentCategory = '';
        $categories = array();
        $formFields = array();

        // Go through each setting, validate it and add it to the array
        foreach ($settings as $setting) {

            if ($currentCategory != $setting['cat']) {
                $currentCategory = $setting['cat'];
                $categories[] = array('tabId' => $setting['cat'], 'tabName' => ucfirst($setting['cat']));
            }

            // Are there any options
            $options = NULL;
            if (!empty($setting['options'])) {
                // Change to an id=>value array
                foreach (explode('|', $setting['options']) as $tempOption)
                    $options[] = array('id' => $tempOption, 'value' => $tempOption);
            }

            // Validate the current setting
            if ($setting['type'] == 'checkbox' && isset($setting['value']))
                $validated = $setting['value'];
            else if (isset($setting['value']))
                $validated = $setting['value'];
            else
                $validated = $setting['default'];

            // Time zone type requires special handling.
            if ($setting['fieldType'] == 'timezone') {
                $options = $this->TimeZoneDropDown($validated);
            }

            // Get a list of settings and assign them to the settings field
            $formFields[] = array(
                'name' => $setting['setting'],
                'type' => $setting['type'],
                'fieldType' => $setting['fieldType'],
                'helpText' => $setting['helptext'],
                'title' => $setting['title'],
                'options' => $options,
                'validation' => $setting['validation'],
                'value' => $validated,
                'enabled' => $setting['userChange'],
                'catId' => $setting['cat'],
                'cat' => ucfirst($setting['cat'])
            );
        }

        $data = [
            'categories' => $categories,
            'fields' => $formFields
        ];

        // Render the Theme and output
        $this->getState()->template = 'settings-page';
        $this->getState()->setData($data);
    }

    function Edit()
    {
        $response = $this->getState();


        $data = new Setting();

        // Get all of the settings in an array
        $settings = Config::GetAll(NULL, array('userChange' => 1, 'userSee' => 1));

        // Go through each setting, validate it and add it to the array
        foreach ($settings as $setting) {
            // Check to see if we have a setting that matches in the provided POST vars.
            $value = \Kit::GetParam($setting['setting'], _POST, $setting['type'], (($setting['type'] == 'checkbox') ? NULL : $setting['default']));

            // Check the library location setting
            if ($setting['setting'] == 'LIBRARY_LOCATION') {
                // Check for a trailing slash and add it if its not there
                $value = rtrim($value, '/');
                $value = rtrim($value, '\\') . DIRECTORY_SEPARATOR;

                // Attempt to add the directory specified
                if (!file_exists($value . 'temp'))
                    // Make the directory with broad permissions recursively (so will add the whole path)
                    mkdir($value . 'temp', 0777, true);

                if (!is_writable($value . 'temp'))
                    trigger_error(__('The Library Location you have picked is not writeable'), E_USER_ERROR);
            }

            // Actually edit
            if (!$data->Edit($setting['setting'], $value))
                trigger_error($data->GetErrorMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Settings Updated'), false);
        $response->callBack = 'settingsUpdated';

    }

    /**
     * Timezone functionality
     * @return
     */
    private function TimeZoneIdentifiersList()
    {

        if (function_exists('timezone_identifiers_list'))
            return timezone_identifiers_list();

        $list[] = 'Europe/London';
        $list[] = 'America/New_York';
        $list[] = 'Europe/Paris';
        $list[] = 'America/Los_Angeles';
        $list[] = 'America/Puerto_Rico';
        $list[] = 'Europe/Moscow';
        $list[] = 'Europe/Helsinki';
        $list[] = 'Europe/Warsaw';
        $list[] = 'Asia/Singapore';
        $list[] = 'Asia/Dubai';
        $list[] = 'Asia/Baghdad';
        $list[] = 'Asia/Shanghai';
        $list[] = 'Indian/Mauritius';
        $list[] = 'Australia/Melbourne';
        $list[] = 'Australia/Sydney';
        $list[] = 'Arctic/Longyearbyen';
        $list[] = 'Antarctica/South_Pole';

        return $list;
    }

    public function TimeZoneDropDown($selectedzone)
    {
        $structure = '';
        $i = 0;

        // Create a Zone array containing the timezones
        // From: http://php.oregonstate.edu/manual/en/function.timezone-identifiers-list.php
        foreach ($this->TimeZoneIdentifiersList() as $zone) {
            $zone = explode('/', $zone);
            $zonen[$i]['continent'] = isset($zone[0]) ? $zone[0] : '';
            $zonen[$i]['city'] = isset($zone[1]) ? $zone[1] : '';
            $zonen[$i]['subcity'] = isset($zone[2]) ? $zone[2] : '';
            $i++;
        }

        // Sort them
        asort($zonen);

        foreach ($zonen as $zone) {
            extract($zone);

            if ($continent == 'Africa' || $continent == 'America' || $continent == 'Antarctica' || $continent == 'Arctic' || $continent == 'Asia' || $continent == 'Atlantic' || $continent == 'Australia' || $continent == 'Europe' || $continent == 'Indian' || $continent == 'Pacific' || $continent == 'General') {
                if (!isset($selectcontinent)) {
                    $structure .= '<optgroup label="' . $continent . '">'; // continent
                } elseif ($selectcontinent != $continent) {
                    $structure .= '</optgroup><optgroup label="' . $continent . '">'; // continent
                }

                if (isset($city) != '') {
                    if (!empty($subcity) != '') {
                        $city = $city . '/' . $subcity;
                    }
                    $structure .= "<option " . ((($continent . '/' . $city) == $selectedzone) ? 'selected="selected "' : '') . " value=\"" . ($continent . '/' . $city) . "\">" . str_replace('_', ' ', $city) . "</option>"; //Timezone
                } else {
                    if (!empty($subcity) != '') {
                        $city = $city . '/' . $subcity;
                    }
                    $structure .= "<option " . (($continent == $selectedzone) ? 'selected="selected "' : '') . " value=\"" . $continent . "\">" . $continent . "</option>"; //Timezone
                }

                $selectcontinent = $continent;
            }
        }
        $structure .= '</optgroup>';

        return $structure;
    }

    /**
     * Sets all debugging to maximum
     * @return
     */
    public function SetMaxDebug()
    {
        $response = new ApplicationState();
        $setting = new Setting();

        if (!$setting->Edit('audit', 'audit'))
            trigger_error(__('Cannot set audit to On'));

        $response->SetFormSubmitResponse(__('Debugging switched On.'));

    }

    /**
     * Turns off all debugging
     * @return
     */
    public function SetMinDebug()
    {
        $response = new ApplicationState();
        $setting = new Setting();

        if (!$setting->Edit('audit', 'error'))
            trigger_error(__('Cannot set audit to Off'), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Debugging switched Off.'));

    }

    /**
     * Puts the Server in Production Mode
     * @return
     */
    public function SetServerProductionMode()
    {
        $response = new ApplicationState();
        $setting = new Setting();

        if (!$setting->Edit('SERVER_MODE', 'Production')) {
            trigger_error(__('Cannot switch modes.'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Server switched to Production Mode'));

    }

    /**
     * Puts the Server in Test Mode
     * @return
     */
    public function SetServerTestMode()
    {
        $response = new ApplicationState();
        $setting = new Setting();

        if (!$setting->Edit('SERVER_MODE', 'Test')) {
            trigger_error(__('Cannot switch modes.'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Server switched to Test Mode'));

    }

    public function SendEmail()
    {

        $response = new ApplicationState();
        $mail_to = \Kit::ValidateParam(Config::GetSetting("mail_to"), _PASSWORD);
        $mail_from = \Kit::ValidateParam(Config::GetSetting("mail_from"), _PASSWORD);
        $subject = __('Email Test');
        $body = __('Test email sent');
        $headers = sprintf("From: %s", $mail_from);

        $output = sprintf(__('Sending test email to %s.'), $mail_to);
        $output .= "<br/><br/>";

        if (mail($mail_to, $subject, $body, $headers)) {
            $output .= __("Mail sent OK");
        } else {
            $output .= __("Mail sending FAILED");
        }

        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->SetFormRequestResponse($output, __('Email Test'), '280px', '140px');

    }

    /**
     * Backup Form
     */
    public function BackupForm()
    {
        $response = $this->getState();

        // Check we have permission to do this
        if ($this->getUser()->userTypeId != 1)
            trigger_error(__('Only an adminitrator can export a database'));

        $form = '';
        $form .= '<p>' . __('This will create a dump file of your database that you can restore later using the import functionality.') . '</p>';
        $form .= '<p>' . __('You should also manually take a backup of your library.') . '</p>';
        $form .= '<p>' . __('Please note: The folder location for mysqldump must be available in your path environment variable for this to work and the php "exec" command must be enabled.') . '</p>';
        $form .= '<a href="index.php?p=admin&q=BackupDatabase" title="' . __('Export Database. Right click to save as.') . '">' . __('Click here to Export') . '</a>';

        $response->SetFormRequestResponse($form, __('Export Database Backup'), '550px', '275px');
        $response->AddButton(__('Close'), 'XiboDialogClose()');

    }

    /**
     * Backup Data and Return a file
     */
    public function BackupDatabase()
    {
        // We want to output a load of stuff to the browser as a text file.
        $maintenance = new Maintenance($this->db);

        if (!$dump = $maintenance->BackupDatabase())
            trigger_error($maintenance->GetErrorMessage(), E_USER_ERROR);
    }

    /**
     * Show an upload form to restore a database dump file
     */
    public function RestoreForm()
    {
        $response = $this->getState();

        if (Config::GetSetting('SETTING_IMPORT_ENABLED') != 1)
            trigger_error(__('Sorry this function is disabled.'), E_USER_ERROR);

        // Check we have permission to do this
        if ($this->getUser()->userTypeId != 1)
            trigger_error(__('Only an adminitrator can import a database'));

        $msgDumpFile = __('Backup File');
        $msgWarn = __('Warning: Importing a file here will overwrite your existing database. This action cannot be reversed.');
        $msgMore = __('Select a file to import and then click the import button below. You will be taken to another page where the file will be imported.');
        $msgInfo = __('Please note: The folder location for mysqldump must be available in your path environment variable for this to work and the php "exec" command must be enabled.');

        $form = <<<FORM
        <p>$msgWarn</p>
        <p>$msgInfo</p>
        <form id="file_upload" method="post" action="index.php?p=admin&q=RestoreDatabase" enctype="multipart/form-data">
            <table>
                <tr>
                    <td><label for="file">$msgDumpFile<span class="required">*</span></label></td>
                    <td>
                        <input type="file" name="dumpFile" />
                    </td>
                </tr>
            </table>
        </form>
        <p>$msgMore</p>
FORM;
        $response->SetFormRequestResponse($form, __('Import Database Backup'), '550px', '375px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Import'), '$("#file_upload").submit()');

    }

    /**
     * Restore the Database
     */
    public function RestoreDatabase()
    {


        if (Config::GetSetting('SETTING_IMPORT_ENABLED') != 1)
            trigger_error(__('Sorry this function is disabled.'), E_USER_ERROR);

        include('install/header.inc');
        echo '<div class="info">';

        // Expect a file upload
        // Check we got a valid file
        if (isset($_FILES['dumpFile']) && is_uploaded_file($_FILES['dumpFile']['tmp_name']) && $_FILES['dumpFile']['error'] == 0) {
            echo 'Restoring Database</br>';
            Log::notice('Valid Upload', 'Backup', 'RestoreDatabase');

            // Directory location
            $fileName = \Xibo\Helper\Sanitize::string($_FILES['dumpFile']['tmp_name']);

            if (is_uploaded_file($fileName)) {
                // Move the uploaded file to a temporary location in the library
                $destination = tempnam(Config::GetSetting('LIBRARY_LOCATION'), 'dmp');
                move_uploaded_file($fileName, $destination);


                $maintenance = new Maintenance($this->db);

                // Use the maintenance class to restore the database
                if (!$maintenance->RestoreDatabase($destination))
                    trigger_error($maintenance->GetErrorMessage(), E_USER_ERROR);

                unlink($destination);
            } else
                trigger_error(__('Not a valid uploaded file'), E_USER_ERROR);
        } else {
            trigger_error(__('Unable to upload file'), E_USER_ERROR);
        }

        echo '</div>';
        echo '<a href="../../web/index.php?p=admin">' . __('Database Restored. Click here to continue.') . '</a>';

        include('install/footer.inc');

        die();
    }

    /**
     * Friendly format for file size
     * @param <type> $fileSize
     * @return <type>
     */
    private function FormatByteSize($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function TidyLibraryForm()
    {
        $response = $this->getState();

        Theme::Set('form_id', 'TidyLibraryForm');
        Theme::Set('form_action', 'index.php?p=admin&q=TidyLibrary');

        $formFields = array();
        $formFields[] = Form::AddMessage(__('Tidying the Library will delete any temporary files. Are you sure you want to proceed?'));

        // Check box to also delete un-used media that has been revised.
        $formFields[] = Form::AddCheckbox('tidyOldRevisions', __('Remove old revisions'), 0,
            __('Cleaning up old revisions of media will result in any unused media revisions being permanently deleted.'), '');

        // Check box to tidy up un-used files
        $formFields[] = Form::AddCheckbox('cleanUnusedFiles', __('Remove all media not currently in use?'), 0,
            __('Selecting this option will remove any media that is not currently being used in Layouts or linked to Displays. This process cannot be reversed.'), '');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Tidy Library'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Settings', 'TidyLibrary') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#TidyLibraryForm").submit()');

    }

    /**
     * Tidies up the library
     */
    public function TidyLibrary()
    {
        $response = $this->getState();
        $tidyOldRevisions = (\Kit::GetParam('tidyOldRevisions', _POST, _CHECKBOX) == 1);
        $cleanUnusedFiles = (\Kit::GetParam('cleanUnusedFiles', _POST, _CHECKBOX) == 1);

        if (Config::GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            trigger_error(__('Sorry this function is disabled.'), E_USER_ERROR);

        $maintenance = new Maintenance();
        if (!$maintenance->TidyLibrary($tidyOldRevisions, $cleanUnusedFiles))
            trigger_error($maintenance->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Library Tidy Complete'));

    }
}

?>
