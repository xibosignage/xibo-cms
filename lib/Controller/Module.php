<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Carbon\Carbon;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Xibo\Entity\Widget;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\WidgetAddEvent;
use Xibo\Event\WidgetEditEvent;
use Xibo\Event\WidgetEditOptionRequestEvent;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetAudioFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

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

    /** @var DataSetFactory */
    private $dataSetFactory;

    /** @var MenuBoardFactory */
    private $menuBoardFactory;

    /**
     * Set common dependencies.
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
     * @param DataSetFactory $dataSetFactory
     * @param MenuBoardFactory $menuBoardFactory
     */
    public function __construct(
        $store,
        $moduleFactory,
        $playlistFactory,
        $mediaFactory,
        $permissionFactory,
        $userGroupFactory,
        $widgetFactory,
        $transitionFactory,
        $regionFactory,
        $layoutFactory,
        $displayGroupFactory,
        $widgetAudioFactory,
        $displayFactory,
        $dataSetFactory,
        $menuBoardFactory
    ) {
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
        $this->dataSetFactory = $dataSetFactory;
        $this->menuBoardFactory = $menuBoardFactory;
    }

    /**
     * Display the module page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'module-page';
        $this->getState()->setData([
            'modulesToInstall' => $this->getInstallableModules()
        ]);

        return $this->render($request, $response);
    }

    /**
     * A grid of modules
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function grid(Request $request, Response $response)
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $filter = [
            'name' => $parsedQueryParams->getString('name'),
            'extension' => $parsedQueryParams->getString('extension'),
            'moduleId' => $parsedQueryParams->getInt('moduleId')
        ];

        $modules = $this->moduleFactory->query($this->gridRenderSort($parsedQueryParams), $this->gridRenderFilter($filter, $parsedQueryParams));

        foreach ($modules as $module) {
            /* @var \Xibo\Entity\Module $module */

            if ($this->isApi($request)) {
                break;
            }

            $module->includeProperty('buttons');

            // Edit button
            $module->buttons[] = array(
                'id' => 'module_button_edit',
                'url' => $this->urlFor($request, 'module.settings.form', ['id' => $module->moduleId]),
                'text' => __('Edit')
            );

            // Clear cache
            if ($module->regionSpecific == 1) {
                $module->buttons[] = array(
                    'id' => 'module_button_clear_cache',
                    'url' => $this->urlFor($request, 'module.clear.cache.form', ['id' => $module->moduleId]),
                    'text' => __('Clear Cache'),
                    'dataAttributes' => [
                        ['name' => 'auto-submit', 'value' => true],
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'module.clear.cache', ['id' => $module->moduleId])],
                        ['name' => 'commit-method', 'value' => 'PUT']
                    ]
                );
            }

            // Uninstall button
            if ($module->enabled === 0 && !empty($module->installName)) {
                $module->buttons[] = ['divider' => true];
                $module->buttons[] = [
                    'id' => 'module_button_uninstall',
                    'url' => $this->urlFor($request, 'module.uninstall.form', ['id' => $module->moduleId]),
                    'text' => __('Uninstall')
                ];
            }

            // Create a module object and return any buttons it may require
            try {
                $moduleObject = $this->moduleFactory->create($module->type)
                    ->setUser($this->getUser())
                    ->setPreview(
                        true,
                        RouteContext::fromRequest($request)->getRouteParser(),
                        0,
                        0
                    );

                // Are there any buttons we need to provide as part of the module?
                foreach ($moduleObject->settingsButtons() as $button) {
                    $button['text'] = __($button['text']);
                    $module->buttons[] = $button;
                }
            } catch (NotFoundException $notFoundException) {
                $this->getLog()->notice('Error with module ' . $module->type
                    . ', e: ' . $notFoundException->getMessage());
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->moduleFactory->countLast();
        $this->getState()->setData($modules);

        return $this->render($request, $response);
    }

    /**
     * Settings Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function settingsForm(Request $request, Response $response, $id)
    {
        // Can we edit?
        $moduleConfigLocked = $this->getConfig()->getSetting('MODULE_CONFIG_LOCKED_CHECKB') == 1
            || $this->getConfig()->getSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked';

        if (!$this->getUser()->userTypeId == 1) {
            throw new AccessDeniedException();
        }

        $module = null;
        $moduleFields = null;
        $error = false;
        try {
            $module = $this->moduleFactory->createById((int)$id);
            $moduleFields = $module->settingsForm();
        } catch (NotFoundException $notFoundException) {
            // There is an error with this module.
            $error = true;
        }

        // Pass to view
        $this->getState()->template = ($moduleFields == null) ? 'module-form-settings' : $moduleFields;
        $this->getState()->setData([
            'moduleConfigLocked' => $moduleConfigLocked,
            'moduleId' => $id,
            'module' => $module,
            'error' => $error,
            'help' => $this->getHelp()->link('Module', 'Edit')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Settings
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function settings(Request $request, Response $response, $id)
    {
        if (!$this->getUser()->userTypeId == 1) {
            throw new AccessDeniedException();
        }
        $sanitizedParams = $this->getSanitizer($request->getParams());

        try {
            $module = $this->moduleFactory->createById((int)$id);
        } catch (NotFoundException $notFoundException) {
            // If we can't load the module, we should disable it.
            $module = $this->moduleFactory->getById($id);
            $module->enabled = 0;
            $module->save();
            return $this->render($request, $response);
        }

        // Module loaded, so continue with the normal edit procedure.
        $module->getModule()->defaultDuration = $sanitizedParams->getInt('defaultDuration');
        $module->getModule()->validExtensions = $sanitizedParams->getString('validExtensions');
        $module->getModule()->enabled = $sanitizedParams->getCheckbox('enabled');
        $module->getModule()->previewEnabled = $sanitizedParams->getCheckbox('previewEnabled');

        // for Font Module set the User, needed for installing fonts in MediaService
        if ($module->getModuleType() === 'font') {
            $module->setUser($this->getUser());
        }

        // Install Files for this module
        $module->installFiles();

        // Get the settings (may throw an exception)
        $response = $module->settings($request, $response);

        // Save
        $module->getModule()->save();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $module->getModule()->name),
            'id' => $module->getModule()->moduleId,
            'data' => $module->getModule()
        ]);

        return $this->render($request, $response);
    }

    /**
     * Verify
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function verifyForm(Request $request, Response $response)
    {
        // Pass to view
        $this->getState()->template = 'module-form-verify';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('Module', 'Edit')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Verify Module
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function verify(Request $request, Response $response)
    {
        // Set all files to valid = 0
        $this->store->update('UPDATE `media` SET valid = 0 WHERE moduleSystemFile = 1', []);

        // Install all files
        $this->installAllModuleFiles();

        // Successful
        $this->getState()->hydrate([
            'message' => __('Verified')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Installs all files related to the enabled modules
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function installAllModuleFiles()
    {
        $this->getLog()->info('Installing all module files');

        // Do this for all enabled modules
        foreach ($this->moduleFactory->getEnabled() as $module) {
            /* @var \Xibo\Entity\Module $module */

            // Install Files for this module
            $moduleObject = $this->moduleFactory->create($module->type);
            $moduleObject->installFiles();
        }

        // Dump the cache on all displays
        foreach ($this->displayFactory->query() as $display) {
            /** @var \Xibo\Entity\Display $display */
            $display->notify();
        }
    }

    /**
     * Form for the install list
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function installListForm(Request $request, Response $response)
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

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param string $name
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function installForm(Request $request,Response $response, $name)
    {
        // Check the module hasn't already been installed
        if ($this->checkModuleInstalled($name)) {
            throw new InvalidArgumentException(__('Module already installed'), 'install');
        }

        // Use the name to get details about this module.
        if (file_exists(PROJECT_ROOT . '/modules/' . $name . '.json')) {
            $module = json_decode(file_get_contents(PROJECT_ROOT . '/modules/' . $name . '.json'));
        } else if (file_exists(PROJECT_ROOT . '/custom/' . $name . '.json')) {
            $module = json_decode(file_get_contents(PROJECT_ROOT . '/custom/' . $name . '.json'));
        } else {
            throw new InvalidArgumentException(__('Invalid module'), 'name');
        }

        $this->getState()->template = 'module-form-install';
        $this->getState()->setData([
            'module' => $module,
            'help' => $this->getHelp()->link('Module', 'Install')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Install Module
     * @param Request $request
     * @param Response $response
     * @param string $name
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function install(Request $request,Response $response, $name)
    {
        $this->getLog()->notice('Request to install Module: ' . $name);

        // Check the module hasn't already been installed
        if ($this->checkModuleInstalled($name))
            throw new InvalidArgumentException(__('Module already installed'), 'install');

        if (file_exists(PROJECT_ROOT . '/modules/' . $name . '.json')) {
            $moduleDetails = json_decode(file_get_contents(PROJECT_ROOT . '/modules/' . $name . '.json'));
        } else if (file_exists(PROJECT_ROOT . '/custom/' . $name . '.json')) {
            $moduleDetails = json_decode(file_get_contents(PROJECT_ROOT . '/custom/' . $name . '.json'));
        } else {
            throw new InvalidArgumentException(__('Invalid module'), 'name');
        }

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

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param string $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function uninstallForm(Request $request, Response $response, $id)
    {
        $module = $this->moduleFactory->getById($id);
        if (empty($module->installName)) {
            throw new InvalidArgumentException(
                __('Cannot uninstall a core module, please disable it instead.'),
                'installName'
            );
        }
        
        $this->getState()->template = 'module-form-uninstall';
        $this->getState()->setData([
            'module' => $module,
            'help' => $this->getHelp()->link('Module', 'Install')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Install Module
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function uninstall(Request $request, Response $response, $id)
    {
        $module = $this->moduleFactory->getById($id);
        if (empty($module->installName)) {
            throw new InvalidArgumentException(
                __('Cannot uninstall a core module, please disable it instead.'),
                'installName'
            );
        }
        $module->delete();

        $this->getState()->hydrate([
            'message' => sprintf(__('Uninstalled %s'), $module->name),
        ]);
        return $this->render($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @param string $type
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function addWidget(Request $request, Response $response,$type, $id)
    {
        $playlist = $this->playlistFactory->getById($id);

        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException(__('This Playlist is not shared with you with edit permission'));
        }

        // Check we have a permission factory
        if ($this->permissionFactory == null) {
            throw new ConfigurationException(__('Sorry there is an error with this request, cannot set inherited permissions'));
        }

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Load some information about this playlist
        // loadWidgets = true to keep the ordering correct
        $playlist->load([
            'playlistIncludeRegionAssignments' => false,
            'loadWidgets' => true,
            'loadTags' => false
        ]);

        // Is this Playlist the drawer?
        if ($playlist->isRegionPlaylist() && $type === 'subplaylist') {
            $region = $this->regionFactory->getById($playlist->regionId);
            if ($region->isDrawer == 1) {
                throw new InvalidArgumentException(
                    __('Sorry you cannot add a sub-playlist to the Drawer'),
                    'type'
                );
            }
        }

        // Create a module to use
        $module = $this->moduleFactory->createForWidget($type, null, $this->getUser()->userId, $id);

        // Assign this module to this Playlist in the appropriate place (which could be null)
        $displayOrder = $this->getSanitizer($request->getParams())->getInt('displayOrder');
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
        $response = $module->add($request, $response);

        // Module add will have saved our widget with the correct playlistId and displayOrder
        // if we have provided a displayOrder, then we ought to also save the Playlist so that new orders for those
        // existing Widgets are also saved.
        if ($displayOrder !== null) {
            $playlist->save();
        }

        // Successful
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $module->getName()),
            'id' => $module->widget->widgetId,
            'data' => $module->widget
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Widget Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editWidgetForm(Request $request, Response $response, $id)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($id));

        if (!$this->getUser()->checkEditable($module->widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Media file?
        $media = null;
        if ($module->getModule()->regionSpecific == 0) {
            try {
                $media = $module->getMedia();
            } catch (NotFoundException $e) {
                $this->getLog()->error('Library Widget does not have a Media Id. widgetId: ' . $id);
            }
        }

        // We load this module for previewing, because we use urlFor with templates
        $module
            ->setUser($this->getUser())
            ->setPreview(
                true,
                RouteContext::fromRequest($request)->getRouteParser(),
                0,
                0
            );

        // Do we have templates to load?
        $templates = [];
        if ($module->hasTemplates()) {
            $templates = $module->templatesAvailable(true);
        }

        // do we have special options requested?
        // Dashboards Widget needs to pull available services from connector
        $event = new WidgetEditOptionRequestEvent($module->widget);
        $this->getDispatcher()->dispatch($event, $event::$NAME);
        $options = $event->getOptions();

        // Pass to view
        $this->getState()->template = $module->editForm($request);
        $this->getState()->setData($module->setTemplateData([
            'module' => $module,
            'media' => $media,
            'validExtensions' => str_replace(',', '|', $module->getModule()->validExtensions),
            'templatesAvailable' => $templates,
            'options' => $options,
            'isTopLevel' => $this->playlistFactory->getById($module->widget->playlistId)->isRegionPlaylist(),
        ]));

        return $this->render($request, $response);
    }

    /**
     * Edit a Widget
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editWidget(Request $request, Response $response, $id)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($id));

        if (!$this->getUser()->checkEditable($module->widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($module->widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Inject the Current User
        $module->setUser($this->getUser());

        // Set an event to be called when we save this module
        $module->setSaveEvent(new WidgetEditEvent($module));

        // Call Module Edit
        $response = $module->edit($request, $response);

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $module->getName()),
            'id' => $module->widget->widgetId,
            'data' => $module->widget
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Widget Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteWidgetForm(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->loadByWidgetId($id);

        if (!$this->getUser()->checkDeleteable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with delete permission'));
        }

        $error = false;
        $module = null;
        try {
            $module = $this->moduleFactory->createWithWidget($widget);
        } catch (NotFoundException $notFoundException) {
            $error = true;
        }

        // Pass to view
        $this->getState()->template = 'module-form-delete';
        $this->getState()->setData([
            'widgetId' => $id,
            'module' => $module,
            'error' => $error,
            'help' => $this->getHelp()->link('Media', 'Delete')
        ]);

        return $this->render($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteWidget(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->loadByWidgetId($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkDeleteable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with delete permission'));
        }

        try {
            $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($id));
        } catch (NotFoundException $notFoundException) {
            // This module doesn't exist, so we just delete the widget.
            $widget->delete();
            $this->getState()->hydrate([
                'message' => __('Deleted Widget')
            ]);
            return $this->render($request, $response);
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($module->widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        $moduleName = $module->getName();
        $widgetMedia = $module->widget->mediaIds;

        // Inject the Current User
        $module->setUser($this->getUser());

        // Call Module Delete
        $response = $module->delete($request, $response);

        // Call Widget Delete
        $module->widget->delete();

         // Delete Media?
        if ($sanitizedParams->getCheckbox('deleteMedia') == 1) {
            foreach ($widgetMedia as $mediaId) {
                $media = $this->mediaFactory->getById($mediaId);

                // Check we have permissions to delete
                if (!$this->getUser()->checkDeleteable($media)) {
                    throw new AccessDeniedException();
                }

                $this->getDispatcher()->dispatch(MediaDeleteEvent::$NAME, new MediaDeleteEvent($media));
                $media->delete();
            }
        }

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $moduleName)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Widget Transition Form
     * @param Request $request
     * @param Response $response
     * @param string $type
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editWidgetTransitionForm(Request $request, Response $response, $type, $id)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($id));

        if (!$this->getUser()->checkEditable($module->widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

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

        return $this->render($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @param string $type
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editWidgetTransition(Request $request, Response $response,$type, $id)
    {
        $widget = $this->widgetFactory->getById($id);

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        $widget->load();
        $sanitizedParams = $this->getSanitizer($request->getParams());

        switch ($type) {
            case 'in':
                $widget->setOptionValue('transIn', 'attrib', $sanitizedParams->getString('transitionType'));
                $widget->setOptionValue('transInDuration', 'attrib', $sanitizedParams->getInt('transitionDuration'));
                $widget->setOptionValue('transInDirection', 'attrib', $sanitizedParams->getString('transitionDirection'));

                break;

            case 'out':
                $widget->setOptionValue('transOut', 'attrib', $sanitizedParams->getString('transitionType'));
                $widget->setOptionValue('transOutDuration', 'attrib', $sanitizedParams->getInt('transitionDuration'));
                $widget->setOptionValue('transOutDirection', 'attrib', $sanitizedParams->getString('transitionDirection'));

                break;

            default:
                throw new InvalidArgumentException(__('Unknown transition type'), 'type');
        }

        $widget->save();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited Transition')),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);

        return $this->render($request, $response);
    }

    /**
     * Widget Audio Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetAudioForm(Request $request, Response $response, $id)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($id));

        if (!$this->getUser()->checkEditable($module->widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Are we allowed to do this?
        if ($module->getModuleType() === 'subplaylist') {
            throw new InvalidArgumentException(__('Audio cannot be attached to a Sub-Playlist Widget. Please attach it to the Widgets inside the Playlist'), 'type');
        }

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

        return $this->render($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetAudio(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Are we allowed to do this?
        if ($widget->type === 'subplaylist') {
            throw new InvalidArgumentException(__('Audio cannot be attached to a Sub-Playlist Widget. Please attach it to the Widgets inside the Playlist'), 'type');
        }

        $widget->load();

        // Pull in the parameters we are expecting from the form.
        $mediaId = $sanitizedParams->getInt('mediaId');
        $volume = $sanitizedParams->getInt('volume', ['default' => 100]);
        $loop = $sanitizedParams->getCheckbox('loop');

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

        return $this->render($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetAudioDelete(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->getById($id);

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

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

        return $this->render($request, $response);
    }

    /**
     * Get Tab
     * @param Request $request
     * @param Response $response
     * @param string $tab
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getTab(Request $request, Response $response,$tab, $id)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($id));

        if (!$this->getUser()->checkViewable($module->widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you'));
        }

        $module
            ->setUser($this->getUser())
            ->setPreview(
                true,
                RouteContext::fromRequest($request)->getRouteParser(),
                0,
                0
            );

        // Pass to view
        $this->getState()->template = $module->getModuleType() . '-tab-' . $tab;
        $this->getState()->setData($module->getTab($tab));

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getDataSets(Request $request, Response $response)
    {
        $parsedRequestParams = $this->getSanitizer($request->getParams());

        $this->getState()->template = 'grid';
        $filter = [
            'dataSet' => $this->getSanitizer($request->getParams())->getString('dataSet')
        ];

        $this->getState()->setData($this->dataSetFactory->query($this->gridRenderSort($parsedRequestParams), $this->gridRenderFilter($filter, $parsedRequestParams)));
        $this->getState()->recordsTotal = $this->dataSetFactory->countLast();

        return $this->render($request, $response);
    }

    /**
     * @param $type
     * @param $templateId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getTemplateImage($type, $templateId)
    {
        $module = $this->moduleFactory->create($type);

        $response = $module->getTemplateImage($templateId);

        $this->setNoOutput(true);

        // Directly return the response
        return $response;
    }

    /**
     * Get Resource
     * @param Request $request
     * @param Response $response
     * @param $regionId
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getResource(Request $request, Response $response, $regionId, $id)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($id), $this->regionFactory->getById($regionId));

        if (!$this->getUser()->checkViewable($module->widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you'));
        }

        $params = $this->getSanitizer($request->getParams());

        // Call module GetResource
        $module
            ->setUser($this->getUser())
            ->setPreview(
                true,
                RouteContext::fromRequest($request)->getRouteParser(),
                $params->getDouble('width'),
                $params->getDouble('height')
            )
        ;

        if ($module->getModule()->regionSpecific == 0 && $module->getModule()->renderAs != 'html') {
            // Non region specific module - no caching required as this is only ever called via preview.
            $response = $module->download($request, $response);
        } else {
            // Region-specific module, need to handle caching and locking.
            $resource = $module->getResourceOrCache();

            if (!empty($resource)) {
                $response->getBody()->write($resource);
            }
        }

        $this->setNoOutput(true);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $name
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function customFormRender(Request $request, Response $response, $id, $name)
    {
        $module = $this->moduleFactory->createById((int)$id);

        if (!method_exists($module, $name)) {
            throw new ConfigurationException(__('Method does not exist'));
        }

        $module
            ->setUser($this->getUser())
            ->setPreview(
                true,
                RouteContext::fromRequest($request)->getRouteParser(),
                0,
                0
            );

        $formDetails = $module->$name();

        $this->getState()->template = $formDetails['template'];
        $this->getState()->setData($formDetails['data']);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $name
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function customFormExecute(Request $request, Response $response, $id, $name)
    {
        $module = $this->moduleFactory->createById((int)$id);

        if (!method_exists($module, $name)) {
            throw new ConfigurationException(__('Method does not exist'));
        }

        $module
            ->setUser($this->getUser())
            ->setPreview(
                true,
                RouteContext::fromRequest($request)->getRouteParser(),
                0,
                0
            );

        // Call the form named
        return $module->$name($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function clearCacheForm(Request $request, Response $response, $id)
    {
        $module = $this->moduleFactory->getById($id);

        $this->getState()->template = 'module-form-clear-cache';
        $this->getState()->autoSubmit = $this->getAutoSubmit('clearCache');
        $this->getState()->setData([
            'module' => $module,
            'help' => $this->getHelp()->link('Module', 'General')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Clear Cache
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function clearCache(Request $request, Response $response, $id)
    {
        $module = $this->moduleFactory->createById((int)$id);
        $module->dumpCacheForModule();

        $this->getState()->hydrate([
            'message' => sprintf(__('Cleared the Cache'))
        ]);

        return $this->render($request, $response);
    }

    /**
     * Widget Expiry Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetExpiryForm(Request $request, Response $response, $id)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($id));

        if (!$this->getUser()->checkEditable($module->widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Pass to view
        $this->getState()->template = 'module-form-expiry';
        $this->getState()->setData([
            'module' => $module,
            'fromDt' => ($module->widget->fromDt === Widget::$DATE_MIN) ? '' : Carbon::createFromTimestamp($module->widget->fromDt)->format(DateFormatHelper::getSystemFormat()),
            'toDt' => ($module->widget->toDt === Widget::$DATE_MAX) ? '' : Carbon::createFromTimestamp($module->widget->toDt)->format(DateFormatHelper::getSystemFormat()),
            'deleteOnExpiry' => $module->getOption('deleteOnExpiry', 0)
        ]);

        return $this->render($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetExpiry(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

        $widget->load();

        // Pull in the parameters we are expecting from the form.
        $fromDt = $sanitizedParams->getDate('fromDt');
        $toDt = $sanitizedParams->getDate('toDt');

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
        $widget->setOptionValue('deleteOnExpiry', 'attrib', ($sanitizedParams->getCheckbox('deleteOnExpiry') ? 1 : 0));

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

        if ($this->isApi($request)) {
            $widget->createdDt = Carbon::createFromTimestamp($widget->createdDt)->format(DateFormatHelper::getSystemFormat());
            $widget->modifiedDt = Carbon::createFromTimestamp($widget->modifiedDt)->format(DateFormatHelper::getSystemFormat());
            $widget->fromDt = Carbon::createFromTimestamp($widget->fromDt)->format(DateFormatHelper::getSystemFormat());
            $widget->toDt = Carbon::createFromTimestamp($widget->toDt)->format(DateFormatHelper::getSystemFormat());
        }

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited Expiry')),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);

        return $this->render($request, $response);
    }


    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getMenuBoards(Request $request, Response $response)
    {
        $parsedRequestParams = $this->getSanitizer($request->getParams());

        $this->getState()->template = 'grid';
        $filter = [
            'name' => $this->getSanitizer($request->getParams())->getString('name')
        ];

        $this->getState()->setData($this->menuBoardFactory->query($this->gridRenderSort($parsedRequestParams), $this->gridRenderFilter($filter, $parsedRequestParams)));
        $this->getState()->recordsTotal = $this->menuBoardFactory->countLast();

        return $this->render($request, $response);
    }

    /**
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}/region",
     *  operationId="WidgetAssignedRegionSet",
     *  tags={"widget"},
     *  summary="Set Widget Region",
     *  description="Set the Region this Widget is intended for - only suitable for Drawer Widgets",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="Id of the Widget to set region on",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="targetRegionId",
     *      in="formData",
     *      description="The target regionId",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetSetRegion(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isRegionPlaylist() || !$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Make sure we are on a Drawer Widget
        $region = $this->regionFactory->getById($playlist->regionId);
        if ($region->isDrawer !== 1) {
            throw new InvalidArgumentException(__('You can only set a target region on a Widget in the drawer.'), 'widgetId');
        }

        // Store the target regionId
        $widget->load();
        $widget->setOptionValue('targetRegionId', 'attrib', $sanitizedParams->getInt('targetRegionId'));

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

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Target region set')),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);

        return $this->render($request, $response);
    }
}
