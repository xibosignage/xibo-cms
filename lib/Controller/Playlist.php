<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Playlist.php)
 */


namespace Xibo\Controller;


use Xibo\Entity\Permission;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
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
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

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
     * @param SanitizerServiceInterface $sanitizerService
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
     * @param LayoutFactory $layoutFactory
     * @param DisplayFactory $displayFactory
     * @param ScheduleFactory $scheduleFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $playlistFactory, $regionFactory, $mediaFactory, $permissionFactory,
        $transitionFactory, $widgetFactory, $moduleFactory, $userGroupFactory, $userFactory, $tagFactory, $layoutFactory, $displayFactory, $scheduleFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

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
     */
    public function displayPage()
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
     * 
     */
    public function grid()
    {
        $this->getState()->template = 'grid';

        // Embed?
        $embed = ($this->getSanitizer()->getString('embed') != null) ? explode(',', $this->getSanitizer()->getString('embed')) : [];

        // Playlists
        $playlists = $this->playlistFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'name' => $this->getSanitizer()->getString('name'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'userId' => $this->getSanitizer()->getInt('userId'),
            'tags' => $this->getSanitizer()->getString('tags'),
            'exactTags' => $this->getSanitizer()->getCheckbox('exactTags'),
            'playlistId' => $this->getSanitizer()->getInt('playlistId'),
            'ownerUserGroupId' => $this->getSanitizer()->getInt('ownerUserGroupId'),
            'mediaLike' => $this->getSanitizer()->getString('mediaLike'),
            'regionSpecific' => $this->getSanitizer()->getInt('regionSpecific', 0)
        ]));

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

            if ($this->isApi())
                continue;

            $playlist->includeProperty('buttons');

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
                    $playlist->buttons[] = array(
                        'id' => 'playlist_timeline_button_edit',
                        'class' => 'XiboCustomFormButton',
                        'url' => $this->urlFor('playlist.timeline.form', ['id' => $playlist->playlistId]),
                        'text' => __('Timeline')
                    );

                    $playlist->buttons[] = ['divider' => true];
                }

                // Edit Button
                $playlist->buttons[] = array(
                    'id' => 'playlist_button_edit',
                    'url' => $this->urlFor('playlist.edit.form', ['id' => $playlist->playlistId]),
                    'text' => __('Edit')
                );

                // Copy Button
                $playlist->buttons[] = array(
                    'id' => 'playlist_button_copy',
                    'url' => $this->urlFor('playlist.copy.form', ['id' => $playlist->playlistId]),
                    'text' => __('Copy')
                );

                // Set Enable Stat
                $playlist->buttons[] = array(
                    'id' => 'playlist_button_setenablestat',
                    'url' => $this->urlFor('playlist.setenablestat.form', ['id' => $playlist->playlistId]),
                    'text' => __('Enable stats collection?'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('playlist.setenablestat', ['id' => $playlist->playlistId])),
                        array('name' => 'commit-method', 'value' => 'put'),
                        array('name' => 'id', 'value' => 'playlist_button_setenablestat'),
                        array('name' => 'text', 'value' => __('Enable stats collection?')),
                        array('name' => 'rowtitle', 'value' => $playlist->name),
                        ['name' => 'form-callback', 'value' => 'setEnableStatMultiSelectFormOpen']
                    )
                );

                $playlist->buttons[] = ['divider' => true];
            }

            // Extra buttons if have delete permissions
            if ($this->getUser()->checkDeleteable($playlist)) {
                // Delete Button
                $playlist->buttons[] = array(
                    'id' => 'playlist_button_delete',
                    'url' => $this->urlFor('playlist.delete.form', ['id' => $playlist->playlistId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('playlist.delete', ['id' => $playlist->playlistId])),
                        array('name' => 'commit-method', 'value' => 'delete'),
                        array('name' => 'id', 'value' => 'playlist_button_delete'),
                        array('name' => 'text', 'value' => __('Delete')),
                        array('name' => 'rowtitle', 'value' => $playlist->name)
                    )
                );

                $playlist->buttons[] = ['divider' => true];
            }

            // Extra buttons if we have modify permissions
            if ($this->getUser()->checkPermissionsModifyable($playlist)) {
                // Permissions button
                $playlist->buttons[] = array(
                    'id' => 'playlist_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'Playlist', 'id' => $playlist->playlistId]),
                    'text' => __('Permissions')
                );
            }

            $playlist->buttons[] = ['divider' => true];

            $playlist->buttons[] = array(
                'id' => 'usage_report_button',
                'url' => $this->urlFor('playlist.usage.form', ['id' => $playlist->playlistId]),
                'text' => __('Usage Report')
            );
        }

        $this->getState()->recordsTotal = $this->playlistFactory->countLast();
        $this->getState()->setData($playlists);
    }

    //<editor-fold desc="CRUD">

    /**
     * Add Form
     */
    public function addForm()
    {
        $this->getState()->template = 'playlist-form-add';
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
     * @throws XiboException
     */
    public function add()
    {
        if ($this->getSanitizer()->getString('name') == '') {
            throw new InvalidArgumentException(__('Please enter playlist name'), 'name');
        }

        $playlist = $this->playlistFactory->create($this->getSanitizer()->getString('name'), $this->getUser()->getId());
        $playlist->isDynamic = $this->getSanitizer()->getCheckbox('isDynamic');
        $playlist->enableStat = $this->getSanitizer()->getString('enableStat');

        $playlist->replaceTags($this->tagFactory->tagsFromString($this->getSanitizer()->getString('tags')));

        // Do we have a tag or name filter?
        $nameFilter = $this->getSanitizer()->getString('filterMediaName');
        $tagFilter = $this->getSanitizer()->getString('filterMediaTag');

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
    }

    /**
     * @param $playlistId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editForm($playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);
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

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        $this->getState()->template = 'playlist-form-edit';
        $this->getState()->setData([
            'playlist' => $playlist,
            'tags' => $tags
        ]);
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
     * @param int $playlistId
     * @throws XiboException
     */
    public function edit($playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        $playlist->name = $this->getSanitizer()->getString('name');
        $playlist->isDynamic = $this->getSanitizer()->getCheckbox('isDynamic');
        $playlist->enableStat = $this->getSanitizer()->getString('enableStat');

        $playlist->replaceTags($this->tagFactory->tagsFromString($this->getSanitizer()->getString('tags')));

        // Do we have a tag or name filter?
        // Capture these as dynamic filter criteria
        if ($playlist->isDynamic === 1) {
            $playlist->filterMediaName = $this->getSanitizer()->getString('filterMediaName');
            $playlist->filterMediaTags = $this->getSanitizer()->getString('filterMediaTag');
        }

        $playlist->save();

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $playlist->name),
            'id' => $playlist->playlistId,
            'data' => $playlist
        ]);
    }

    /**
     * @param $playlistId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function deleteForm($playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkDeleteable($playlist))
            throw new AccessDeniedException();

        $this->getState()->template = 'playlist-form-delete';
        $this->getState()->setData([
            'playlist' => $playlist
        ]);
    }

    /**
     * Delete
     * @param $playlistId
     * @throws \Xibo\Exception\XiboException
     */
    public function delete($playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkDeleteable($playlist))
            throw new AccessDeniedException();

        // Issue the delete
        $playlist->delete();

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $playlist->name)
        ]);
    }

    /**
     * Copy playlist form
     * @param int $playlistId
     * @throws NotFoundException
     */
    public function copyForm($playlistId)
    {
        // Get the playlist
        $playlist = $this->playlistFactory->getById($playlistId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($playlist))
            throw new AccessDeniedException();

        $this->getState()->template = 'playlist-form-copy';
        $this->getState()->setData([
            'playlist' => $playlist
        ]);
    }

    /**
     * Copies a playlist
     * @param int $playlistId
     *
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
     * @throws XiboException
     */
    public function copy($playlistId)
    {
        // Get the playlist
        $originalPlaylist = $this->playlistFactory->getById($playlistId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($originalPlaylist))
            throw new AccessDeniedException();

        // Load the playlist for Copy
        $originalPlaylist->load(['loadTags' => false]);

        // Clone the original
        $playlist = clone $originalPlaylist;

        $playlist->name = $this->getSanitizer()->getString('name');
        $playlist->setOwner($this->getUser()->userId);

        // Copy the media on the playlist and change the assignments.
        if ($this->getSanitizer()->getCheckbox('copyMediaFiles') == 1) {
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

        // Clone the closure table for the original playlist
        $originalPlaylist->cloneClosureTable($playlist->getId());

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Copied as %s'), $playlist->name),
            'id' => $playlist->playlistId,
            'data' => $playlist
        ]);
    }
    //</editor-fold>

    /**
     * Timeline Form
     * @param int $playlistId
     * @throws XiboException
     */
    public function timelineForm($playlistId)
    {
        // Get a complex object of playlists and widgets
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // Pass to view
        $this->getState()->template = 'region-form-timeline';
        $this->getState()->setData([
            'playlist' => $playlist,
            'help' => $this->getHelp()->link('Layout', 'RegionOptions')
        ]);
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
     */
    public function widgetGrid()
    {
        $this->getState()->template = 'grid';

        $widgets = $this->widgetFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'playlistId' => $this->getSanitizer()->getInt('playlistId'),
            'widgetId' => $this->getSanitizer()->getInt('widgetId')
        ]));

        foreach ($widgets as $widget) {
            /* @var Widget $widget */
            $widget->load();

            $widget->module = $this->moduleFactory->createWithWidget($widget);

            // Add property for name
            $widget->name = $widget->module->getName();

            // Add property for transition
            $widget->transition = sprintf('%s / %s', $widget->module->getTransition('in'), $widget->module->getTransition('out'));

            if ($this->isApi()) {
                $widget->createdDt = $this->getDate()->getLocalDate($widget->createdDt);
                $widget->modifiedDt = $this->getDate()->getLocalDate($widget->modifiedDt);
                $widget->fromDt = $this->getDate()->getLocalDate($widget->fromDt);
                $widget->toDt = $this->getDate()->getLocalDate($widget->toDt);
            }
        }

        // Store the table rows
        $this->getState()->recordsTotal = $this->widgetFactory->countLast();
        $this->getState()->setData($widgets);
    }

    /**
     * Add Library items to a Playlist
     * @param int $playlistId
     *
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
     *
     * @throws XiboException
     */
    public function libraryAssign($playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

        if ($playlist->isDynamic === 1)
            throw new InvalidArgumentException(__('This Playlist is dynamically managed so cannot accept manual assignments.'), 'isDynamic');

        // Expect a list of mediaIds
        $media = $this->getSanitizer()->getIntArray('media');

        if (count($media) <= 0)
            throw new InvalidArgumentException(__('Please provide Media to Assign'), 'media');

        // Optional Duration
        $duration = ($this->getSanitizer()->getInt('duration'));

        // Optional displayOrder
        $displayOrder = $this->getSanitizer()->getInt('displayOrder');

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
            $widget = $this->widgetFactory->create($this->getUser()->userId, $playlistId, $item->mediaType, $itemDuration);
            $widget->assignMedia($item->mediaId);

            // Assign the widget to the module
            $module->setWidget($widget);

            // Set default options (this sets options on the widget)
            $module->setDefaultWidgetOptions();

            // If a duration has been provided, then we want to use it, so set useDuration to 1.
            if ($duration !== null || $this->getSanitizer()->getCheckbox('useDuration') == 1) {
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
     * @param int $playlistId
     *
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
     *
     * @throws XiboException
     */
    function order($playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

        // Load the widgets
        $playlist->load();

        // Get our list of widget orders
        $widgets = $this->getSanitizer()->getParam('widgets', null);

        if ($widgets == null)
            throw new InvalidArgumentException(__('Cannot Save empty region playlist. Please add widgets'), 'widgets');

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
    }

    /**
     * Playlist Usage Report Form
     * @param int $playlistId
     */
    public function usageForm($playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkViewable($playlist))
            throw new AccessDeniedException();

        $this->getState()->template = 'playlist-form-usage';
        $this->getState()->setData([
            'playlist' => $playlist
        ]);
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
     * @param int $playlistId
     * @throws NotFoundException
     */
    public function usage($playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkViewable($playlist))
            throw new AccessDeniedException();

        // Get a list of displays that this playlistId is used on
        $displays = [];
        $displayIds = [];

        // have we been provided with a date/time to restrict the scheduled events to?
        $playlistDate = $this->getSanitizer()->getDate('playlistEventDate');

        if ($playlistDate !== null) {
            // Get a list of scheduled events that this playlistId is used on, based on the date provided
            $toDate = $playlistDate->copy()->addDay();

            $events = $this->scheduleFactory->query(null, [
                'futureSchedulesFrom' => $playlistDate->format('U'),
                'futureSchedulesTo' => $toDate->format('U'),
                'playlistId' => $playlistId
            ]);
        } else {
            // All scheduled events for this playlistId
            $events = $this->scheduleFactory->query(null, [
                'playlistId' => $playlistId
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
                } catch (XiboException $e) {
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

        if ($this->isApi() && $displays == []) {
            $displays = [
                'data' =>__('Specified Playlist item is not in use.')];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $totalRecords;
        $this->getState()->setData($displays);
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
     * @param int $playlistId
     * @throws NotFoundException
     */
    public function usageLayouts($playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkViewable($playlist))
            throw new AccessDeniedException();

        $layouts = $this->layoutFactory->query(null, ['playlistId' => $playlistId]);

        if (!$this->isApi()) {
            foreach ($layouts as $layout) {
                $layout->includeProperty('buttons');

                // Add some buttons for this row
                if ($this->getUser()->checkEditable($layout)) {
                    // Design Button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_design',
                        'linkType' => '_self', 'external' => true,
                        'url' => $this->urlFor('layout.designer', array('id' => $layout->layoutId)),
                        'text' => __('Design')
                    );
                }

                // Preview
                $layout->buttons[] = array(
                    'id' => 'layout_button_preview',
                    'linkType' => '_blank',
                    'external' => true,
                    'url' => $this->urlFor('layout.preview', ['id' => $layout->layoutId]),
                    'text' => __('Preview Layout')
                );
            }
        }

        if ($this->isApi() && $layouts == []) {
            $layouts = [
                'data' =>__('Specified Playlist item is not in use.')
            ];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->layoutFactory->countLast();
        $this->getState()->setData($layouts);
    }

    /**
     * Set Enable Stats Collection of a Playlist
     * @param int $playlistId
     *
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
     *
     * @throws XiboException
     */

    function setEnableStat($playlistId)
    {
        // Get the Playlist
        $playlist = $this->playlistFactory->getById($playlistId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($playlist))
            throw new AccessDeniedException();

        $enableStat = $this->getSanitizer()->getString('enableStat');

        $playlist->enableStat = $enableStat;
        $playlist->save(['saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('For Playlist %s Enable Stats Collection is set to %s'), $playlist->name, __($playlist->enableStat))
        ]);
    }

    /**
     * Set Enable Stat Form
     * @param int $playlistId
     * @throws XiboException
     */
    public function setEnableStatForm($playlistId)
    {
        // Get the Playlist
        $playlist = $this->playlistFactory->getById($playlistId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($playlist))
            throw new AccessDeniedException();

        $data = [
            'playlist' => $playlist,
            'help' => $this->getHelp()->link('Playlist', 'EnableStat')
        ];

        $this->getState()->template = 'playlist-form-setenablestat';
        $this->getState()->setData($data);
    }
}