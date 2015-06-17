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

use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
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
    public function grid()
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
        foreach ($module->settingsForm() as $field)
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
        $module->installFiles();

        try {
            // Get the settings (may throw an exception)
            $settings = json_encode($module->settings());

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
            $moduleObject->installOrUpdate();
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
     * Add Widget Form
     * @param string $type
     * @param int $playlistId
     */
    public function addWidgetForm($type, $playlistId)
    {
        $playlist = PlaylistFactory::getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // Create a module to use
        $module = ModuleFactory::createForWidget($type, null, $this->getUser()->userId, $playlistId);

        // Pass to view
        $this->getState()->template = 'module-form-' . $module->getModuleType() . '-add';
        $this->getState()->setData([
            'playlist' => $playlist,
            'module' => $module
        ]);
    }

    /**
     * Add Widget
     * @param string $type
     * @param int $playlistId
     */
    public function addWidget($type, $playlistId)
    {
        $playlist = PlaylistFactory::getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // Create a module to use
        $module = ModuleFactory::createForWidget($type, null, $this->getUser()->userId, $playlistId);

        // Call module add
        $module->add();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $module->getName()),
            'id' => $module->widget->widgetId,
            'data' => [$module]
        ]);
    }

    /**
     * Edit Widget Form
     * @param int $widgetId
     */
    public function editWidgetForm($widgetId)
    {
        $module = ModuleFactory::createWithWidget(WidgetFactory::loadByWidgetId($widgetId));

        if (!$this->getUser()->checkEditable($module->widget))
            throw new AccessDeniedException();

        // Pass to view
        $this->getState()->template = 'module-form-' . $module->getModuleType() . '-edit';
        $this->getState()->setData([
            'module' => $module
        ]);
    }

    /**
     * Edit Widget
     * @param int $widgetId
     */
    public function editWidget($widgetId)
    {
        $module = ModuleFactory::createWithWidget(WidgetFactory::loadByWidgetId($widgetId));

        if (!$this->getUser()->checkEditable($module->widget))
            throw new AccessDeniedException();

        // Call Module Edit
        $module->edit();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $module->getName()),
            'id' => $module->widget->widgetId,
            'data' => [$module]
        ]);
    }

    /**
     * Delete Widget Form
     * @param int $widgetId
     */
    public function deleteWidgetForm($widgetId)
    {
        $module = ModuleFactory::createWithWidget(WidgetFactory::loadByWidgetId($widgetId));

        if (!$this->getUser()->checkDeleteable($module->widget))
            throw new AccessDeniedException();

        // Pass to view
        $this->getState()->template = 'module-form-delete';
        $this->getState()->setData([
            'module' => $module,
            'help' => Help::Link('Media', 'Delete')
        ]);
    }

    /**
     * Delete Widget
     * @param int $widgetId
     */
    public function deleteWidget($widgetId)
    {
        $module = ModuleFactory::createWithWidget(WidgetFactory::loadByWidgetId($widgetId));

        if (!$this->getUser()->checkDeleteable($module->widget))
            throw new AccessDeniedException();

        // Call Module Delete
        $module->delete();

        // Call Widget Delete
        $module->widget->delete();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $module->getName())
        ]);
    }

    /**
     * Edit Widget Transition Form
     * @param string $type
     * @param int $widgetId
     */
    public function editWidgetTransitionForm($type, $widgetId)
    {
        $module = ModuleFactory::createWithWidget(WidgetFactory::loadByWidgetId($widgetId));

        if (!$this->getUser()->checkEditable($module->widget))
            throw new AccessDeniedException();

        // Pass to view
        $this->getState()->template = 'module-form-transition';
        $this->getState()->setData([
            'type' => $type,
            'module' => $module,
            'transitions' => [
                'in' => TransitionFactory::getEnabledByType('in'),
                'out' => TransitionFactory::getEnabledByType('out'),
                'compassPoints' => array(
                    array('id' => 'N', 'name' => __('North')),
                    array('id' => 'NE', 'name' => __('North East')),
                    array('id' => 'E', 'name' => __('East')),
                    array('id' => 'SE', 'name' => __('South East')),
                    array('id' => 'S', 'name' => __('South')),
                    array('id' => 'SW', 'name' => __('South West')),
                    array('id' => 'W', 'name' => __('West')),
                    array('id' => 'NW', 'name' => __('North West'))
                )
            ],
            'help' => Help::Link('Transition', 'Edit')
        ]);
    }

    /**
     * Edit Widget Transition
     * @param string $type
     * @param int $widgetId
     */
    public function editWidgetTransition($type, $widgetId)
    {
        $widget = WidgetFactory::getById($widgetId);

        if (!$this->getUser()->checkEditable($widget))
            throw new AccessDeniedException();

        $widget->load();

        switch ($type) {
            case 'in':
                $widget->setOptionValue('transIn', 'attrib', Sanitize::getString('transitionType'));
                $widget->setOptionValue('transInDuration', 'attrib', Sanitize::getInt('transitionDuration'));
                $widget->setOptionValue('transInDirection', 'attrib', Sanitize::getString('transitionDirection'));

                break;

            case 'out':
                $widget->setOptionValue('transOut', 'attrib', Sanitize::getString('transitionType'));
                $widget->setOptionValue('transOutDuration', 'attrib', Sanitize::getInt('transitionDuration'));
                $widget->setOptionValue('transOutDirection', 'attrib', Sanitize::getString('transitionDirection'));

                break;

            default:
                throw new \InvalidArgumentException(__('Unknown transition type'));
        }

        $widget->save();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited Transition')),
            'id' => $widget->widgetId,
            'data' => [$widget]
        ]);
    }
}
