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

use Xibo\Entity\Permission;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
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
            $files = glob(PROJECT_ROOT . '/modules/*.json');

            // Get a list of all currently installed modules
            $installed = [];
            $data['modulesToInstall'] = [];

            foreach (ModuleFactory::query() as $row) {
                /* @var \Xibo\Entity\Module $row */
                $installed[] = $row->type;
            }

            // Compare the two
            foreach ($files as $file) {
                // Check to see if the module has already been installed
                $fileName = explode('.', basename($file));

                if (in_array($fileName[0], $installed))
                    continue;

                // If not, open it up and get some information about it
                $data['modulesToInstall'][] = json_decode(file_get_contents($file));
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
        $modules = ModuleFactory::query($this->gridRenderSort(), $this->gridRenderFilter());

        foreach ($modules as $module) {
            /* @var \Xibo\Entity\Module $module */

            if ($this->isApi())
                break;

            $module->includeProperty('buttons');

            // If the module config is not locked, present some buttons
            if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') != 'Checked') {

                // Edit button
                $module->buttons[] = array(
                    'id' => 'module_button_edit',
                    'url' => $this->urlFor('module.settings.form', ['id' => $module->moduleId]),
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
        $this->getState()->recordsTotal = ModuleFactory::countLast();
        $this->getState()->setData($modules);
    }

    /**
     * Settings Form
     * @param int $moduleId
     */
    public function settingsForm($moduleId)
    {
        // Can we edit?
        if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked')
            throw new \InvalidArgumentException(__('Module Config Locked'));

        if (!$this->getUser()->userTypeId == 1)
            throw new AccessDeniedException();

        $module = ModuleFactory::createById($moduleId);

        $moduleFields = $module->settingsForm();

        // Pass to view
        $this->getState()->template = ($moduleFields == null) ? 'module-form-settings' : $moduleFields;
        $this->getState()->setData([
            'module' => $module,
            'help' => Help::Link('Module', 'Edit')
        ]);
    }

    /**
     * Settings
     * @param int $moduleId
     */
    public function settings($moduleId)
    {
        // Can we edit?
        if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked')
            throw new \InvalidArgumentException(__('Module Config Locked'));

        if (!$this->getUser()->userTypeId == 1)
            throw new AccessDeniedException();

        $module = ModuleFactory::createById($moduleId);
        $module->getModule()->validExtensions = Sanitize::getString('validExtensions');
        $module->getModule()->imageUri = Sanitize::getString('imageUri');
        $module->getModule()->enabled = Sanitize::getString('enabled');
        $module->getModule()->previewEnabled = Sanitize::getString('previewEnabled');

        // Install Files for this module
        $module->installFiles();

        // Get the settings (may throw an exception)
        $module->settings();

        // Save
        $module->getModule()->save();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $module->getModule()->name),
            'id' => $module->getModule()->moduleId,
            'data' => $module->getModule()
        ]);
    }

    /**
     * Verify
     */
    public function verifyForm()
    {
        // Pass to view
        $this->getState()->template = 'module-form-verify';
        $this->getState()->setData([
            'help' => Help::Link('Module', 'Edit')
        ]);
    }

    /**
     * Verify Module
     */
    public function verify()
    {
        // Set all files to valid = 0
        PDOConnect::update('UPDATE `media` SET valid = 0 WHERE moduleSystemFile = 1', []);

        // Install all files
        Library::installAllModuleFiles();

        // Successful
        $this->getState()->hydrate([
            'message' => __('Verified')
        ]);
    }

    /**
     * @param string $name
     */
    public function installForm($name)
    {
        if (!file_exists('../modules/' . $name . '.json'))
            throw new \InvalidArgumentException(__('Invalid module'));

        // Use the name to get details about this module.
        $module = json_decode(file_get_contents('../modules/' . $name . '.json'));

        $this->getState()->template = 'module-form-install';
        $this->getState()->setData([
            'module' => $module,
            'help' => Help::Link('Module', 'Install')
        ]);
    }

    /**
     * Install Module
     * @param string $name
     */
    public function install($name)
    {
        Log::notice('Request to install Module: ' . $name);

        if (!file_exists('../modules/' . $name . '.json'))
            throw new \InvalidArgumentException(__('Invalid module'));

        // Use the name to get details about this module.
        $moduleDetails = json_decode(file_get_contents('../modules/' . $name . '.json'));

        // All modules should be capable of autoload
        $module = ModuleFactory::createForInstall($moduleDetails->class);
        $module->installOrUpdate();

        Log::notice('Module Installed: ' . $module->getModuleType());

        // Excellent... capital... success
        $this->getState()->hydrate([
            'message' => sprintf(__('Installed %s'), $module->getModuleType()),
            'data' => $module
        ]);
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
        $this->getState()->template = $module->getModuleType() . '-form-add';
        $this->getState()->setData([
            'playlist' => $playlist,
            'media' => MediaFactory::query(),
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

        // Load some information about this playlist
        $playlist->load([
            'playlistIncludeRegionAssignments' => false,
            'loadWidgets' => false
        ]);

        // Create a module to use
        $module = ModuleFactory::createForWidget($type, null, $this->getUser()->userId, $playlistId);

        // Inject the Current User
        $module->setUser($this->getUser());

        // Call module add
        $module->add();

        // Permissions
        if (Config::GetSetting('INHERIT_PARENT_PERMISSIONS') == 1) {
            // Apply permissions from the Parent
            foreach ($playlist->permissions as $permission) {
                /* @var Permission $permission */
                $permission = PermissionFactory::create($permission->groupId, get_class($module->widget), $module->widget->getId(), $permission->view, $permission->edit, $permission->delete);
                $permission->save();
            }
        } else {
            foreach (PermissionFactory::createForNewEntity($this->getUser(), get_class($module->widget), $module->widget->getId(), Config::GetSetting('LAYOUT_DEFAULT')) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }
        }

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $module->getName()),
            'id' => $module->widget->widgetId,
            'data' => $module
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
        $this->getState()->template = $module->getModuleType() . '-form-edit';
        $this->getState()->setData([
            'module' => $module,
            'media' => MediaFactory::query(),
            'validExtensions' => str_replace(',', '|', $module->getModule()->validExtensions)
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

        // Inject the Current User
        $module->setUser($this->getUser());

        // Call Module Edit
        $module->edit();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $module->getName()),
            'id' => $module->widget->widgetId,
            'data' => $module
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

        $moduleName = $module->getName();
        $widgetMedia = $module->widget->mediaIds;

        // Inject the Current User
        $module->setUser($this->getUser());

        // Call Module Delete
        $module->delete();

        // Call Widget Delete
        $module->widget->delete();

        // Delete Media?
        if (Sanitize::getCheckbox('deleteMedia') == 1) {
            foreach ($widgetMedia as $mediaId) {
                $media = MediaFactory::getById($mediaId);

                // Check we have permissions to delete
                if (!$this->getUser()->checkDeleteable($media))
                    throw new AccessDeniedException();

                $media->delete();
            }
        }

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $moduleName)
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
            'data' => $widget
        ]);
    }

    /**
     * Get Tab
     * @param string $tab
     * @param int $widgetId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getTab($tab, $widgetId)
    {
        $module = ModuleFactory::createWithWidget(WidgetFactory::loadByWidgetId($widgetId));

        if (!$this->getUser()->checkViewable($module->widget))
            throw new AccessDeniedException();

        // Pass to view
        $this->getState()->template = $module->getModuleType() . '-tab-' . $tab;
        $this->getState()->setData($module->getTab($tab));
    }

    /**
     * Get Resource
     * @param $regionId
     * @param $widgetId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getResource($regionId, $widgetId)
    {
        $module = ModuleFactory::createWithWidget(WidgetFactory::loadByWidgetId($widgetId), RegionFactory::getById($regionId));

        if (!$this->getUser()->checkViewable($module->widget))
            throw new AccessDeniedException();

        // Call module GetResource
        echo $module->getResource();
        $this->setNoOutput(true);
    }
}
