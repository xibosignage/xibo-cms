<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
use Xibo\Entity\Widget;
use Xibo\Event\WidgetAddEvent;
use Xibo\Event\WidgetEditEvent;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\ScheduleFactory;
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

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var ScheduleFactory  */
    private $scheduleFactory;

    /** @var DataSetFactory */
    private $dataSetFactory;

    /** @var PlayerVersionFactory  */
    private $playerVersionFactory;

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
     * @param DisplayFactory $displayFactory
     * @param ScheduleFactory $scheduleFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $moduleFactory, $playlistFactory, $mediaFactory, $permissionFactory, $userGroupFactory, $widgetFactory, $transitionFactory, $regionFactory, $layoutFactory, $displayGroupFactory, $widgetAudioFactory, $displayFactory, $scheduleFactory, $dataSetFactory)
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
        $this->displayFactory = $displayFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->dataSetFactory = $dataSetFactory;
    }

    /**
     * Display the module page
     */
    function displayPage()
    {
        $this->getState()->template = 'module-page';
        $this->getState()->setData([
            'modulesToInstall' => $this->getInstallableModules()
        ]);
    }

    /**
     * A grid of modules
     * @throws XiboException
     */
    public function grid()
    {
        $filter = [
            'name' => $this->getSanitizer()->getString('name'),
            'extension' => $this->getSanitizer()->getString('extension'),
            'moduleId' => $this->getSanitizer()->getInt('moduleId')
        ];

        $modules = $this->moduleFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($modules as $module) {
            /* @var \Xibo\Entity\Module $module */

            if ($this->isApi())
                break;

            $module->includeProperty('buttons');

            // Edit button
            $module->buttons[] = array(
                'id' => 'module_button_edit',
                'url' => $this->urlFor('module.settings.form', ['id' => $module->moduleId]),
                'text' => __('Edit')
            );

            // Clear cache
            if ($module->regionSpecific == 1) {
                $module->buttons[] = array(
                    'id' => 'module_button_clear_cache',
                    'url' => $this->urlFor('module.clear.cache.form', ['id' => $module->moduleId]),
                    'text' => __('Clear Cache')
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
     * @throws XiboException
     */
    public function settingsForm($moduleId)
    {
        // Can we edit?
        $moduleConfigLocked = ($this->getConfig()->getSetting('MODULE_CONFIG_LOCKED_CHECKB') == 1 || $this->getConfig()->getSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked');

        if (!$this->getUser()->userTypeId == 1)
            throw new AccessDeniedException();

        $module = $this->moduleFactory->createById($moduleId);

        $moduleFields = $module->settingsForm();

        // Pass to view
        $this->getState()->template = ($moduleFields == null) ? 'module-form-settings' : $moduleFields;
        $this->getState()->setData([
            'moduleConfigLocked' => $moduleConfigLocked,
            'module' => $module,
            'help' => $this->getHelp()->link('Module', 'Edit')
        ]);
    }

    /**
     * Settings
     * @param int $moduleId
     * @throws XiboException
     */
    public function settings($moduleId)
    {
        // Can we edit?
        $moduleConfigLocked = ($this->getConfig()->getSetting('MODULE_CONFIG_LOCKED_CHECKB') == 1 || $this->getConfig()->getSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked');

        if (!$this->getUser()->userTypeId == 1)
            throw new AccessDeniedException();

        $module = $this->moduleFactory->createById($moduleId);
        $module->getModule()->defaultDuration = $this->getSanitizer()->getInt('defaultDuration');
        $module->getModule()->validExtensions = $this->getSanitizer()->getString('validExtensions');
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
     * @throws \Exception
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
     * Form for the install list
     * @throws XiboException
     */
    public function installListForm()
    {
        // Use the name to get details about this module.
        $modules = $this->getInstallableModules();

        if (count($modules) <= 0)
            throw new InvalidArgumentException(__('Sorry, no modules available to install'), 'modules');

        $this->getState()->template = 'module-form-install-list';
        $this->getState()->setData([
            'modulesToInstall' => $modules,
            'help' => $this->getHelp()->link('Module', 'Install')
        ]);
    }

    /**
     * @param string $name
     * @throws InvalidArgumentException
     */
    public function installForm($name)
    {
        // Check the module hasn't already been installed
        if ($this->checkModuleInstalled($name))
            throw new InvalidArgumentException(__('Module already installed'), 'install');

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
     * @throws XiboException
     */
    public function install($name)
    {
        $this->getLog()->notice('Request to install Module: ' . $name);

        // Check the module hasn't already been installed
        if ($this->checkModuleInstalled($name))
            throw new InvalidArgumentException(__('Module already installed'), 'install');

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
     * Add Widget
     *
     * * @SWG\Post(
     *  path="/playlist/widget/{type}/{playlistId}",
     *  operationId="addWidget",
     *  tags={"widget"},
     *  summary="Add a Widget to a Playlist",
     *  description="Add a new Widget to a Playlist",
     *  @SWG\Parameter(
     *      name="type",
     *      in="path",
     *      description="The type of the Widget e.g. text. Media based Widgets like Image are added via POST /playlist/library/assign/{playlistId} call.",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The Playlist ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="displayOrder",
     *      in="formData",
     *      description="Optional integer to say which position this assignment should occupy in the list. If more than one media item is being added, this will be the position of the first one.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     *
     * @param string $type
     * @param int $playlistId
     * @throws XiboException
     */
    public function addWidget($type, $playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // Check we have a permission factory
        if ($this->permissionFactory == null)
            throw new ConfigurationException(__('Sorry there is an error with this request, cannot set inherited permissions'));

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

        // Load some information about this playlist
        // loadWidgets = true to keep the ordering correct
        $playlist->load([
            'playlistIncludeRegionAssignments' => false,
            'loadWidgets' => true,
            'loadTags' => false
        ]);

        // Create a module to use
        $module = $this->moduleFactory->createForWidget($type, null, $this->getUser()->userId, $playlistId);

        // Assign this module to this Playlist in the appropriate place (which could be null)
        $displayOrder = $this->getSanitizer()->getInt('displayOrder');
        $playlist->assignWidget($module->widget, $displayOrder);

        // Inject the Current User
        $module->setUser($this->getUser());

        // Check that we can call `add()` directly on this module
        if ($module->getModule()->regionSpecific != 1) {
            throw new InvalidArgumentException(__('Sorry but a file based Widget must be assigned not created'), 'type');
        }

        // Set an event to be called when we save this module
        $module->setSaveEvent(new WidgetAddEvent($module));

        // Call module add
        $module->add();

        // Module add will have saved our widget with the correct playlistId and displayOrder
        // if we have provided a displayOrder, then we ought to also save the Playlist so that new orders for those
        // existing Widgets are also saved.
        if ($displayOrder !== null) {
            $playlist->save();
        }

        // Permissions
        if ($this->getConfig()->getSetting('INHERIT_PARENT_PERMISSIONS') == 1) {
            // Apply permissions from the Parent
            foreach ($playlist->permissions as $permission) {
                /* @var Permission $permission */
                $permission = $this->permissionFactory->create($permission->groupId, get_class($module->widget), $module->widget->getId(), $permission->view, $permission->edit, $permission->delete);
                $permission->save();
            }
        } else {
            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($module->widget), $module->widget->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }
        }

        // Successful
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $module->getName()),
            'id' => $module->widget->widgetId,
            'data' => $module->widget
        ]);
    }

    /**
     * Edit Widget Form
     * @param int $widgetId
     * @throws XiboException
     */
    public function editWidgetForm($widgetId)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkEditable($module->widget))
            throw new AccessDeniedException();

        // Media file?
        $media = null;
        if ($module->getModule()->regionSpecific == 0) {
            try {
                $media = $module->getMedia();
            } catch (NotFoundException $e) {
                
            }
        }

        // Pass to view
        $this->getState()->template = $module->editForm();
        $this->getState()->setData($module->setTemplateData([
            'module' => $module,
            'media' => $media,
            'validExtensions' => str_replace(',', '|', $module->getModule()->validExtensions)
        ]));
    }

    /**
     * Edit a Widget
     *
     * @param int $widgetId
     * @throws XiboException
     */
    public function editWidget($widgetId)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkEditable($module->widget))
            throw new AccessDeniedException();

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($module->widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

        // Inject the Current User
        $module->setUser($this->getUser());

        // Set an event to be called when we save this module
        $module->setSaveEvent(new WidgetEditEvent($module));

        // Call Module Edit
        $module->edit();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $module->getName()),
            'id' => $module->widget->widgetId,
            'data' => $module->widget
        ]);
    }

    /**
     * Delete Widget Form
     * @param int $widgetId
     * @throws XiboException
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
     * Delete a Widget
     * @SWG\Delete(
     *  path="/playlist/widget/{widgetId}",
     *  operationId="WidgetDelete",
     *  tags={"widget"},
     *  summary="Delete a Widget",
     *  description="Deleted a specified widget",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The widget ID to delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *  )
     *)
     *
     * @param int $widgetId
     * @throws XiboException
     */
    public function deleteWidget($widgetId)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkDeleteable($module->widget))
            throw new AccessDeniedException();

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($module->widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

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
        if ($this->getSanitizer()->getCheckbox('deleteMedia', 0) == 1) {
            foreach ($widgetMedia as $mediaId) {
                $media = $this->mediaFactory->getById($mediaId);

                // Check we have permissions to delete
                if (!$this->getUser()->checkDeleteable($media))
                    throw new AccessDeniedException();

                $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory);
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
     * @throws XiboException
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
     * Edit Widget transition
     * @SWG\Put(
     *  path="/playlist/widget/transition/{type}/{widgetId}",
     *  operationId="WidgetEditTransition",
     *  tags={"widget"},
     *  summary="Adds In/Out transition",
     *  description="Adds In/Out transition to a specified widget",
     *  @SWG\Parameter(
     *      name="type",
     *      in="path",
     *      description="Transition type, available options: in, out",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The widget ID to add the transition to",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="transitionType",
     *      in="formData",
     *      description="Type of a transition, available Options: fly, fadeIn, fadeOut",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="transitionDuration",
     *      in="formData",
     *      description="Duration of this transition in milliseconds",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="transitionDirection",
     *      in="formData",
     *      description="The direction for this transition, only appropriate for transitions that move, such as fly. Available options: N, NE, E, SE, S, SW, W, NW",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
     *          type="string"
     *      )
     *   )
     * )
     *
     * @param string $type
     * @param int $widgetId
     *
     * @throws XiboException
     */
    public function editWidgetTransition($type, $widgetId)
    {
        $widget = $this->widgetFactory->getById($widgetId);

        if (!$this->getUser()->checkEditable($widget))
            throw new AccessDeniedException();

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

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
     * @throws XiboException
     */
    public function widgetAudioForm($widgetId)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkEditable($module->widget))
            throw new AccessDeniedException();

        $audioAvailable = true;
        if ($module->widget->countAudio() > 0) {
            $audio = $this->mediaFactory->getById($module->widget->getAudioIds()[0]);

            $this->getLog()->debug('Found audio: ' . $audio->mediaId . ', isEdited = ' . $audio->isEdited . ', retired = ' . $audio->retired);
            $audioAvailable = ($audio->isEdited == 0 && $audio->retired == 0);
        }

        // Pass to view
        $this->getState()->template = 'module-form-audio';
        $this->getState()->setData([
            'module' => $module,
            'media' => $this->mediaFactory->getByMediaType('audio'),
            'isAudioAvailable' => $audioAvailable
        ]);
    }

    /**
     * Edit an Audio Widget
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}/audio",
     *  operationId="WidgetAssignedAudioEdit",
     *  tags={"widget"},
     *  summary="Parameters for edting/adding audio file to a specific widget",
     *  description="Parameters for edting/adding audio file to a specific widget",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="Id of a widget to which you want to add audio or edit existing audio",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="formData",
     *      description="Id of a audio file in CMS library you wish to add to a widget",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="volume",
     *      in="formData",
     *      description="Volume percentage(0-100) for this audio to play at",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="loop",
     *      in="formData",
     *      description="Flag (0, 1) Should the audio loop if it finishes before the widget has finished?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param int $widgetId
     * @throws XiboException
     */
    public function widgetAudio($widgetId)
    {
        $widget = $this->widgetFactory->getById($widgetId);

        if (!$this->getUser()->checkEditable($widget))
            throw new AccessDeniedException();

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

        $widget->load();

        // Pull in the parameters we are expecting from the form.
        $mediaId = $this->getSanitizer()->getInt('mediaId');
        $volume = $this->getSanitizer()->getInt('volume', 100);
        $loop = $this->getSanitizer()->getCheckbox('loop');

        // Remove existing audio records.
        foreach ($widget->audio as $audio) {
            $widget->unassignAudio($audio);
        }

        if ($mediaId != 0) {
            $widgetAudio = $this->widgetAudioFactory->createEmpty();
            $widgetAudio->mediaId = $mediaId;
            $widgetAudio->volume = $volume;
            $widgetAudio->loop = $loop;

            $widget->assignAudio($widgetAudio);
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
     * Delete an Assigned Audio Widget
     * @SWG\Delete(
     *  path="/playlist/widget/{widgetId}/audio",
     *  operationId="WidgetAudioDelete",
     *  tags={"widget"},
     *  summary="Delete assigned audio widget",
     *  description="Delete assigned audio widget from specified widget ID",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="Id of a widget from which you want to delete the audio from",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *  )
     *)
     *
     * @param int $widgetId
     * @throws XiboException
     */
    public function widgetAudioDelete($widgetId)
    {
        $widget = $this->widgetFactory->getById($widgetId);

        if (!$this->getUser()->checkEditable($widget))
            throw new AccessDeniedException();

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

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
     * @throws XiboException
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

    public function getDataSets()
    {
        $this->getState()->template = 'grid';
        $filter = [
            'dataSet' => $this->getSanitizer()->getString('dataSet')
        ];

        $this->getState()->setData($this->dataSetFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter)));
        $this->getState()->recordsTotal = $this->dataSetFactory->countLast();
    }

    /**
     * @param $type
     * @param $templateId
     * @throws XiboException
     */
    public function getTemplateImage($type, $templateId)
    {
        $module = $this->moduleFactory->create($type);
        $module->getTemplateImage($templateId);
        $this->setNoOutput(true);
    }

    /**
     * Get Resource
     * @param $regionId
     * @param $widgetId
     * @throws XiboException
     */
    public function getResource($regionId, $widgetId)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId), $this->regionFactory->getById($regionId));

        if (!$this->getUser()->checkViewable($module->widget))
            throw new AccessDeniedException();

        // Call module GetResource
        $module->setUser($this->getUser());

        if ($module->getModule()->regionSpecific == 0) {
            // Non region specific module - no caching required as this is only ever called via preview.
            echo $module->getResource(0);
        } else {
            // Region-specific module, need to handle caching and locking.
            echo $module->getResourceOrCache(0);
        }

        $this->setNoOutput(true);
    }

    /**
     * @param $moduleId
     * @param $formName
     * @throws XiboException
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
     * @throws XiboException
     */
    public function customFormExecute($moduleId, $formName)
    {
        $module = $this->moduleFactory->createById($moduleId);

        if (!method_exists($module, $formName))
            throw new ConfigurationException(__('Method does not exist'));

        $module->$formName();
        $this->setNoOutput(true);
    }

    /**
     * Get installable modules
     * @return array
     */
    private function getInstallableModules()
    {
        $modules = [];

        // Do we have any modules to install?!
        if ($this->getConfig()->getSetting('MODULE_CONFIG_LOCKED_CHECKB') != 1 && $this->getConfig()->getSetting('MODULE_CONFIG_LOCKED_CHECKB') != 'Checked') {
            // Get a list of matching files in the modules folder
            $files = array_merge(glob(PROJECT_ROOT . '/modules/*.json'), glob(PROJECT_ROOT . '/custom/*.json'));

            // Get a list of all currently installed modules
            $installed = [];

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
                $modules[] = json_decode(file_get_contents($file));
            }
        }

        return $modules;
    }

    /**
     * Check whether a module is installed or not.
     * @param string $name
     * @return bool
     */
    private function checkModuleInstalled($name)
    {
        try {
            $this->moduleFactory->getByInstallName($name);
            return true;
        } catch (NotFoundException $notFoundException) {
            return false;
        }
    }

    /**
     * Clear Cache Form
     * @param $moduleId
     * @throws XiboException
     */
    public function clearCacheForm($moduleId)
    {
        $module = $this->moduleFactory->getById($moduleId);

        $this->getState()->template = 'module-form-clear-cache';
        $this->getState()->setData([
            'module' => $module,
            'help' => $this->getHelp()->link('Module', 'General')
        ]);
    }

    /**
     * Clear Cache
     * @param $moduleId
     * @throws XiboException
     */
    public function clearCache($moduleId)
    {
        $module = $this->moduleFactory->createById($moduleId);
        $module->dumpCacheForModule();

        $this->getState()->hydrate([
            'message' => sprintf(__('Cleared the Cache'))
        ]);
    }

    /**
     * Widget Expiry Form
     * @param int $widgetId
     * @throws XiboException
     */
    public function widgetExpiryForm($widgetId)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkEditable($module->widget))
            throw new AccessDeniedException();

        // Pass to view
        $this->getState()->template = 'module-form-expiry';
        $this->getState()->setData([
            'module' => $module,
            'fromDt' => ($module->widget->fromDt === Widget::$DATE_MIN) ? '' : $this->getDate()->getLocalDate($module->widget->fromDt),
            'toDt' => ($module->widget->toDt === Widget::$DATE_MAX) ? '' : $this->getDate()->getLocalDate($module->widget->toDt),
            'deleteOnExpiry' => $module->getOption('deleteOnExpiry', 0)
        ]);
    }

    /**
     * Edit an Expiry Widget
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}/expiry",
     *  operationId="WidgetAssignedExpiryEdit",
     *  tags={"widget"},
     *  summary="Set Widget From/To Dates",
     *  description="Control when this Widget is active on this Playlist",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="Id of a widget to which you want to add audio or edit existing audio",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="fromDt",
     *      in="formData",
     *      description="The From Date in Y-m-d H::i:s format",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="toDt",
     *      in="formData",
     *      description="The To Date in Y-m-d H::i:s format",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="deleteOnExpiry",
     *      in="formData",
     *      description="Delete this Widget when it expires?",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param int $widgetId
     * @throws XiboException
     */
    public function widgetExpiry($widgetId)
    {
        $widget = $this->widgetFactory->getById($widgetId);

        if (!$this->getUser()->checkEditable($widget))
            throw new AccessDeniedException();

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

        $widget->load();

        // Pull in the parameters we are expecting from the form.
        $fromDt = $this->getSanitizer()->getDate('fromDt');
        $toDt = $this->getSanitizer()->getDate('toDt');

        if ($fromDt !== null) {
            $widget->fromDt = $fromDt->format('U');
        } else {
            $widget->fromDt = Widget::$DATE_MIN;
        }

        if ($toDt !== null) {
            $widget->toDt = $toDt->format('U');
        } else {
            $widget->toDt = Widget::$DATE_MAX;
        }

        // Delete on expiry?
        $widget->setOptionValue('deleteOnExpiry', 'attrib', ($this->getSanitizer()->getCheckbox('deleteOnExpiry') ? 1 : 0));

        // Save
        $widget->save([
            'saveWidgetOptions' => true,
            'saveWidgetAudio' => false,
            'saveWidgetMedia' => false,
            'notify' => true,
            'notifyPlaylists' => true,
            'notifyDisplays' => false,
            'audit' => true
        ]);

        if ($this->isApi()) {
            $widget->createdDt = $this->getDate()->getLocalDate($widget->createdDt);
            $widget->modifiedDt = $this->getDate()->getLocalDate($widget->modifiedDt);
            $widget->fromDt = $this->getDate()->getLocalDate($widget->fromDt);
            $widget->toDt = $this->getDate()->getLocalDate($widget->toDt);
        }

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited Expiry')),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);
    }
}
