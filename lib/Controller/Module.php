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
namespace Xibo\Controller;

use Xibo\Factory\ModuleFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Config;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;
use Xibo\Storage\PDOConnect;


class Module extends Base
{
    /**
     * Display the module page
     */
    function displayPage()
    {
        $data = [];

        // Do we have any modules to install?!
        if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') != 'Checked') {
            // Get a list of matching files in the modules folder
            $files = glob('modules/*.module.php');

            $installed = [];

            // Get a list of all currently installed modules
            try {
                $dbh = PDOConnect::init();

                $sth = $dbh->prepare("SELECT CONCAT('modules/', LOWER(Module), '.module.php') AS Module FROM `module`");
                $sth->execute();

                $rows = $sth->fetchAll();
                $installed = array();

                foreach ($rows as $row)
                    $installed[] = $row['Module'];

            } catch (\Exception $e) {
                trigger_error(__('Cannot get installed modules'), E_USER_ERROR);
            }

            // Compare the two
            $to_install = array_diff($files, $installed);

            if (count($to_install) > 0) {
                $data['modulesToInstall'] = $to_install;
            }
        }

        $this->getState()->template = 'module-page';
        $this->getState()->setData($data);
    }

    /**
     * A grid of modules
     */
    public function Grid()
    {
        $modules = ModuleFactory::query();

        foreach ($modules as $module) {
            /* @var \Xibo\Entity\Module $module */

            // If the module config is not locked, present some buttons
            if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') != 'Checked') {

                // Edit button
                $module->buttons[] = array(
                    'id' => 'module_button_edit',
                    'url' => 'index.php?p=module&q=EditForm&ModuleID=' . $module->moduleId,
                    'text' => __('Edit')
                );
            }

            // Are there any buttons we need to provide as part of the module?
            if (isset($module->settings['buttons'])) {
                foreach ($module->settings['buttons'] as $button) {
                    $button['text'] = __($button['text']);
                    $module->buttons[] = $button;
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($modules);
    }

    /**
     * Edit Form
     */
    public function EditForm()
    {

        $user = $this->getUser();
        $response = $this->getState();
        $helpManager = new Help($db, $user);

        // Can we edit?
        if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Module Config Locked'), E_USER_ERROR);

        $moduleId = \Xibo\Helper\Sanitize::getInt('ModuleID');

        // Pull the currently known info from the DB
        $SQL = '';
        $SQL .= 'SELECT ModuleID, ';
        $SQL .= '   Module, ';
        $SQL .= '   Name, ';
        $SQL .= '   Enabled, ';
        $SQL .= '   Description, ';
        $SQL .= '   RegionSpecific, ';
        $SQL .= '   ValidExtensions, ';
        $SQL .= '   ImageUri, ';
        $SQL .= '   PreviewEnabled ';
        $SQL .= '  FROM `module` ';
        $SQL .= ' WHERE ModuleID = %d ';

        $SQL = sprintf($SQL, $moduleId);

        if (!$row = $db->GetSingleRow($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Error getting Module'));
        }

        $type = \Kit::ValidateParam($row['Module'], _WORD);

        // Set some information about the form
        Theme::Set('form_id', 'ModuleEditForm');
        Theme::Set('form_action', 'index.php?p=module&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="ModuleID" value="' . $moduleId . '" /><input type="hidden" name="type" value="' . $type . '" />');

        $formFields = array();
        $formFields[] = Form::AddText('ValidExtensions', __('Valid Extensions'), \Xibo\Helper\Sanitize::string($row['ValidExtensions']),
            __('The Extensions allowed on files uploaded using this module. Comma Separated.'), 'e', '');

        $formFields[] = Form::AddText('ImageUri', __('Image Uri'), \Xibo\Helper\Sanitize::string($row['ImageUri']),
            __('The Image to display for this module. This should be a path relative to the root of the installation.'), 'i', '');

        $formFields[] = Form::AddCheckbox('PreviewEnabled', __('Preview Enabled?'),
            \Xibo\Helper\Sanitize::int($row['PreviewEnabled']), __('When PreviewEnabled users will be able to see a preview in the layout designer'),
            'p');

        $formFields[] = Form::AddCheckbox('Enabled', __('Enabled?'),
            \Xibo\Helper\Sanitize::int($row['Enabled']), __('When Enabled users will be able to add media using this module'),
            'b');

        // Set any module specific form fields
        $module = \Xibo\Factory\ModuleFactory::create($type);

        // Merge in the fields from the settings
        foreach ($module->ModuleSettingsForm() as $field)
            $formFields[] = $field;

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit Module'), '350px', '325px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Module', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ModuleEditForm").submit()');

    }

    public function Edit()
    {


        $response = $this->getState();

        // Can we edit?
        if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Module Config Locked'), E_USER_ERROR);

        $moduleId = \Xibo\Helper\Sanitize::getInt('ModuleID');
        $type = \Kit::GetParam('type', _POST, _WORD);
        $validExtensions = \Kit::GetParam('ValidExtensions', _POST, _STRING, '');
        $imageUri = \Xibo\Helper\Sanitize::getString('ImageUri');
        $enabled = \Xibo\Helper\Sanitize::getCheckbox('Enabled');
        $previewEnabled = \Xibo\Helper\Sanitize::getCheckbox('PreviewEnabled');

        // Validation
        if ($moduleId == 0 || $moduleId == '')
            trigger_error(__('Module ID is missing'), E_USER_ERROR);

        if ($type == '')
            trigger_error(__('Type is missing'), E_USER_ERROR);

        if ($imageUri == '')
            trigger_error(__('Image Uri is a required field.'), E_USER_ERROR);

        // Process any module specific form fields
        $module = ModuleFactory::create($type, $this->db, $this->user);

        // Install Files for this module
        $module->InstallFiles();

        try {
            // Get the settings (may throw an exception)
            $settings = json_encode($module->ModuleSettings());

            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare('
                UPDATE `module` SET ImageUri = :image_url, ValidExtensions = :valid_extensions,
                    Enabled = :enabled, PreviewEnabled = :preview_enabled, settings = :settings
                 WHERE ModuleID = :module_id');

            $sth->execute(array(
                'image_url' => $imageUri,
                'valid_extensions' => $validExtensions,
                'enabled' => $enabled,
                'preview_enabled' => $previewEnabled,
                'settings' => $settings,
                'module_id' => $moduleId
            ));

            $response->SetFormSubmitResponse(__('Module Edited'), false);

        } catch (Exception $e) {

            Log::error($e->getMessage());

            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));

            trigger_error(__('Unable to update module'), E_USER_ERROR);
        }
    }

    /**
     * Edit Form
     */
    public function VerifyForm()
    {
        $user = $this->getUser();
        $response = $this->getState();
        $helpManager = new Help(NULL, $user);

        // Set some information about the form
        Theme::Set('form_id', 'VerifyForm');
        Theme::Set('form_action', 'index.php?p=module&q=Verify');

        $formFields = array();
        $formFields[] = Form::AddMessage(__('Verify all modules have been installed correctly by reinstalling any module related files'));

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Verify'), '350px', '325px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Module', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Verify'), '$("#VerifyForm").submit()');

    }

    public function Verify()
    {


        $response = $this->getState();

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $dbh->exec('UPDATE `media` SET valid = 0 WHERE moduleSystemFile = 1');
        } catch (Exception $e) {

            Log::error($e->getMessage());

            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));

            return false;
        }

        Media::installAllModuleFiles();

        $response->SetFormSubmitResponse(__('Verified'), false);

    }

    public function Install()
    {
        // Module file name
        $file = \Xibo\Helper\Sanitize::getString('module');

        if ($file == '')
            trigger_error(__('Unable to install module'), E_USER_ERROR);

        Log::notice('Request to install Module: ' . $file, 'module', 'Install');

        // Check that the file exists
        if (!file_exists($file))
            trigger_error(__('File does not exist'), E_USER_ERROR);

        // Make sure the file is in our list of expected module files
        $files = glob('modules/*.module.php');

        if (!in_array($file, $files))
            trigger_error(__('Not a module file'), E_USER_ERROR);

        // Load the file
        include_once($file);

        $type = str_replace('modules/', '', $file);
        $type = str_replace('.module.php', '', $type);

        // Load the module object inside the file
        if (!class_exists($type))
            trigger_error(__('Module file does not contain a class of the correct name'), E_USER_ERROR);

        try {
            Log::notice('Validation passed, installing module.', 'module', 'Install');
            $moduleObject = ModuleFactory::create($type, $this->db, $this->user);
            $moduleObject->InstallOrUpdate();
        } catch (Exception $e) {
            trigger_error(__('Unable to install module'), E_USER_ERROR);
        }

        Log::notice('Module Installed: ' . $file, 'module', 'Install');

        // Excellent... capital... success
        $response = $this->getState();
        $response->refresh = true;
        $response->refreshLocation = 'index.php?p=module';

    }

    /**
     * Execute a Module Action
     */
    public function Exec()
    {
        $requestedModule = \Kit::GetParam('mod', _REQUEST, _WORD);
        $requestedMethod = \Kit::GetParam('method', _REQUEST, _WORD);

        Log::debug('Module Exec for ' . $requestedModule . ' with method ' . $requestedMethod);

        // Validate that GetResource calls have a region
        if ($requestedMethod == 'GetResource' && \Kit::GetParam('regionId', _REQUEST, _INT) == 0)
            die(__('Get Resource Call without a Region'));

        // Create a new module to handle this request
        $module = \Xibo\Factory\ModuleFactory::createForWidget(Kit::GetParam('mod', _REQUEST, _WORD), \Kit::GetParam('widgetId', _REQUEST, _INT), $this->getUser()->userId, \Kit::GetParam('playlistId', _REQUEST, _INT), \Kit::GetParam('regionId', _REQUEST, _INT));

        // Authenticate access to this widget
        if (!$this->getUser()->checkViewable($module->widget))
            die(__('Access Denied'));

        // Set the permissions for this module
        $module->setPermission($this->getUser()->getPermission($module->widget));

        // Set the user - it is used in forms to return other entities
        $module->setUser($this->user);

        // What module has been requested?
        $response = null;
        $method = \Kit::GetParam('method', _REQUEST, _WORD);
        $raw = \Kit::GetParam('raw', _REQUEST, _WORD);

        if (method_exists($module, $method)) {
            $response = $module->$method();
        } else {
            // Set the error to display
            trigger_error(__('This Module does not exist'), E_USER_ERROR);
        }

        if ($raw == 'true') {
            echo $response;
            exit();
        } else {
            /* @var ApplicationState $response */

        }
    }
}
