<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\SubPlaylistItem;

/**
 * Class Playlist
 * @package Xibo\Controller
 */
class Playlist extends Base
{
    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var WidgetFactory */
    private $widgetFactory;

    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var UserGroupFactory */
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

    /** @var FolderFactory */
    private $folderFactory;

    /** @var RegionFactory */
    private $regionFactory;

    /**
     * Set common dependencies.
     * @param PlaylistFactory $playlistFactory
     * @param MediaFactory $mediaFactory
     * @param WidgetFactory $widgetFactory
     * @param ModuleFactory $moduleFactory
     * @param UserGroupFactory $userGroupFactory
     * @param UserFactory $userFactory
     * @param TagFactory $tagFactory
     * @param LayoutFactory $layoutFactory
     * @param DisplayFactory $displayFactory
     * @param ScheduleFactory $scheduleFactory
     * @param FolderFactory $folderFactory
     * @param RegionFactory $regionFactory
     */
    public function __construct(
        $playlistFactory,
        $mediaFactory,
        $widgetFactory,
        $moduleFactory,
        $userGroupFactory,
        $userFactory,
        $tagFactory,
        $layoutFactory,
        $displayFactory,
        $scheduleFactory,
        $folderFactory,
        $regionFactory
    ) {
        $this->playlistFactory = $playlistFactory;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->moduleFactory = $moduleFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->userFactory = $userFactory;
        $this->tagFactory = $tagFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayFactory = $displayFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->folderFactory = $folderFactory;
        $this->regionFactory = $regionFactory;
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
            'modules' => $moduleFactory->getAssignableModules()
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
     *   @SWG\Parameter(
     *      name="logicalOperator",
     *      in="query",
     *      description="When filtering by multiple Tags, which logical operator should be used? AND|OR",
     *      type="string",
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
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="query",
     *      description="Filter by Folder ID",
     *      type="integer",
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
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function grid(Request $request, Response $response)
    {
        $this->getState()->template = 'grid';
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Embed?
        $embed = ($sanitizedParams->getString('embed') != null)
            ? explode(',', $sanitizedParams->getString('embed'))
            : [];

        // Playlists
        $playlists = $this->playlistFactory->query($this->gridRenderSort($sanitizedParams), $this->gridRenderFilter([
            'name' => $sanitizedParams->getString('name'),
            'useRegexForName' => $sanitizedParams->getCheckbox('useRegexForName'),
            'userId' => $sanitizedParams->getInt('userId'),
            'tags' => $sanitizedParams->getString('tags'),
            'exactTags' => $sanitizedParams->getCheckbox('exactTags'),
            'playlistId' => $sanitizedParams->getInt('playlistId'),
            'notPlaylistId' => $sanitizedParams->getInt('notPlaylistId'),
            'ownerUserGroupId' => $sanitizedParams->getInt('ownerUserGroupId'),
            'mediaLike' => $sanitizedParams->getString('mediaLike'),
            'regionSpecific' => $sanitizedParams->getInt('regionSpecific', ['default' => 0]),
            'folderId' => $sanitizedParams->getInt('folderId'),
            'layoutId' => $sanitizedParams->getInt('layoutId'),
            'logicalOperator' => $sanitizedParams->getString('logicalOperator'),
            'logicalOperatorName' => $sanitizedParams->getString('logicalOperatorName'),
        ], $sanitizedParams));

        foreach ($playlists as $playlist) {
            // Handle embeds
            if (in_array('widgets', $embed)) {
                $loadPermissions = in_array('permissions', $embed);
                $loadTags = in_array('tags', $embed);
                $loadActions = in_array('actions', $embed);

                $playlist->load([
                    'loadPermissions' => $loadPermissions,
                    'loadWidgets' => true,
                    'loadTags' => $loadTags,
                    'loadActions' => $loadActions
                ]);

                foreach ($playlist->widgets as $widget) {
                    $widget->setUnmatchedProperty('tags', []);

                    try {
                        $module = $this->moduleFactory->getByType($widget->type);
                    } catch (NotFoundException $notFoundException) {
                        $this->getLog()->error('Module not found for widget: ' . $widget->type);
                        continue;
                    }

                    // Embed the name of this widget
                    $widget->setUnmatchedProperty('moduleName', $module->name);
                    $widgetName = $widget->getOptionValue('name', null);

                    if ($module->regionSpecific == 0) {
                        // Use the media assigned to this widget
                        $media = $this->mediaFactory->getById($widget->getPrimaryMediaId());
                        $media->load();
                        $widget->setUnmatchedProperty('name', $widget->getOptionValue('name', null) ?: $media->name);

                        // Augment with tags
                        $widget->setUnmatchedProperty('tags', $media->tags);
                    } else {
                        $widget->setUnmatchedProperty('name', $widget->getOptionValue('name', null) ?: $module->name);
                        $widget->setUnmatchedProperty('tags', []);
                    }

                    // Sub-playlists should calculate a fresh duration
                    if ($widget->type === 'subplaylist') {
                        $widget->calculateDuration($module);
                    }

                    // Get transitions
                    $widget->transitionIn = $widget->getOptionValue('transIn', null);
                    $widget->transitionOut = $widget->getOptionValue('transOut', null);
                    $widget->transitionDurationIn = $widget->getOptionValue('transInDuration', null);
                    $widget->transitionDurationOut = $widget->getOptionValue('transOutDuration', null);

                    // Permissions?
                    if ($loadPermissions) {
                        // Augment with editable flag
                        $widget->setUnmatchedProperty('isEditable', $this->getUser()->checkEditable($widget));

                        // Augment with deletable flag
                        $widget->setUnmatchedProperty('isDeletable', $this->getUser()->checkDeleteable($widget));

                        // Augment with viewable flag
                        $widget->setUnmatchedProperty('isViewable', $this->getUser()->checkViewable($widget));

                        // Augment with permissions flag
                        $widget->setUnmatchedProperty(
                            'isPermissionsModifiable',
                            $this->getUser()->checkPermissionsModifyable($widget)
                        );
                    }
                }
            }


            if ($sanitizedParams->getCheckbox('fullScreenScheduleCheck')) {
                $fullScreenCampaignId = $this->hasFullScreenLayout($playlist);
                $playlist->setUnmatchedProperty('hasFullScreenLayout', (!empty($fullScreenCampaignId)));
                $playlist->setUnmatchedProperty('fullScreenCampaignId', $fullScreenCampaignId);
            }

            if ($this->isApi($request)) {
                continue;
            }

            $playlist->includeProperty('buttons');

            switch ($playlist->enableStat) {
                case 'On':
                    $playlist->setUnmatchedProperty(
                        'enableStatDescription',
                        __('This Playlist has enable stat collection set to ON')
                    );
                    break;

                case 'Off':
                    $playlist->setUnmatchedProperty(
                        'enableStatDescription',
                        __('This Playlist has enable stat collection set to OFF')
                    );
                    break;

                default:
                    $playlist->setUnmatchedProperty(
                        'enableStatDescription',
                        __('This Playlist has enable stat collection set to INHERIT')
                    );
            }

            // Only proceed if we have edit permissions
            if ($this->getUser()->featureEnabled('playlist.modify')
                && $this->getUser()->checkEditable($playlist)
            ) {
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

                if ($this->getUser()->featureEnabled('folder.view')) {
                    // Select Folder
                    $playlist->buttons[] = [
                        'id' => 'playlist_button_selectfolder',
                        'url' => $this->urlFor($request, 'playlist.selectfolder.form', ['id' => $playlist->playlistId]),
                        'text' => __('Select Folder'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor($request, 'playlist.selectfolder', [
                                    'id' => $playlist->playlistId
                                ])
                            ],
                            ['name' => 'commit-method', 'value' => 'put'],
                            ['name' => 'id', 'value' => 'playlist_button_selectfolder'],
                            ['name' => 'text', 'value' => __('Move to Folder')],
                            ['name' => 'rowtitle', 'value' => $playlist->name],
                            ['name' => 'form-callback', 'value' => 'moveFolderMultiSelectFormOpen']
                        ]
                    ];
                }

                // Set Enable Stat
                $playlist->buttons[] = [
                    'id' => 'playlist_button_setenablestat',
                    'url' => $this->urlFor($request, 'playlist.setenablestat.form', ['id' => $playlist->playlistId]),
                    'text' => __('Enable stats collection?'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor($request, 'playlist.setenablestat', [
                                'id' => $playlist->playlistId
                            ])
                        ],
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
            if ($this->getUser()->featureEnabled('playlist.modify')
                && $this->getUser()->checkDeleteable($playlist)
            ) {
                // Delete Button
                $playlist->buttons[] = [
                    'id' => 'playlist_button_delete',
                    'url' => $this->urlFor($request, 'playlist.delete.form', ['id' => $playlist->playlistId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor($request, 'playlist.delete', [
                                'id' => $playlist->playlistId
                            ])
                        ],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'playlist_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $playlist->name]
                    ]
                ];

                $playlist->buttons[] = ['divider' => true];
            }

            // Extra buttons if we have modify permissions
            if ($this->getUser()->featureEnabled('playlist.modify')
                && $this->getUser()->checkPermissionsModifyable($playlist)
            ) {
                // Permissions button
                $playlist->buttons[] = [
                    'id' => 'playlist_button_permissions',
                    'url' => $this->urlFor($request, 'user.permissions.form', [
                        'entity' => 'Playlist',
                        'id' => $playlist->playlistId
                    ]),
                    'text' => __('Share'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor($request, 'user.permissions.multi', [
                                'entity' => 'Playlist',
                                'id' => $playlist->playlistId
                            ])
                        ],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'playlist_button_permissions'],
                        ['name' => 'text', 'value' => __('Share')],
                        ['name' => 'rowtitle', 'value' => $playlist->name],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        [
                            'name' => 'custom-handler-url',
                            'value' => $this->urlFor($request, 'user.permissions.multi.form', [
                                'entity' => 'Playlist'
                            ])
                        ],
                        ['name' => 'content-id-name', 'value' => 'playlistId']
                    ]
                ];
            }

            if ($this->getUser()->featureEnabled(['schedule.view', 'layout.view'])) {
                $playlist->buttons[] = ['divider' => true];

                $playlist->buttons[] = array(
                    'id' => 'usage_report_button',
                    'url' => $this->urlFor($request, 'playlist.usage.form', ['id' => $playlist->playlistId]),
                    'text' => __('Usage Report')
                );
            }

            // Schedule
            if ($this->getUser()->featureEnabled('schedule.add')
                && ($this->getUser()->checkEditable($playlist)
                    || $this->getConfig()->getSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 1)
            ) {
                $playlist->buttons[] = [
                    'id' => 'playlist_button_schedule',
                    'url' => $this->urlFor(
                        $request,
                        'schedule.add.form',
                        ['id' => $playlist->playlistId, 'from' => 'Playlist']
                    ),
                    'text' => __('Schedule')
                ];
            }
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
     *      name="logicalOperatorName",
     *      in="formData",
     *      description="When filtering by multiple names in name filter, which logical operator should be used? AND|OR",
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
     *  @SWG\Parameter(
     *      name="exactTags",
     *      in="formData",
     *      description="When filtering by Tags, should we use exact match?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="logicalOperator",
     *      in="formData",
     *      description="When filtering by Tags, which logical operator should be used? AND|OR",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="maxNumberOfItems",
     *      in="formData",
     *      description="Maximum number of items that can be assigned to this Playlist (dynamic Playlist only)",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
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

        // Folders
        $folderId = $sanitizedParams->getInt('folderId');
        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }

        $folder = $this->folderFactory->getById($folderId, 0);
        $playlist->folderId = $folder->id;
        $playlist->permissionsFolderId = $folder->getPermissionFolderIdOrThis();

        // Tags
        if ($this->getUser()->featureEnabled('tag.tagging')) {
            if (is_array($sanitizedParams->getParam('tags'))) {
                $tags = $this->tagFactory->tagsFromJson($sanitizedParams->getArray('tags'));
            } else {
                $tags = $this->tagFactory->tagsFromString($sanitizedParams->getString('tags'));
            }

            $playlist->updateTagLinks($tags);
        }

        // Do we have a tag, name or folder filter?
        $nameFilter = $sanitizedParams->getString('filterMediaName');
        $nameFilterLogicalOperator = $sanitizedParams->getString('logicalOperatorName');
        $tagFilter = $this->getUser()->featureEnabled('tag.tagging') ? $sanitizedParams->getString('filterMediaTag') : null;
        $logicalOperator = $this->getUser()->featureEnabled('tag.tagging') ? $sanitizedParams->getString('logicalOperator') : 'OR';
        $exactTags = $this->getUser()->featureEnabled('tag.tagging') ? $sanitizedParams->getCheckbox('exactTags') : 0;
        $folderIdFilter = $this->getUser()->featureEnabled('folder.view') ? $sanitizedParams->getInt('filterFolderId') : null;

        // Capture these as dynamic filter criteria
        if ($playlist->isDynamic === 1) {
            if (empty($nameFilter) && empty($tagFilter) && empty($folderIdFilter)) {
                throw new InvalidArgumentException(__('No filters have been set for this dynamic Playlist, please click the Filters tab to define'));
            }
            $playlist->filterMediaName = $nameFilter;
            $playlist->filterMediaNameLogicalOperator = $nameFilterLogicalOperator;
            if ($this->getUser()->featureEnabled('tag.tagging')) {
                $playlist->filterMediaTags = $tagFilter;
                $playlist->filterExactTags = $exactTags;
                $playlist->filterMediaTagsLogicalOperator = $logicalOperator;
            }

            if ($this->getUser()->featureEnabled('folder.view')) {
                $playlist->filterFolderId = $folderIdFilter;
            }

            $playlist->maxNumberOfItems = $sanitizedParams->getInt('maxNumberOfItems', ['default' => $this->getConfig()->getSetting('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER')]);
        }

        $playlist->save();

        // Should we assign any existing media
        if (!empty($nameFilter) || !empty($tagFilter) || !empty($folderIdFilter)) {
            $media = $this->mediaFactory->query(
                null,
                [
                    'name' => $nameFilter,
                    'tags' => $tagFilter,
                    'folderId' => $folderIdFilter,
                    'assignable' => 1,
                    'exactTags' => $exactTags,
                    'logicalOperator' => $logicalOperator,
                    'logicalOperatorName' => $nameFilterLogicalOperator
                ]
            );

            if (count($media) > 0) {
                $widgets = [];

                foreach ($media as $item) {
                    // Assign items from the library.
                    // Get a module to use
                    $module = $this->moduleFactory->getByType($item->mediaType);

                    // The item duration shouldn't ever be 0 in the library, but in case it is we set to the default
                    $itemDuration = ($item->duration == 0) ? $module->defaultDuration : $item->duration;

                    // Create a widget
                    $widget = $this->widgetFactory->create(
                        $this->getUser()->userId,
                        $playlist->playlistId,
                        $item->mediaType,
                        $itemDuration,
                        $module->schemaVersion
                    );
                    $widget->assignMedia($item->mediaId);

                    // Calculate the duration
                    $widget->calculateDuration($module);

                    // Assign the widget to the playlist
                    $playlist->assignWidget($widget);

                    // Add to a list of new widgets
                    $widgets[] = $widget;
                    if ($playlist->isDynamic && count($widgets) >= $playlist->maxNumberOfItems) {
                        $this->getLog()->debug(sprintf(
                            'Dynamic Playlist ID %d, has reached the maximum number of items %d, finishing assignments',
                            $playlist->playlistId,
                            $playlist->maxNumberOfItems
                        ));
                        break;
                    }
                }

                // Save the playlist
                $playlist->save();
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

        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'playlist-form-edit';
        $this->getState()->setData([
            'playlist' => $playlist
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
     *      name="logicalOperatorName",
     *      in="formData",
     *      description="When filtering by multiple names in name filter, which logical operator should be used? AND|OR",
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
     *  @SWG\Parameter(
     *      name="exactTags",
     *      in="formData",
     *      description="When filtering by Tags, should we use exact match?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="logicalOperator",
     *      in="formData",
     *      description="When filtering by Tags, which logical operator should be used? AND|OR",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="maxNumberOfItems",
     *      in="formData",
     *      description="Maximum number of items that can be assigned to this Playlist (dynamic Playlist only)",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
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
        $playlist->folderId = $sanitizedParams->getInt('folderId', ['default' => $playlist->folderId]);

        if ($playlist->hasPropertyChanged('folderId')) {
            if ($playlist->folderId === 1) {
                $this->checkRootFolderAllowSave();
            }
            $folder = $this->folderFactory->getById($playlist->folderId);
            $playlist->permissionsFolderId = $folder->getPermissionFolderIdOrThis();
        }

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            if (is_array($sanitizedParams->getParam('tags'))) {
                $tags = $this->tagFactory->tagsFromJson($sanitizedParams->getArray('tags'));
            } else {
                $tags = $this->tagFactory->tagsFromString($sanitizedParams->getString('tags'));
            }

            $playlist->updateTagLinks($tags);
        }

        // Do we have a tag or name filter?
        // Capture these as dynamic filter criteria
        if ($playlist->isDynamic === 1) {
            $filterMediaName = $sanitizedParams->getString('filterMediaName');
            $filterMediaTag = $sanitizedParams->getString('filterMediaTag');
            $filterFolderId = $sanitizedParams->getString('filterFolderId');

            if (empty($filterMediaName) && empty($filterMediaTag) && empty($filterFolderId)) {
                throw new InvalidArgumentException(__('No filters have been set for this dynamic Playlist, please click the Filters tab to define'));
            }
            $playlist->filterMediaName = $filterMediaName;
            $playlist->filterMediaNameLogicalOperator = $sanitizedParams->getString('logicalOperatorName');

            if ($this->getUser()->featureEnabled('tag.tagging')) {
                $playlist->filterMediaTags = $filterMediaTag;
                $playlist->filterExactTags = $sanitizedParams->getCheckbox('exactTags');
                $playlist->filterMediaTagsLogicalOperator = $sanitizedParams->getString('logicalOperator');
            }

            if ($this->getUser()->featureEnabled('folder.view')) {
                $playlist->filterFolderId = $filterFolderId;
            }

            $playlist->maxNumberOfItems = $sanitizedParams->getInt('maxNumberOfItems');
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
     *
     * @SWG\Delete(
     *  path="/playlist/{playlistId}",
     *  operationId="playlistDelete",
     *  tags={"playlist"},
     *  summary="Delete a Playlist",
     *  description="Delete a Playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The PlaylistId to delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
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
        $playlist->setModuleFactory($this->moduleFactory);
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
        $originalPlaylist = $this->playlistFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check Permissions
        if (!$this->getUser()->checkViewable($originalPlaylist)) {
            throw new AccessDeniedException();
        }

        // Load the playlist for Copy
        $originalPlaylist->load(['loadTags' => false]);

        // Clone the original
        $playlist = clone $originalPlaylist;

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

        // Set from global setting
        if ($playlist->enableStat == null) {
            $playlist->enableStat = $this->getConfig()->getSetting('PLAYLIST_STATS_ENABLED_DEFAULT');
        }

        // tags
        $playlist->updateTagLinks($originalPlaylist->tags);

        // Save the new playlist
        $playlist->save();

        // Clone the closure table for the original playlist
        $originalPlaylist->cloneClosureTable($playlist->getId());

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
        $this->getState()->template = 'playlist-form-timeline';
        $this->getState()->setData([
            'playlist' => $playlist,
        ]);

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

        if (empty($media)) {
            throw new InvalidArgumentException(__('Please provide Media to Assign'), 'media');
        }

        // Optional Duration
        $duration = ($sanitizedParams->getInt('duration'));

        // Optional displayOrder
        $displayOrder = $sanitizedParams->getInt('displayOrder');

        $newWidgets = [];

        // Loop through all the media
        foreach ($media as $mediaId) {
            $item = $this->mediaFactory->getById($mediaId);

            if (!$this->getUser()->checkViewable($item)) {
                throw new AccessDeniedException(__('You do not have permissions to use this media'));
            }

            if ($item->mediaType == 'genericfile' || $item->mediaType == 'font') {
                throw new InvalidArgumentException(sprintf(
                    __('You cannot assign file type %s to a playlist'),
                    $item->mediaType
                ), 'mediaType');
            }

            // Create a module
            $module = $this->moduleFactory->getByType($item->mediaType);

            // Determine the duration
            // if we have a duration provided, then use it, otherwise use the duration recorded on the
            // library item already
            $itemDuration = ($duration !== null) ? $duration : $item->duration;

            // If the library item duration (or provided duration) is 0, then default to the Module Default
            // Duration as configured in settings.
            $itemDuration = ($itemDuration == 0) ? $module->defaultDuration : $itemDuration;

            // Create a widget
            $widget = $this->widgetFactory->create($this->getUser()->userId, $id, $item->mediaType, $itemDuration, $module->schemaVersion);
            $widget->assignMedia($item->mediaId);

            // If a duration has been provided, then we want to use it, so set useDuration to 1.
            if ($duration !== null || $sanitizedParams->getCheckbox('useDuration') == 1) {
                $widget->useDuration = 1;
                $widget->duration = $itemDuration;
            }

            // Calculate the duration
            $widget->calculateDuration($module);

            // Assign the widget to the playlist
            $playlist->assignWidget($widget, $displayOrder);

            if ($playlist->isRegionPlaylist() && count($playlist->widgets) >= 2) {
                // Convert this region to a `playlist` (if it is a zone)
                $widgetRegion = $this->regionFactory->getById($playlist->regionId);
                if ($widgetRegion->type === 'zone') {
                    $widgetRegion->type = 'playlist';
                    $widgetRegion->save();
                }
            }

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

        // Add new widgets to playlist for return values
        $playlist->setUnmatchedProperty('newWidgets', $newWidgets);

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
    public function order(Request $request, Response $response, $id)
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
        $playlistFromDate = $sanitizedParams->getDate('playlistEventFromDate');
        $playlistToDate = $sanitizedParams->getDate('playlistEventToDate');

        // Events query array
        $eventsQuery = [
            'playlistId' => $id
        ];

        if ($playlistFromDate !== null) {
            $eventsQuery['futureSchedulesFrom'] = $playlistFromDate->format('U');
        }

        if ($playlistToDate !== null) {
            $eventsQuery['futureSchedulesTo'] = $playlistToDate->format('U');
        }

        // Query for events
        $events = $this->scheduleFactory->query(null, $eventsQuery);

        // Total records returned from the schedules query
        $totalRecords = $this->scheduleFactory->countLast();

        foreach ($events as $row) {
            /* @var \Xibo\Entity\Schedule $row */

            // Generate this event
            // Assess the date?
            if ($playlistFromDate !== null && $playlistToDate !== null) {
                try {
                    $scheduleEvents = $row->getEvents($playlistFromDate, $playlistToDate);
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
                    'external' => true,
                    'url' => '#',
                    'onclick' => 'createMiniLayoutPreview',
                    'onclickParam' => $this->urlFor($request, 'layout.preview', ['id' => $layout->layoutId]),
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
        ];

        $this->getState()->template = 'playlist-form-setenablestat';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }



    /**
     * Select Folder Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function selectFolderForm(Request $request, Response $response, $id)
    {
        // Get the Playlist
        $playlist = $this->playlistFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException();
        }

        $data = [
            'playlist' => $playlist
        ];

        $this->getState()->template = 'playlist-form-selectfolder';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Put(
     *  path="/playlist/{id}/selectfolder",
     *  operationId="playlistSelectFolder",
     *  tags={"playlist"},
     *  summary="Playlist Select folder",
     *  description="Select Folder for Playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The Playlist ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
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
    public function selectFolder(Request $request, Response $response, $id)
    {
        // Get the Layout
        $playlist = $this->playlistFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException();
        }

        $folderId = $this->getSanitizer($request->getParams())->getInt('folderId');
        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        $playlist->folderId = $folderId;
        $folder = $this->folderFactory->getById($playlist->folderId);
        $playlist->permissionsFolderId = $folder->getPermissionFolderIdOrThis();

        // Save
        $playlist->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Playlist %s moved to Folder %s'), $playlist->name, $folder->text)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Check if we already have a full screen Layout for this Playlist
     * @param \Xibo\Entity\Playlist $playlist
     * @return ?int
     */
    private function hasFullScreenLayout(\Xibo\Entity\Playlist $playlist): ?int
    {
        return $this->layoutFactory->getLinkedFullScreenLayout('playlist', $playlist->playlistId)?->campaignId;
    }

    /**
     * Convert Layout editor playlist to global playlist.
     * Assign this Playlist to the original regionPlaylist via sub-playlist Widget.
     * @SWG\Post(
     *   path="/playlist/{id}/convert",
     *   operationId="convert",
     *   tags={"playlist"},
     *   summary="Playlist Convert",
     *   description="Create a global playlist from inline editor Playlist.
     * Assign created Playlist via sub-playlist Widget to region Playlist.",
     *   @SWG\Parameter(
     *       name="playlistId",
     *       in="path",
     *       description="The Playlist ID",
     *       type="integer",
     *       required=true
     *    ),
     *   @SWG\Parameter(
     *       name="name",
     *       in="formData",
     *       description="Optional name for the global Playlist.",
     *       type="string",
     *       required=false
     *    ),
     *   @SWG\Response(
     *       response=201,
     *       description="successful operation"
     *   )
     *  )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function convert(Request $request, Response $response, $id): Response
    {
        $params = $this->getSanitizer($request->getParams());

        // get region playlist
        $regionPlaylist = $this->playlistFactory->getById($id);

        // check if it is region playlist
        if (!$regionPlaylist->isRegionPlaylist()) {
            throw new InvalidArgumentException(__('Not a Region Playlist'), 'playlistId');
        }

        // get the region
        $region = $this->regionFactory->getById($regionPlaylist->regionId);

        // make sure this is playlist type region
        if ($region->type !== 'playlist') {
            throw new InvalidArgumentException(__('Not a Playlist'), 'playlistId');
        }

        // get Layout
        $layout = $this->layoutFactory->getByRegionId($regionPlaylist->regionId);

        // check permissions
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException();
        }

        // check if it is a draft
        if (!$layout->isEditable()) {
            throw new InvalidArgumentException(
                __('This Layout is not a Draft, please checkout.'),
                'layoutId'
            );
        }

        $regionPlaylist->load();

        // clone region playlist to a new Playlist object
        $playlist = clone $regionPlaylist;
        $name = $params->getString(
            'name',
            ['default' => sprintf(__('Untitled %s'), Carbon::now()->format(DateFormatHelper::getSystemFormat()))]
        );

        $playlist->name = empty($playlist->name) ? $name : $playlist->name;
        $playlist->setOwner($this->getUser()->userId);

        if ($playlist->enableStat == null) {
            $playlist->enableStat = $this->getConfig()->getSetting('PLAYLIST_STATS_ENABLED_DEFAULT');
        }

        // Save the new playlist
        $playlist->save();
        $playlist->updateDuration();

        // Clone the closure table for the original playlist
        $regionPlaylist->cloneClosureTable($playlist->getId());

        // remove widgets on the region Playlist
        foreach ($regionPlaylist->widgets as $widget) {
            $widget->delete();
        }
        $regionPlaylist->widgets = [];

        $module = $this->moduleFactory->getByType('subplaylist');

        // create a new sub-playlist Widget
        $widget = $this->widgetFactory->create(
            $this->getUser()->userId,
            $regionPlaylist->playlistId,
            'subplaylist',
            $playlist->duration,
            $module->schemaVersion
        );

        // save, simulate add
        $widget->save();

        // prepare sub-playlist item
        $item = new SubPlaylistItem();
        $item->rowNo = 1;
        $item->playlistId = $playlist->playlistId;
        $item->spotFill = 'repeat';
        $item->spotLength =  '';
        $item->spots = '';

        $playlistItems[] = $item;

        // update Widget subPlaylists option
        $widget->setOptionValue('subPlaylists', 'attrib', json_encode($playlistItems));

        // Calculate the duration
        $widget->calculateDuration($module);

        // Assign the sub-playlist widget to the region playlist
        $regionPlaylist->assignWidget($widget);
        // Save the region playlist
        $regionPlaylist->save();

        // build Layout xlf
        $layout->xlfToDisk(['notify' => true, 'exceptionOnError' => true, 'exceptionOnEmptyRegion' => false]);

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Conversion Successful'),
        ]);

        return $this->render($request, $response);
    }
}
