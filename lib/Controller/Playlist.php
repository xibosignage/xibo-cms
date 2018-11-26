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
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
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
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $playlistFactory, $regionFactory, $mediaFactory, $permissionFactory,
        $transitionFactory, $widgetFactory, $moduleFactory, $userGroupFactory, $userFactory, $tagFactory)
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
     * Search
     */
    public function grid()
    {
        $this->getState()->template = 'grid';

        // Playlists
        $playlists = $this->playlistFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'name' => $this->getSanitizer()->getString('name'),
            'userId' => $this->getSanitizer()->getInt('userId'),
            'tags' => $this->getSanitizer()->getString('tags'),
            'exactTags' => $this->getSanitizer()->getCheckbox('exactTags'),
            'playlistId' => $this->getSanitizer()->getInt('playlistId'),
            'ownerUserGroupId' => $this->getSanitizer()->getInt('ownerUserGroupId'),
            'mediaLike' => $this->getSanitizer()->getString('mediaLike'),
            'regionSpecific' => 0
        ]));

        foreach ($playlists as $playlist) {

            if ($this->isApi())
                continue;

            $playlist->includeProperty('buttons');
            $playlist->includeProperty('requiresDurationUpdate');

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
        $playlist = $this->playlistFactory->create($this->getSanitizer()->getString('name'), $this->getUser()->getId());
        $playlist->isDynamic = $this->getSanitizer()->getCheckbox('isDynamic');
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
        if ($playlist->isDynamic === 0 && (!empty($nameFilter) || !empty($tagFilter))) {
            $media = $this->mediaFactory->query(null, ['name' => $nameFilter, 'tags' => $tagFilter]);

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

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        $this->getState()->template = 'playlist-form-edit';
        $this->getState()->setData([
            'playlist' => $playlist
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
        $playlist = $this->playlistFactory->getById($playlistId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($playlist))
            throw new AccessDeniedException();

        // Load the playlist for Copy
        $playlist->load();
        $playlist = clone $playlist;

        $playlist->name = $this->getSanitizer()->getString('name');

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

        $playlist->load();

        foreach ($playlist->widgets as $widget) {

            /* @var Widget $widget */
            $widget->module = $this->moduleFactory->createWithWidget($widget);
        }

        // Pass to view
        $this->getState()->template = 'region-form-timeline';
        $this->getState()->setData([
            'region' => ['regionId' => -1],
            'playlist' => $playlist,
            'modules' => $this->moduleFactory->getAssignableModules(),
            'transitions' => $this->transitionData(),
            'help' => $this->getHelp()->link('Layout', 'RegionOptions')
        ]);
    }

    /**
     * Widget Grid
     *
     * @SWG\Get(
     *  path="/playlist/widget",
     *  operationId="playlistSearch",
     *  tags={"playlist"},
     *  summary="Playlist Widget Search",
     *  description="Search widgets on a Playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="formData",
     *      description="The Playlist ID to Search",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="formData",
     *      description="The widget ID to Search",
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
     */
    public function widgetGrid()
    {
        $this->getState()->template = 'grid';

        // Transitions
        $transIn = $this->transitionFactory->getEnabledByType('in');
        $transOut = $this->transitionFactory->getEnabledByType('out');
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

            if ($this->isApi())
                continue;

            $widget->includeProperty('buttons');

            if ($this->getUser()->checkEditable($widget)) {
                $widget->buttons[] = array(
                    'id' => 'timeline_button_edit',
                    'url' => $this->urlFor('module.widget.edit.form', ['id' => $widget->widgetId]),
                    'text' => __('Edit')
                );
            }

            if ($this->getUser()->checkDeleteable($widget)) {
                $widget->buttons[] = array(
                    'id' => 'timeline_button_delete',
                    'url' => $this->urlFor('module.widget.delete.form', ['id' => $widget->widgetId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('module.widget.delete', ['id' => $widget->widgetId])),
                        array('name' => 'commit-method', 'value' => 'delete'),
                        array('name' => 'id', 'value' => 'timeline_button_delete'),
                        array('name' => 'text', 'value' => __('Delete')),
                        array('name' => 'rowtitle', 'value' => $widget->module->getName()),
                        array('name' => 'options', 'value' => 'unassign')
                    )
                );
            }

            if ($this->getUser()->checkPermissionsModifyable($widget)) {
                $widget->buttons[] = array(
                    'id' => 'timeline_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'Widget', 'id' => $widget->widgetId]),
                    'text' => __('Permissions')
                );
            }

            if (count($transIn) > 0) {
                $widget->buttons[] = array(
                    'id' => 'timeline_button_trans_in',
                    'url' => $this->urlFor('module.widget.transition.edit.form', ['id' => $widget->widgetId, 'type' => 'in']),
                    'text' => __('In Transition')
                );
            }

            if (count($transOut) > 0) {
                $widget->buttons[] = array(
                    'id' => 'timeline_button_trans_in',
                    'url' => $this->urlFor('module.widget.transition.edit.form', ['id' => $widget->widgetId, 'type' => 'out']),
                    'text' => __('Out Transition')
                );
            }
        }

        // Store the table rows
        $this->getState()->recordsTotal = $this->widgetFactory->countLast();
        $this->getState()->setData($widgets);
    }

    /**
     * Form for assigning Library Items to a Playlist
     * @param int $playlistId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function libraryAssignForm($playlistId)
    {
        $playlist = $this->playlistFactory->getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        $this->getState()->template = 'playlist-form-library-assign';
        $this->getState()->setData([
            'playlist' => $playlist,
            'modules' => $this->moduleFactory->query(null, ['regionSpecific' => 0, 'enabled' => 1, 'assignable' => 1]),
            'help' => $this->getHelp()->link('Library', 'Assign')
        ]);
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
            throw new \InvalidArgumentException(__('Please provide Media to Assign'));

        // Optional Duration
        $duration = ($this->getSanitizer()->getInt('duration'));

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
            if ($duration !== null || $this->getSanitizer()->getCheckbox('useDuration') == 1)
                $widget->useDuration = 1;

            // Calculate the duration
            $widget->calculateDuration($module);

            // Assign the widget to the playlist
            $playlist->assignWidget($widget);

            // Add to a list of new widgets
            $newWidgets[] = $widget;
        }

        // Save the playlist
        $playlist->save();

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

        $playlist->save();

        // Success
        $this->getState()->hydrate([
            'message' => __('Order Changed'),
            'data' => $playlist
        ]);
    }

    /**
     * @return array
     */
    private function transitionData()
    {
        return [
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
        ];
    }
}