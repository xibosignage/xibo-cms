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
use Xibo\Exception\ConfigurationException;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetAudioFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Module
 * @package Xibo\Controller
 */
class Module extends Base
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /**
     * @var TransitionFactory
     */
    private $transitionFactory;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /** @var  LayoutFactory */
    protected $layoutFactory;

    /** @var  DisplayGroupFactory */
    protected $displayGroupFactory;

    /** @var  WidgetAudioFactory */
    protected $widgetAudioFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param ModuleFactory $moduleFactory
     * @param PlaylistFactory $playlistFactory
     * @param MediaFactory $mediaFactory
     * @param PermissionFactory $permissionFactory
     * @param UserGroupFactory $userGroupFactory
     * @param WidgetFactory $widgetFactory
     * @param TransitionFactory $transitionFactory
     * @param RegionFactory $regionFactory
     * @param LayoutFactory $layoutFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param WidgetAudioFactory $widgetAudioFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $moduleFactory, $playlistFactory, $mediaFactory, $permissionFactory, $userGroupFactory, $widgetFactory, $transitionFactory, $regionFactory, $layoutFactory, $displayGroupFactory, $widgetAudioFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->moduleFactory = $moduleFactory;
        $this->playlistFactory = $playlistFactory;
        $this->mediaFactory = $mediaFactory;
        $this->permissionFactory = $permissionFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->widgetFactory = $widgetFactory;
        $this->transitionFactory = $transitionFactory;
        $this->regionFactory = $regionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->widgetAudioFactory = $widgetAudioFactory;
    }

    /**
     * Display the module page
     */
    function displayPage()
    {
        $data = [];

        // Do we have any modules to install?!
        if ($this->getConfig()->GetSetting('MODULE_CONFIG_LOCKED_CHECKB') != 'Checked') {
            // Get a list of matching files in the modules folder
            $files = array_merge(glob(PROJECT_ROOT . '/modules/*.json'), glob(PROJECT_ROOT . '/custom/*.json'));

            // Get a list of all currently installed modules
            $installed = [];
            $data['modulesToInstall'] = [];

            foreach ($this->moduleFactory->query() as $row) {
                /* @var \Xibo\Entity\Module $row */
                $installed[] = $row->installName;
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
        $modules = $this->moduleFactory->query($this->gridRenderSort(), $this->gridRenderFilter());

        foreach ($modules as $module) {
            /* @var \Xibo\Entity\Module $module */

            if ($this->isApi())
                break;

            $module->includeProperty('buttons');

            // If the module config is not locked, present some buttons
            if ($this->getConfig()->GetSetting('MODULE_CONFIG_LOCKED_CHECKB') != 'Checked') {

                // Edit button
                $module->buttons[] = array(
                    'id' => 'module_button_edit',
                    'url' => $this->urlFor('module.settings.form', ['id' => $module->moduleId]),
                    'text' => __('Edit')
                );
            }

            // Create a module object and return any buttons it may require
            $moduleObject = $this->moduleFactory->create($module->type);

            // Are there any buttons we need to provide as part of the module?
            foreach ($moduleObject->settingsButtons() as $button) {
                $button['text'] = __($button['text']);
                $module->buttons[] = $button;
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->moduleFactory->countLast();
        $this->getState()->setData($modules);
    }

    /**
     * Settings Form
     * @param int $moduleId
     */
    public function settingsForm($moduleId)
    {
        // Can we edit?
        if ($this->getConfig()->GetSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked')
            throw new \InvalidArgumentException(__('Module Config Locked'));

        if (!$this->getUser()->userTypeId == 1)
            throw new AccessDeniedException();

        $module = $this->moduleFactory->createById($moduleId);

        $moduleFields = $module->settingsForm();

        // Pass to view
        $this->getState()->template = ($moduleFields == null) ? 'module-form-settings' : $moduleFields;
        $this->getState()->setData([
            'module' => $module,
            'help' => $this->getHelp()->link('Module', 'Edit')
        ]);
    }

    /**
     * Settings
     * @param int $moduleId
     */
    public function settings($moduleId)
    {
        // Can we edit?
        if ($this->getConfig()->GetSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked')
            throw new \InvalidArgumentException(__('Module Config Locked'));

        if (!$this->getUser()->userTypeId == 1)
            throw new AccessDeniedException();

        $module = $this->moduleFactory->createById($moduleId);
        $module->getModule()->defaultDuration = $this->getSanitizer()->getInt('defaultDuration');
        $module->getModule()->validExtensions = $this->getSanitizer()->getString('validExtensions');
        $module->getModule()->imageUri = $this->getSanitizer()->getString('imageUri');
        $module->getModule()->enabled = $this->getSanitizer()->getCheckbox('enabled');
        $module->getModule()->previewEnabled = $this->getSanitizer()->getCheckbox('previewEnabled');

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
            'help' => $this->getHelp()->link('Module', 'Edit')
        ]);
    }

    /**
     * Verify Module
     */
    public function verify()
    {
        // Set all files to valid = 0
        $this->store->update('UPDATE `media` SET valid = 0 WHERE moduleSystemFile = 1', []);

        // Install all files
        $this->getApp()->container->get('\Xibo\Controller\Library')->installAllModuleFiles();

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
        // Use the name to get details about this module.
        if (file_exists(PROJECT_ROOT . '/modules/' . $name . '.json'))
            $module = json_decode(file_get_contents(PROJECT_ROOT . '/modules/' . $name . '.json'));
        else if (file_exists(PROJECT_ROOT . '/custom/' . $name . '.json'))
            $module = json_decode(file_get_contents(PROJECT_ROOT . '/custom/' . $name . '.json'));
        else
            throw new \InvalidArgumentException(__('Invalid module'));


        $this->getState()->template = 'module-form-install';
        $this->getState()->setData([
            'module' => $module,
            'help' => $this->getHelp()->link('Module', 'Install')
        ]);
    }

    /**
     * Install Module
     * @param string $name
     */
    public function install($name)
    {
        $this->getLog()->notice('Request to install Module: ' . $name);

        if (file_exists(PROJECT_ROOT . '/modules/' . $name . '.json'))
            $moduleDetails = json_decode(file_get_contents(PROJECT_ROOT . '/modules/' . $name . '.json'));
        else if (file_exists(PROJECT_ROOT . '/custom/' . $name . '.json'))
            $moduleDetails = json_decode(file_get_contents(PROJECT_ROOT . '/custom/' . $name . '.json'));
        else
            throw new \InvalidArgumentException(__('Invalid module'));

        // All modules should be capable of autoload
        $module = $this->moduleFactory->createForInstall($moduleDetails->class);
        $module->setUser($this->getUser());
        $module->installOrUpdate($this->moduleFactory);

        $this->getLog()->notice('Module Installed: ' . $module->getModuleType());

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
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // Create a module to use
        $module = $this->moduleFactory->createForWidget($type, null, $this->getUser()->userId, $playlistId);

        // Pass to view
        $this->getState()->template = $module->addForm();
        $this->getState()->setData($module->setTemplateData([
            'playlist' => $playlist,
            'media' => $this->mediaFactory->query(),
            'module' => $module
        ]));
    }

    /**
     * Add Widget
     * @param string $type
     * @param int $playlistId
     * @throws ConfigurationException
     */
    public function addWidget($type, $playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // Check we have a permission factory
        if ($this->permissionFactory == null)
            throw new ConfigurationException(__('Sorry there is an error with this request, cannot set inherited permissions'));

        // Load some information about this playlist
        $playlist->setChildObjectDependencies($this->regionFactory);
        $playlist->load([
            'playlistIncludeRegionAssignments' => false,
            'loadWidgets' => false
        ]);

        // Create a module to use
        $module = $this->moduleFactory->createForWidget($type, null, $this->getUser()->userId, $playlistId);

        // Inject the Current User
        $module->setUser($this->getUser());

        // Call module add
        $module->add();

        // Permissions
        if ($this->getConfig()->GetSetting('INHERIT_PARENT_PERMISSIONS') == 1) {
            // Apply permissions from the Parent
            foreach ($playlist->permissions as $permission) {
                /* @var Permission $permission */
                $permission = $this->permissionFactory->create($permission->groupId, get_class($module->widget), $module->widget->getId(), $permission->view, $permission->edit, $permission->delete);
                $permission->save();
            }
        } else {
            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($module->widget), $module->widget->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
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
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkEditable($module->widget))
            throw new AccessDeniedException();

        // Pass to view
        $this->getState()->template = $module->editForm();
        $this->getState()->setData($module->setTemplateData([
            'module' => $module,
            'media' => $this->mediaFactory->query(),
            'validExtensions' => str_replace(',', '|', $module->getModule()->validExtensions)
        ]));
    }

    /**
     * Edit Widget
     * @param int $widgetId
     */
    public function editWidget($widgetId)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

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
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkDeleteable($module->widget))
            throw new AccessDeniedException();

        // Set some dependencies that are used in the delete
        $module->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory);

        // Pass to view
        $this->getState()->template = 'module-form-delete';
        $this->getState()->setData([
            'module' => $module,
            'help' => $this->getHelp()->link('Media', 'Delete')
        ]);
    }

    /**
     * Delete Widget
     * @param int $widgetId
     */
    public function deleteWidget($widgetId)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkDeleteable($module->widget))
            throw new AccessDeniedException();

        // Set some dependencies that are used in the delete
        $module->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory);

        $moduleName = $module->getName();
        $widgetMedia = $module->widget->mediaIds;

        // Inject the Current User
        $module->setUser($this->getUser());

        // Call Module Delete
        $module->delete();

        // Call Widget Delete
        $module->widget->delete();

        // Delete Media?
        if ($this->getSanitizer()->getCheckbox('deleteMedia') == 1) {
            foreach ($widgetMedia as $mediaId) {
                $media = $this->mediaFactory->getById($mediaId);

                // Check we have permissions to delete
                if (!$this->getUser()->checkDeleteable($media))
                    throw new AccessDeniedException();

                $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory);

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
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkEditable($module->widget))
            throw new AccessDeniedException();

        // Pass to view
        $this->getState()->template = 'module-form-transition';
        $this->getState()->setData([
            'type' => $type,
            'module' => $module,
            'transitions' => [
                'in' => $this->transitionFactory->getEnabledByType('in'),
                'out' => $this->transitionFactory->getEnabledByType('out'),
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
            'help' => $this->getHelp()->link('Transition', 'Edit')
        ]);
    }

    /**
     * Edit Widget Transition
     * @param string $type
     * @param int $widgetId
     */
    public function editWidgetTransition($type, $widgetId)
    {
        $widget = $this->widgetFactory->getById($widgetId);

        if (!$this->getUser()->checkEditable($widget))
            throw new AccessDeniedException();

        $widget->load();

        switch ($type) {
            case 'in':
                $widget->setOptionValue('transIn', 'attrib', $this->getSanitizer()->getString('transitionType'));
                $widget->setOptionValue('transInDuration', 'attrib', $this->getSanitizer()->getInt('transitionDuration'));
                $widget->setOptionValue('transInDirection', 'attrib', $this->getSanitizer()->getString('transitionDirection'));

                break;

            case 'out':
                $widget->setOptionValue('transOut', 'attrib', $this->getSanitizer()->getString('transitionType'));
                $widget->setOptionValue('transOutDuration', 'attrib', $this->getSanitizer()->getInt('transitionDuration'));
                $widget->setOptionValue('transOutDirection', 'attrib', $this->getSanitizer()->getString('transitionDirection'));

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
     * Widget Audio Form
     * @param int $widgetId
     */
    public function widgetAudioForm($widgetId)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkEditable($module->widget))
            throw new AccessDeniedException();

        // Pass to view
        $this->getState()->template = 'module-form-audio';
        $this->getState()->setData([
            'module' => $module,
            'media' => $this->mediaFactory->getByMediaType('audio')
        ]);
    }

    /**
     * Widget Audio
     * @param int $widgetId
     */
    public function widgetAudio($widgetId)
    {
        $widget = $this->widgetFactory->getById($widgetId);

        if (!$this->getUser()->checkEditable($widget))
            throw new AccessDeniedException();

        $widget->load();

        // Pull in the parameters we are expecting from the form.
        $mediaId = $this->getSanitizer()->getInt('mediaId');
        $volume = $this->getSanitizer()->getInt('volume');
        $loop = $this->getSanitizer()->getCheckbox('loop');

        if ($mediaId != 0) {
            $widgetAudio = $this->widgetAudioFactory->createEmpty();
            $widgetAudio->mediaId = $mediaId;
            $widgetAudio->volume = $volume;
            $widgetAudio->loop = $loop;

            $widget->assignAudio($widgetAudio);
        } else {
            // Remove existing audio records.
            foreach ($widget->audio as $audio) {
                $widget->unassignAudio($audio);
            }
        }

        $widget->save();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited Audio')),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);
    }

    /**
     * Widget Audio
     * @param int $widgetId
     */
    public function widgetAudioDelete($widgetId)
    {
        $widget = $this->widgetFactory->getById($widgetId);

        if (!$this->getUser()->checkEditable($widget))
            throw new AccessDeniedException();

        $widget->load();

        foreach ($widget->audio as $audio) {
            $widget->unassignAudio($audio);
        }

        $widget->save();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Removed Audio')),
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
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

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
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId), $this->regionFactory->getById($regionId));

        if (!$this->getUser()->checkViewable($module->widget))
            throw new AccessDeniedException();

        // Call module GetResource
        echo $module->getResource();
        $this->setNoOutput(true);
    }

    /**
     * @param $moduleId
     * @param $formName
     * @throws ConfigurationException
     */
    public function customFormRender($moduleId, $formName)
    {
        $module = $this->moduleFactory->createById($moduleId);

        if (!method_exists($module, $formName))
            throw new ConfigurationException(__('Method does not exist'));

        $formDetails = $module->$formName();

        $this->getState()->template = $formDetails['template'];
        $this->getState()->setData($formDetails['data']);
    }

    /**
     * @param $moduleId
     * @param $formName
     * @throws ConfigurationException
     */
    public function customFormExecute($moduleId, $formName)
    {
        $module = $this->moduleFactory->createById($moduleId);

        if (!method_exists($module, $formName))
            throw new ConfigurationException(__('Method does not exist'));

        $module->$formName();
        $this->setNoOutput(true);
    }
}
