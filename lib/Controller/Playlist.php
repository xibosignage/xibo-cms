<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Xibo\Entity\Permission;
use Xibo\Entity\Widget;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Playlist
 * @package Xibo\Controller
 */
class Playlist extends Base
{
    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var TransitionFactory
     */
    private $transitionFactory;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /** @var UserFactory */
    private $userFactory;

    /** @var TagFactory */
    private $tagFactory;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var DisplayFactory */
    private $displayFactory;

    /** @var ScheduleFactory */
    private $scheduleFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param PlaylistFactory $playlistFactory
     * @param RegionFactory $regionFactory
     * @param MediaFactory $mediaFactory
     * @param PermissionFactory $permissionFactory
     * @param TransitionFactory $transitionFactory
     * @param WidgetFactory $widgetFactory
     * @param ModuleFactory $moduleFactory
     * @param UserGroupFactory $userGroupFactory
     * @param UserFactory $userFactory
     * @param TagFactory $tagFactory
     * @param Twig $view
     * @param LayoutFactory $layoutFactory
     * @param DisplayFactory $displayFactory
     * @param ScheduleFactory $scheduleFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $playlistFactory, $regionFactory, $mediaFactory, $permissionFactory,
        $transitionFactory, $widgetFactory, $moduleFactory, $userGroupFactory, $userFactory, $tagFactory, Twig $view, $layoutFactory, $displayFactory, $scheduleFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->playlistFactory = $playlistFactory;
        $this->regionFactory = $regionFactory;
        $this->mediaFactory = $mediaFactory;
        $this->permissionFactory = $permissionFactory;
        $this->transitionFactory = $transitionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->moduleFactory = $moduleFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->userFactory = $userFactory;
        $this->tagFactory = $tagFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayFactory = $displayFactory;
        $this->scheduleFactory = $scheduleFactory;
    }

    /**
     * Display Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response)
    {
        $moduleFactory = $this->moduleFactory;

        // Call to render the template
        $this->getState()->template = 'playlist-page';
        $this->getState()->setData([
            'users' => $this->userFactory->query(),
            'groups' => $this->userGroupFactory->query(),
                        'modules' => array_map(function($element) use ($moduleFactory) { 
                    $module = $moduleFactory->createForInstall($element->class);
                    $module->setModule($element);
                    return $module;
                }, $moduleFactory->getAssignableModules())
        ]);

        return $this->render($request, $response);
    }

    /**
     * Playlist Search
     *
     * @SWG\Get(
     *  path="/playlist",
     *  operationId="playlistSearch",
     *  tags={"playlist"},
     *  summary="Search Playlists",
     *  description="Search for Playlists viewable by this user",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="query",
     *      description="Filter by Playlist Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="query",
     *      description="Filter by partial Playlist name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userId",
     *      in="query",
     *      description="Filter by user Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="query",
     *      description="Filter by tags",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="exactTags",
     *      in="query",
     *      description="A flag indicating whether to treat the tags filter as an exact match",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerUserGroupId",
     *      in="query",
     *      description="Filter by users in this UserGroupId",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="query",
     *      description="Embed related data such as regions, widgets, permissions, tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Playlist")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function grid(Request $request, Response $response)
    {
        $this->getState()->template = 'grid';
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Embed?
        $embed = ($sanitizedParams->getString('embed') != null) ? explode(',', $sanitizedParams->getString('embed')) : [];

        // Playlists
        $playlists = $this->playlistFactory->query($this->gridRenderSort($request), $this->gridRenderFilter([
            'name' => $sanitizedParams->getString('name'),
            'useRegexForName' => $sanitizedParams->getCheckbox('useRegexForName'),
            'userId' => $sanitizedParams->getInt('userId'),
            'tags' => $sanitizedParams->getString('tags'),
            'exactTags' => $sanitizedParams->getCheckbox('exactTags'),
            'playlistId' => $sanitizedParams->getInt('playlistId'),
            'ownerUserGroupId' => $sanitizedParams->getInt('ownerUserGroupId'),
            'mediaLike' => $sanitizedParams->getString('mediaLike'),
            'regionSpecific' => $sanitizedParams->getInt('regionSpecific', ['default' => 0])
        ], $request), $request);

        foreach ($playlists as $playlist) {

            // Handle embeds
            if (in_array('widgets', $embed)) {

                $loadPermissions = in_array('permissions', $embed);
                $loadTags = in_array('tags', $embed);

                $playlist->load([
                    'loadPermissions' => $loadPermissions,
                    'loadWidgets' => true,
                    'loadTags' => $loadTags
                ]);

                foreach ($playlist->widgets as $widget) {

                    /* @var Widget $widget */
                    $widget->module = $this->moduleFactory->createWithWidget($widget);

                    // Embed the name of this widget
                    $widget->name = $widget->module->getName();

                    // Augment with tags?
                    if ($loadTags) {
                        $widget->tags = $widget->module->getMediaTags();
                    }

                    // Add widget module type name
                    $widget->moduleName = $widget->module->getModuleName();

                    // Permissions?
                    if ($loadPermissions) {
                        // Augment with editable flag
                        $widget->isEditable = $this->getUser()->checkEditable($widget);

                        // Augment with deletable flag
                        $widget->isDeletable = $this->getUser()->checkDeleteable($widget);

                        // Augment with permissions flag
                        $widget->isPermissionsModifiable = $this->getUser()->checkPermissionsModifyable($widget);
                    }
                }
            }

            if ($this->isApi($request))
                continue;

            $playlist->includeProperty('buttons');
            $playlist->includeProperty('requiresDurationUpdate');

            switch ($playlist->enableStat) {

                case 'On':
                    $playlist->enableStatDescription = __('This Playlist has enable stat collection set to ON');
                    break;

                case 'Off':
                    $playlist->enableStatDescription = __('This Playlist has enable stat collection set to OFF');
                    break;

                default:
                    $playlist->enableStatDescription = __('This Playlist has enable stat collection set to INHERIT');
            }

            // Only proceed if we have edit permissions
            if ($this->getUser()->checkEditable($playlist)) {

                if ($playlist->isDynamic === 0) {
                    // Timeline edit
                    $playlist->buttons[] = [
                        'id' => 'playlist_timeline_button_edit',
                        'class' => 'XiboCustomFormButton',
                        'url' => $this->urlFor($request, 'playlist.timeline.form', ['id' => $playlist->playlistId]),
                        'text' => __('Timeline')
                    ];

                    $playlist->buttons[] = ['divider' => true];
                }

                // Edit Button
                $playlist->buttons[] = [
                    'id' => 'playlist_button_edit',
                    'url' => $this->urlFor($request, 'playlist.edit.form', ['id' => $playlist->playlistId]),
                    'text' => __('Edit')
                ];

                // Copy Button
                $playlist->buttons[] = [
                    'id' => 'playlist_button_copy',
                    'url' => $this->urlFor($request, 'playlist.copy.form', ['id' => $playlist->playlistId]),
                    'text' => __('Copy')
                ];

                // Set Enable Stat
                $playlist->buttons[] = [
                    'id' => 'playlist_button_setenablestat',
                    'url' => $this->urlFor($request, 'playlist.setenablestat.form', ['id' => $playlist->playlistId]),
                    'text' => __('Enable stats collection?'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request, 'playlist.setenablestat', ['id' => $playlist->playlistId])],
                        ['name' => 'commit-method', 'value' => 'put'],
                        ['name' => 'id', 'value' => 'playlist_button_setenablestat'],
                        ['name' => 'text', 'value' => __('Enable stats collection?')],
                        ['name' => 'rowtitle', 'value' => $playlist->name],
                        ['name' => 'form-callback', 'value' => 'setEnableStatMultiSelectFormOpen']
                    ]
                ];

                $playlist->buttons[] = ['divider' => true];
            }

            // Extra buttons if have delete permissions
            if ($this->getUser()->checkDeleteable($playlist)) {
                // Delete Button
                $playlist->buttons[] = [
                    'id' => 'playlist_button_delete',
                    'url' => $this->urlFor($request,'playlist.delete.form', ['id' => $playlist->playlistId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'playlist.delete', ['id' => $playlist->playlistId])],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'playlist_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'rowtitle', 'value' => $playlist->name]
                    ]
                ];

                $playlist->buttons[] = ['divider' => true];
            }

            // Extra buttons if we have modify permissions
            if ($this->getUser()->checkPermissionsModifyable($playlist)) {
                // Permissions button
                $playlist->buttons[] = [
                    'id' => 'playlist_button_permissions',
                    'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'Playlist', 'id' => $playlist->playlistId]),
                    'text' => __('Permissions')
                ];
            }

            $playlist->buttons[] = ['divider' => true];

            $playlist->buttons[] = array(
                'id' => 'usage_report_button',
                'url' => $this->urlFor($request,'playlist.usage.form', ['id' => $playlist->playlistId]),
                'text' => __('Usage Report')
            );
        }

        $this->getState()->recordsTotal = $this->playlistFactory->countLast();
        $this->getState()->setData($playlists);

        return $this->render($request, $response);
    }

    //<editor-fold desc="CRUD">

    /**
     * Add Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'playlist-form-add';

        return $this->render($request, $response);
    }

    /**
     * Add
     *
     * @SWG\Post(
     *  path="/playlist",
     *  operationId="playlistAdd",
     *  tags={"playlist"},
     *  summary="Add a Playlist",
     *  description="Add a new Playlist",
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Name for this Playlist",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isDynamic",
     *      in="formData",
     *      description="Is this Playlist Dynamic?",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="filterMediaName",
     *      in="formData",
     *      description="Add Library Media matching the name filter provided",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="filterMediaTag",
     *      in="formData",
     *      description="Add Library Media matching the tag filter provided",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Playlist"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if ($sanitizedParams->getString('name') == '') {
            throw new InvalidArgumentException(__('Please enter playlist name'), 'name');
        }

        $playlist = $this->playlistFactory->create($sanitizedParams->getString('name'), $this->getUser()->getId());
        $playlist->isDynamic = $sanitizedParams->getCheckbox('isDynamic');
        $playlist->enableStat = $sanitizedParams->getString('enableStat');

        $playlist->replaceTags($this->tagFactory->tagsFromString($sanitizedParams->getString('tags')));

        // Do we have a tag or name filter?
        $nameFilter = $sanitizedParams->getString('filterMediaName');
        $tagFilter = $sanitizedParams->getString('filterMediaTag');

        // Capture these as dynamic filter criteria
        if ($playlist->isDynamic === 1) {
            $playlist->filterMediaName = $nameFilter;
            $playlist->filterMediaTags = $tagFilter;
        }

        $playlist->save();

        // Default permissions
        foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($playlist), $playlist->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
            /* @var Permission $permission */
            $permission->save();
        }

        // Should we assign any existing media
        if (!empty($nameFilter) || !empty($tagFilter)) {
            $media = $this->mediaFactory->query(null, ['name' => $nameFilter, 'tags' => $tagFilter, 'assignable' => 1]);

            if (count($media) > 0) {
                $widgets = [];

                foreach ($media as $item) {
                    // Create a module
                    $module = $this->moduleFactory->create($item->mediaType);

                    // Determine the duration
                    $itemDuration = ($item->duration == 0) ? $module->determineDuration() : $item->duration;

                    // Create a widget
                    $widget = $this->widgetFactory->create($this->getUser()->userId, $playlist->playlistId, $item->mediaType, $itemDuration);
                    $widget->assignMedia($item->mediaId);

                    // Assign the widget to the module
                    $module->setWidget($widget);

                    // Set default options (this sets options on the widget)
                    $module->setDefaultWidgetOptions();

                    // Calculate the duration
                    $widget->calculateDuration($module);

                    // Assign the widget to the playlist
                    $playlist->assignWidget($widget);

                    // Add to a list of new widgets
                    $widgets[] = $widget;
                }

                // Save the playlist
                $playlist->save();

                // Handle permissions
                foreach ($widgets as $widget) {
                    /* @var Widget $widget */
                    if ($this->getConfig()->getSetting('INHERIT_PARENT_PERMISSIONS') == 1) {
                        // Apply permissions from the Parent
                        foreach ($playlist->permissions as $permission) {
                            /* @var Permission $permission */
                            $permission = $this->permissionFactory->create($permission->groupId, get_class($widget), $widget->getId(), $permission->view, $permission->edit, $permission->delete);
                            $permission->save();
                        }
                    } else {
                        foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($widget), $widget->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                            /* @var Permission $permission */
                            $permission->save();
                        }
                    }
                }
            }
        }

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $playlist->name),
            'id' => $playlist->playlistId,
            'data' => $playlist
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $playlist = $this->playlistFactory->getById($id);
        $tags = '';

        $arrayOfTags = array_filter(explode(',', $playlist->tags));
        $arrayOfTagValues = array_filter(explode(',', $playlist->tagValues));

        for ($i=0; $i<count($arrayOfTags); $i++) {
            if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL' )) {
                $tags .= $arrayOfTags[$i] . '|' . $arrayOfTagValues[$i];
                $tags .= ',';
            } else {
                $tags .= $arrayOfTags[$i] . ',';
            }
        }

        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'playlist-form-edit';
        $this->getState()->setData([
            'playlist' => $playlist,
            'tags' => $tags
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit
     *
     * @SWG\Put(
     *  path="/playlist/{playlistId}",
     *  operationId="playlistEdit",
     *  tags={"playlist"},
     *  summary="Edit a Playlist",
     *  description="Edit a Playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The PlaylistId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Name for this Playlist",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isDynamic",
     *      in="formData",
     *      description="Is this Playlist Dynamic?",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="filterMediaName",
     *      in="formData",
     *      description="Add Library Media matching the name filter provided",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="filterMediaTag",
     *      in="formData",
     *      description="Add Library Media matching the tag filter provided",
     *      type="string",
     *      required=false
     *   ),
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
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function edit(Request $request, Response $response, $id)
    {
        $playlist = $this->playlistFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException();
        }

        $playlist->name = $sanitizedParams->getString('name');
        $playlist->isDynamic = $sanitizedParams->getCheckbox('isDynamic');
        $playlist->enableStat = $sanitizedParams->getString('enableStat');

        $playlist->replaceTags($this->tagFactory->tagsFromString($sanitizedParams->getString('tags')));

        // Do we have a tag or name filter?
        // Capture these as dynamic filter criteria
        if ($playlist->isDynamic === 1) {
            $playlist->filterMediaName = $sanitizedParams->getString('filterMediaName');
            $playlist->filterMediaTags = $sanitizedParams->getString('filterMediaTag');
        }

        $playlist->save();

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $playlist->name),
            'id' => $playlist->playlistId,
            'data' => $playlist
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        $playlist = $this->playlistFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($playlist)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'playlist-form-delete';
        $this->getState()->setData([
            'playlist' => $playlist
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function delete(Request $request, Response $response, $id)
    {
        $playlist = $this->playlistFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($playlist)) {
            throw new AccessDeniedException();
        }

        // Issue the delete
        $playlist->delete();

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $playlist->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copy playlist form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function copyForm(Request $request, Response $response, $id)
    {
        // Get the playlist
        $playlist = $this->playlistFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($playlist)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'playlist-form-copy';
        $this->getState()->setData([
            'playlist' => $playlist
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copies a playlist
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Post(
     *  path="/playlist/copy/{playlistId}",
     *  operationId="playlistCopy",
     *  tags={"playlist"},
     *  summary="Copy Playlist",
     *  description="Copy a Playlist, providing a new name if applicable",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The Playlist ID to Copy",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The name for the new Playlist",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="copyMediaFiles",
     *      in="formData",
     *      description="Flag indicating whether to make new Copies of all Media Files assigned to the Playlist being Copied",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Playlist"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function copy(Request $request, Response $response, $id)
    {
        // Get the playlist
        $playlist = $this->playlistFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check Permissions
        if (!$this->getUser()->checkViewable($playlist)) {
            throw new AccessDeniedException();
        }

        // Load the playlist for Copy
        $playlist->load(['loadTags' => false]);
        $playlist = clone $playlist;

        $playlist->name = $sanitizedParams->getString('name');
        $playlist->setOwner($this->getUser()->userId);

        // Copy the media on the playlist and change the assignments.
        if ($sanitizedParams->getCheckbox('copyMediaFiles') == 1) {
            foreach ($playlist->widgets as $widget) {
                // Copy the media
                $oldMedia = $this->mediaFactory->getById($widget->getPrimaryMediaId());
                $media = clone $oldMedia;
                $media->setOwner($this->getUser()->userId);
                $media->save();

                $widget->unassignMedia($oldMedia->mediaId);
                $widget->assignMedia($media->mediaId);

                // Update the widget option with the new ID
                $widget->setOptionValue('uri', 'attrib', $media->storedAs);
            }
        }

        // Handle tags
        $tags = '';

        $arrayOfTags = array_filter(explode(',', $playlist->tags));
        $arrayOfTagValues = array_filter(explode(',', $playlist->tagValues));

        for ($i=0; $i<count($arrayOfTags); $i++) {
            if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL' )) {
                $tags .= $arrayOfTags[$i] . '|' . $arrayOfTagValues[$i];
                $tags .= ',';
            } else {
                $tags .= $arrayOfTags[$i] . ',';
            }
        }

        $playlist->replaceTags($this->tagFactory->tagsFromString($tags));

        // Set from global setting
        if ($playlist->enableStat == null) {
            $playlist->enableStat = $this->getConfig()->getSetting('PLAYLIST_STATS_ENABLED_DEFAULT');
        }

        // Save the new playlist
        $playlist->save();

        // Permissions
        foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($playlist), $playlist->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
            /* @var Permission $permission */
            $permission->save();
        }

        foreach ($playlist->widgets as $widget) {
            /* @var Widget $widget */
            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($widget), $widget->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Copied as %s'), $playlist->name),
            'id' => $playlist->playlistId,
            'data' => $playlist
        ]);

        return $this->render($request, $response);
    }
    //</editor-fold>

    /**
     * Timeline Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function timelineForm(Request $request, Response $response, $id)
    {
        // Get a complex object of playlists and widgets
        $playlist = $this->playlistFactory->getById($id);

        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException();
        }

        // Pass to view
        $this->getState()->template = 'region-form-timeline';
        $this->getState()->setData([
            'playlist' => $playlist,
            'help' => $this->getHelp()->link('Layout', 'RegionOptions')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Widget Grid
     *
     * @SWG\Get(
     *  path="/playlist/widget",
     *  operationId="playlistWidgetSearch",
     *  tags={"playlist"},
     *  summary="Playlist Widget Search",
     *  description="Search widgets on a Playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="query",
     *      description="The Playlist ID to Search",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="query",
     *      description="The Widget ID to Search",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Widget")
     *      )
     *  )
     * )
     *
     * This is not used by the WEB app - remains here for API usage only
     * TODO: deprecate
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetGrid(Request $request, Response $response)
    {
        $this->getState()->template = 'grid';
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $widgets = $this->widgetFactory->query($this->gridRenderSort($request), $this->gridRenderFilter([
            'playlistId' => $sanitizedParams->getInt('playlistId'),
            'widgetId' => $sanitizedParams->getInt('widgetId')
        ], $request));

        foreach ($widgets as $widget) {
            /* @var Widget $widget */
            $widget->load();

            $widget->module = $this->moduleFactory->createWithWidget($widget);

            // Add property for name
            $widget->name = $widget->module->getName();

            // Add property for transition
            $widget->transition = sprintf('%s / %s', $widget->module->getTransition('in'), $widget->module->getTransition('out'));

            if ($this->isApi($request)) {
                $widget->createdDt = $this->getDate()->getLocalDate($widget->createdDt);
                $widget->modifiedDt = $this->getDate()->getLocalDate($widget->modifiedDt);
                $widget->fromDt = $this->getDate()->getLocalDate($widget->fromDt);
                $widget->toDt = $this->getDate()->getLocalDate($widget->toDt);
            }
        }

        // Store the table rows
        $this->getState()->recordsTotal = $this->widgetFactory->countLast();
        $this->getState()->setData($widgets);

        return $this->render($request, $response);
    }

    /**
     * Add Library items to a Playlist
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Post(
     *  path="/playlist/library/assign/{playlistId}",
     *  operationId="playlistLibraryAssign",
     *  tags={"playlist"},
     *  summary="Assign Library Items",
     *  description="Assign Media from the Library to this Playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The Playlist ID to assign to",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="media",
     *      in="formData",
     *      description="Array of Media IDs to assign",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="Optional duration for all Media in this assignment to use on the Widget",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="Optional flag indicating whether to enable the useDuration field",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayOrder",
     *      in="formData",
     *      description="Optional integer to say which position this assignment should occupy in the list. If more than one media item is being added, this will be the position of the first one.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Playlist")
     *  )
     * )
     */
    public function libraryAssign(Request $request, Response $response, $id)
    {
        $playlist = $this->playlistFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

        if ($playlist->isDynamic === 1)
            throw new InvalidArgumentException(__('This Playlist is dynamically managed so cannot accept manual assignments.'), 'isDynamic');

        // Expect a list of mediaIds
        $media = $sanitizedParams->getIntArray('media');

        if (count($media) <= 0)
            throw new InvalidArgumentException(__('Please provide Media to Assign'), 'media');

        // Optional Duration
        $duration = ($sanitizedParams->getInt('duration'));

        // Optional displayOrder
        $displayOrder = $sanitizedParams->getInt('displayOrder');

        $newWidgets = [];

        // Loop through all the media
        foreach ($media as $mediaId) {
            /* @var int $mediaId */
            $item = $this->mediaFactory->getById($mediaId);

            if (!$this->getUser()->checkViewable($item))
                throw new AccessDeniedException(__('You do not have permissions to use this media'));

            if ($item->mediaType == 'genericfile' || $item->mediaType == 'font')
                throw new InvalidArgumentException(sprintf(__('You cannot assign file type %s to a playlist'), $item->mediaType), 'mediaType');

            // Create a module
            $module = $this->moduleFactory->create($item->mediaType);

            // Determine the duration
            // if we have a duration provided, then use it, otherwise use the duration recorded on the library item already
            $itemDuration = ($duration !== null) ? $duration : $item->duration;

            // If the library item duration (or provided duration) is 0, then call the module to determine what the
            // duration should be.
            // in most cases calling the module will return the Module Default Duration as configured in settings.
            $itemDuration = ($itemDuration == 0) ? $module->determineDuration() : $itemDuration;

            // Create a widget
            $widget = $this->widgetFactory->create($this->getUser()->userId, $id, $item->mediaType, $itemDuration);
            $widget->assignMedia($item->mediaId);

            // Assign the widget to the module
            $module->setWidget($widget);

            // Set default options (this sets options on the widget)
            $module->setDefaultWidgetOptions();

            // If a duration has been provided, then we want to use it, so set useDuration to 1.
            if ($duration !== null || $sanitizedParams->getCheckbox('useDuration') == 1) {
                $widget->useDuration = 1;
                $widget->duration = $itemDuration;
            }

            // Calculate the duration
            $widget->calculateDuration($module);

            // Assign the widget to the playlist
            $playlist->assignWidget($widget, $displayOrder);

            // If we have one provided we should bump the display order by 1 so that if we have more than one
            // media to assign, we don't put the second one in the same place as the first one.
            if ($displayOrder !== null) {
                $displayOrder++;
            }

            // Add to a list of new widgets
            $newWidgets[] = $widget;
        }

        // Save the playlist
        $playlist->save(['saveTags' => false]);

        // Handle permissions
        foreach ($newWidgets as $widget) {
            /* @var Widget $widget */
            if ($this->getConfig()->getSetting('INHERIT_PARENT_PERMISSIONS') == 1) {
                // Apply permissions from the Parent
                foreach ($playlist->permissions as $permission) {
                    /* @var Permission $permission */
                    $permission = $this->permissionFactory->create($permission->groupId, get_class($widget), $widget->getId(), $permission->view, $permission->edit, $permission->delete);
                    $permission->save();
                }
            } else {
                foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($widget), $widget->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                    /* @var Permission $permission */
                    $permission->save();
                }
            }
        }

        // Add new widgets to playlist for return values
        $playlist->newWidgets = $newWidgets;

        // Success
        $this->getState()->hydrate([
            'message' => __('Media Assigned'),
            'data' => $playlist
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Definition(
     *  definition="PlaylistWidgetList",
     *  @SWG\Property(
     *      property="widgetId",
     *      type="integer",
     *      description="Widget ID"
     *  ),
     *  @SWG\Property(
     *      property="position",
     *      type="integer",
     *      description="The position in the Playlist"
     *  )
     * )
     */

    /**
     * Order a playlist and its widgets
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Post(
     *  path="/playlist/order/{playlistId}",
     *  operationId="playlistOrder",
     *  tags={"playlist"},
     *  summary="Order Widgets",
     *  description="Set the order of widgets in the Playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The Playlist ID to Order",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="widgets",
     *      in="formData",
     *      description="Array of widgetIds and positions - all widgetIds present in the playlist need to be passed in the call with their positions",
     *      type="array",
     *      required=true,
     *      @SWG\Items(
     *          ref="#/definitions/PlaylistWidgetList"
     *      )
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Playlist")
     *  )
     * )
     */
    function order(Request $request, Response $response, $id)
    {
        $playlist = $this->playlistFactory->getById($id);

        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException();
        }

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Load the widgets
        $playlist->load();

        // Get our list of widget orders
        $widgets = $request->getParam('widgets', null);

        if ($widgets == null) {
            throw new InvalidArgumentException(__('Cannot Save empty region playlist. Please add widgets'), 'widgets');
        }

        // Go through each one and move it
        foreach ($widgets as $widgetId => $position) {

            // Find this item in the existing list and add it to our new order
            foreach ($playlist->widgets as $widget) {
                /* @var \Xibo\Entity\Widget $widget */
                if ($widget->getId() == $widgetId) {
                    $this->getLog()->debug('Setting Display Order ' . $position . ' on widgetId ' . $widgetId);
                    $widget->displayOrder = $position;
                    break;
                }
            }
        }

        $playlist->save(['saveTags' => false]);

        // Success
        $this->getState()->hydrate([
            'message' => __('Order Changed'),
            'data' => $playlist
        ]);

        return $this->render($request, $response);
    }

    /**
     * Playlist Usage Report Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function usageForm(Request $request, Response $response, $id)
    {
        $playlist = $this->playlistFactory->getById($id);

        if (!$this->getUser()->checkViewable($playlist)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'playlist-form-usage';
        $this->getState()->setData([
            'playlist' => $playlist
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/playlist/usage/{playlistId}",
     *  operationId="playlistUsageReport",
     *  tags={"playlist"},
     *  summary="Get Playlist Item Usage Report",
     *  description="Get the records for the playlist item usage report",
     * @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The Playlist Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *     response=200,
     *     description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function usage(Request $request, Response $response, $id)
    {
        $playlist = $this->playlistFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkViewable($playlist)) {
            throw new AccessDeniedException();
        }

        // Get a list of displays that this playlistId is used on
        $displays = [];
        $displayIds = [];

        // have we been provided with a date/time to restrict the scheduled events to?
        $playlistDate = $sanitizedParams->getDate('playlistEventDate');

        if ($playlistDate !== null) {
            // Get a list of scheduled events that this playlistId is used on, based on the date provided
            $toDate = $playlistDate->copy()->addDay();

            $events = $this->scheduleFactory->query(null, [
                'futureSchedulesFrom' => $playlistDate->format('U'),
                'futureSchedulesTo' => $toDate->format('U'),
                'playlistId' => $id
            ]);
        } else {
            // All scheduled events for this playlistId
            $events = $this->scheduleFactory->query(null, [
                'playlistId' => $id
            ]);
        }

        // Total records returned from the schedules query
        $totalRecords = $this->scheduleFactory->countLast();

        foreach ($events as $row) {
            /* @var \Xibo\Entity\Schedule $row */

            // Generate this event
            // Assess the date?
            if ($playlistDate !== null) {
                try {
                    $scheduleEvents = $row->getEvents($playlistDate, $toDate);
                } catch (GeneralException $e) {
                    $this->getLog()->error('Unable to getEvents for ' . $row->eventId);
                    continue;
                }

                // Skip events that do not fall within the specified days
                if (count($scheduleEvents) <= 0)
                    continue;

                $this->getLog()->debug('EventId ' . $row->eventId . ' as events: ' . json_encode($scheduleEvents));
            }

            // Load the display groups
            $row->load();

            foreach ($row->displayGroups as $displayGroup) {
                foreach ($this->displayFactory->getByDisplayGroupId($displayGroup->displayGroupId) as $display) {

                    if (in_array($display->displayId, $displayIds)) {
                        continue;
                    }

                    $displays[] = $display;
                    $displayIds = $display->displayId;

                }
            }
        }

        if ($this->isApi($request) && $displays == []) {
            $displays = [
                'data' =>__('Specified Playlist item is not in use.')];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $totalRecords;
        $this->getState()->setData($displays);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/playlist/usage/layouts/{playlistId}",
     *  operationId="playlistUsageLayoutsReport",
     *  tags={"playlist"},
     *  summary="Get Playlist Item Usage Report for Layouts",
     *  description="Get the records for the playlist item usage report for Layouts",
     * @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The Playlist Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *     response=200,
     *     description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function usageLayouts(Request $request, Response $response, $id)
    {
        $playlist = $this->playlistFactory->getById($id);

        if (!$this->getUser()->checkViewable($playlist)) {
            throw new AccessDeniedException();
        }

        $layouts = $this->layoutFactory->query(null, ['playlistId' => $id]);

        if (!$this->isApi($request)) {
            foreach ($layouts as $layout) {
                $layout->includeProperty('buttons');

                // Add some buttons for this row
                if ($this->getUser()->checkEditable($layout)) {
                    // Design Button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_design',
                        'linkType' => '_self', 'external' => true,
                        'url' => $this->urlFor($request,'layout.designer', array('id' => $layout->layoutId)),
                        'text' => __('Design')
                    );
                }

                // Preview
                $layout->buttons[] = array(
                    'id' => 'layout_button_preview',
                    'linkType' => '_blank',
                    'external' => true,
                    'url' => $this->urlFor($request,'layout.preview', ['id' => $layout->layoutId]),
                    'text' => __('Preview Layout')
                );
            }
        }

        if ($this->isApi($request) && $layouts == []) {
            $layouts = [
                'data' =>__('Specified Playlist item is not in use.')
            ];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->layoutFactory->countLast();
        $this->getState()->setData($layouts);

        return $this->render($request, $response);
    }

    /**
     * Set Enable Stats Collection of a Playlist
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Put(
     *  path="/playlist/setenablestat/{playlistId}",
     *  operationId="playlistSetEnableStat",
     *  tags={"playlist"},
     *  summary="Enable Stats Collection",
     *  description="Set Enable Stats Collection? to use for the collection of Proof of Play statistics for a Playlist.",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The Playlist ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option to enable the collection of Media Proof of Play statistics, On, Off or Inherit.",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */

    function setEnableStat(Request $request, Response $response, $id)
    {
        // Get the Playlist
        $playlist = $this->playlistFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($playlist)) {
            throw new AccessDeniedException();
        }

        $enableStat = $this->getSanitizer($request->getParams())->getString('enableStat');

        $playlist->enableStat = $enableStat;
        $playlist->save(['saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('For Playlist %s Enable Stats Collection is set to %s'), $playlist->name, __($playlist->enableStat))
        ]);

        return $this->render($request, $response);
    }

    /**
     * Set Enable Stat Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function setEnableStatForm(Request $request, Response $response, $id)
    {
        // Get the Playlist
        $playlist = $this->playlistFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($playlist)) {
            throw new AccessDeniedException();
        }

        $data = [
            'playlist' => $playlist,
            'help' => $this->getHelp()->link('Playlist', 'EnableStat')
        ];

        $this->getState()->template = 'playlist-form-setenablestat';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }
}