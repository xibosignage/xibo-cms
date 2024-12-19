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
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Img;
use Psr\Http\Message\ResponseInterface;
use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Connector\ProviderDetails;
use Xibo\Connector\ProviderImport;
use Xibo\Entity\Media;
use Xibo\Entity\SearchResult;
use Xibo\Entity\SearchResults;
use Xibo\Event\LibraryProviderEvent;
use Xibo\Event\LibraryProviderImportEvent;
use Xibo\Event\LibraryProviderListEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\MediaFullLoadEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Environment;
use Xibo\Helper\XiboUploadHandler;
use Xibo\Service\MediaService;
use Xibo\Service\MediaServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\LibraryFullException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\Render\WidgetDownloader;

/**
 * Class Library
 * @package Xibo\Controller
 */
class Library extends Base
{
    /** @var EventDispatcherInterface */
    private $dispatcher;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var ScheduleFactory  */
    private $scheduleFactory;

    /** @var FolderFactory */
    private $folderFactory;
    /**
     * @var MediaServiceInterface
     */
    private $mediaService;

    /**
     * Set common dependencies.
     * @param UserFactory $userFactory
     * @param ModuleFactory $moduleFactory
     * @param TagFactory $tagFactory
     * @param MediaFactory $mediaFactory
     * @param WidgetFactory $widgetFactory
     * @param PermissionFactory $permissionFactory
     * @param LayoutFactory $layoutFactory
     * @param PlaylistFactory $playlistFactory
     * @param UserGroupFactory $userGroupFactory
     * @param DisplayFactory $displayFactory
     * @param ScheduleFactory $scheduleFactory
     * @param FolderFactory $folderFactory
     */
    public function __construct(
        $userFactory,
        $moduleFactory,
        $tagFactory,
        $mediaFactory,
        $widgetFactory,
        $permissionFactory,
        $layoutFactory,
        $playlistFactory,
        $userGroupFactory,
        $displayFactory,
        $scheduleFactory,
        $folderFactory
    ) {
        $this->moduleFactory = $moduleFactory;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->userFactory = $userFactory;
        $this->tagFactory = $tagFactory;
        $this->permissionFactory = $permissionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->playlistFactory = $playlistFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->displayFactory = $displayFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->folderFactory = $folderFactory;
    }

    /**
     * Get Module Factory
     * @return ModuleFactory
     */
    public function getModuleFactory()
    {
        return $this->moduleFactory;
    }

    /**
     * Get Media Factory
     * @return MediaFactory
     */
    public function getMediaFactory()
    {
        return $this->mediaFactory;
    }

    /**
     * Get Permission Factory
     * @return PermissionFactory
     */
    public function getPermissionFactory()
    {
        return $this->permissionFactory;
    }

    /**
     * Get Widget Factory
     * @return WidgetFactory
     */
    public function getWidgetFactory()
    {
        return $this->widgetFactory;
    }

    /**
     * Get Layout Factory
     * @return LayoutFactory
     */
    public function getLayoutFactory()
    {
        return $this->layoutFactory;
    }

    /**
     * Get Playlist Factory
     * @return PlaylistFactory
     */
    public function getPlaylistFactory()
    {
        return $this->playlistFactory;
    }

    /**
     * @return TagFactory
     */
    public function getTagFactory()
    {
        return $this->tagFactory;
    }

    /**
     * @return FolderFactory
     */
    public function getFolderFactory()
    {
        return $this->folderFactory;
    }

    public function useMediaService(MediaServiceInterface $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function getMediaService()
    {
        return $this->mediaService->setUser($this->getUser());
    }

    /**
     * Displays the page logic
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());
        $mediaId = $sanitizedParams->getInt('mediaId');

        if ($mediaId !== null) {
            $media = $this->mediaFactory->getById($mediaId);
            if (!$this->getUser()->checkViewable($media)) {
                throw new AccessDeniedException();
            }

            // Thumbnail
            $module = $this->moduleFactory->getByType($media->mediaType);
            $media->setUnmatchedProperty('thumbnail', '');
            if ($module->hasThumbnail) {
                $media->setUnmatchedProperty(
                    'thumbnail',
                    $this->urlFor($request, 'library.download', [
                        'id' => $media->mediaId
                    ], [
                        'preview' => 1
                    ])
                );
            }
            $media->setUnmatchedProperty('fileSizeFormatted', ByteFormatter::format($media->fileSize));

            $this->getState()->template = 'library-direct-media-details';
            $this->getState()->setData([
                'media' => $media
            ]);
        } else {
            // Users we have permission to see
            $this->getState()->template = 'library-page';
            $this->getState()->setData([
                'modules' => $this->moduleFactory->getLibraryModules(),
                'validExt' => implode('|', $this->moduleFactory->getValidExtensions([]))
            ]);
        }

        return $this->render($request, $response);
    }

    /**
     * Set Enable Stats Collection of a media
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Put(
     *  path="/library/setenablestat/{mediaId}",
     *  operationId="mediaSetEnableStat",
     *  tags={"library"},
     *  summary="Enable Stats Collection",
     *  description="Set Enable Stats Collection? to use for the collection of Proof of Play statistics for a media.",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option to enable the collection of Media Proof of Play statistics",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function setEnableStat(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        $enableStat = $this->getSanitizer($request->getParams())->getString('enableStat');

        $media->enableStat = $enableStat;
        $media->save(['saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('For Media %s Enable Stats Collection is set to %s'), $media->name, __($media->enableStat))
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
        // Get the Media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        $data = [
            'media' => $media,
        ];

        $this->getState()->template = 'library-form-setenablestat';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Prints out a Table of all media items
     *
     * @SWG\Get(
     *  path="/library",
     *  operationId="librarySearch",
     *  tags={"library"},
     *  summary="Library Search",
     *  description="Search the Library for this user",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="query",
     *      description="Filter by Media Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="media",
     *      in="query",
     *      description="Filter by Media Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="query",
     *      description="Filter by Media Type",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerId",
     *      in="query",
     *      description="Filter by Owner Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="query",
     *      description="Filter by Retired",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="query",
     *      description="Filter by Tags - comma seperated",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
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
     *      name="duration",
     *      in="query",
     *      description="Filter by Duration - a number or less-than,greater-than,less-than-equal or great-than-equal followed by a | followed by a number",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="fileSize",
     *      in="query",
     *      description="Filter by File Size - a number or less-than,greater-than,less-than-equal or great-than-equal followed by a | followed by a number",
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
     *          @SWG\Items(ref="#/definitions/Media")
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
        $user = $this->getUser();

        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        // Construct the SQL
        $mediaList = $this->mediaFactory->query($this->gridRenderSort($parsedQueryParams), $this->gridRenderFilter([
            'mediaId' => $parsedQueryParams->getInt('mediaId'),
            'name' => $parsedQueryParams->getString('media'),
            'useRegexForName' => $parsedQueryParams->getCheckbox('useRegexForName'),
            'nameExact' => $parsedQueryParams->getString('nameExact'),
            'type' => $parsedQueryParams->getString('type'),
            'types' => $parsedQueryParams->getArray('types'),
            'tags' => $parsedQueryParams->getString('tags'),
            'exactTags' => $parsedQueryParams->getCheckbox('exactTags'),
            'ownerId' => $parsedQueryParams->getInt('ownerId'),
            'retired' => $parsedQueryParams->getInt('retired'),
            'duration' => $parsedQueryParams->getInt('duration'),
            'fileSize' => $parsedQueryParams->getString('fileSize'),
            'ownerUserGroupId' => $parsedQueryParams->getInt('ownerUserGroupId'),
            'assignable' => $parsedQueryParams->getInt('assignable'),
            'folderId' => $parsedQueryParams->getInt('folderId'),
            'onlyMenuBoardAllowed' => $parsedQueryParams->getInt('onlyMenuBoardAllowed'),
            'layoutId' => $parsedQueryParams->getInt('layoutId'),
            'includeLayoutBackgroundImage' => ($parsedQueryParams->getInt('layoutId') != null) ? 1 : 0,
            'orientation' => $parsedQueryParams->getString('orientation', ['defaultOnEmptyString' => true]),
            'logicalOperator' => $parsedQueryParams->getString('logicalOperator'),
            'logicalOperatorName' => $parsedQueryParams->getString('logicalOperatorName'),
            'unreleasedOnly' => $parsedQueryParams->getCheckbox('unreleasedOnly'),
            'unusedOnly' => $parsedQueryParams->getCheckbox('unusedOnly'),
        ], $parsedQueryParams));

        // Add some additional row content
        foreach ($mediaList as $media) {
            $media->setUnmatchedProperty('revised', ($media->parentId != 0) ? 1 : 0);

            // Thumbnail
            $media->setUnmatchedProperty('thumbnail', '');
            try {
                $module = $this->moduleFactory->getByType($media->mediaType);
                if ($module->hasThumbnail) {
                    $renderThumbnail = true;
                    // for video, check if the cover image exists here.
                    if ($media->mediaType === 'video') {
                        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');
                        $renderThumbnail = file_exists($libraryLocation . $media->mediaId . '_videocover.png');
                    }
                    
                    if ($renderThumbnail) {
                        $media->setUnmatchedProperty(
                            'thumbnail',
                            $this->urlFor($request, 'library.download', [
                                'id' => $media->mediaId
                            ], [
                                'preview' => 1
                            ])
                        );
                    }
                }
            } catch (NotFoundException $notFoundException) {
                $this->getLog()->error('Module ' . $media->mediaType . ' not found');
            }

            $media->setUnmatchedProperty('fileSizeFormatted', ByteFormatter::format($media->fileSize));

            // Media expiry
            $media->setUnmatchedProperty('mediaExpiresIn', __('Expires %s'));
            $media->setUnmatchedProperty('mediaExpiryFailed', __('Expired '));
            $media->setUnmatchedProperty('mediaNoExpiryDate', __('Never'));

            if ($this->isApi($request)) {
                $media->excludeProperty('mediaExpiresIn');
                $media->excludeProperty('mediaExpiryFailed');
                $media->excludeProperty('mediaNoExpiryDate');
                $media->expires = ($media->expires == 0)
                    ? 0
                    : Carbon::createFromTimestamp($media->expires)->format(DateFormatHelper::getSystemFormat());
                continue;
            }

            $media->includeProperty('buttons');

            switch ($media->released) {
                case 1:
                    $media->setUnmatchedProperty('releasedDescription', '');
                    break;

                case 2:
                    $media->setUnmatchedProperty(
                        'releasedDescription',
                        __('The uploaded image is too large and cannot be processed, please use another image.')
                    );
                    break;

                default:
                    $media->setUnmatchedProperty(
                        'releasedDescription',
                        __('This image will be resized according to set thresholds and limits.')
                    );
            }

            switch ($media->enableStat) {
                case 'On':
                    $media->setUnmatchedProperty(
                        'enableStatDescription',
                        __('This Media has enable stat collection set to ON')
                    );
                    break;

                case 'Off':
                    $media->setUnmatchedProperty(
                        'enableStatDescription',
                        __('This Media has enable stat collection set to OFF')
                    );
                    break;

                default:
                    $media->setUnmatchedProperty(
                        'enableStatDescription',
                        __('This Media has enable stat collection set to INHERIT')
                    );
            }

            if ($parsedQueryParams->getCheckbox('fullScreenScheduleCheck')) {
                $fullScreenCampaignId = $this->hasFullScreenLayout($media);
                $media->setUnmatchedProperty('hasFullScreenLayout', (!empty($fullScreenCampaignId)));
                $media->setUnmatchedProperty('fullScreenCampaignId', $fullScreenCampaignId);
            }

            $media->buttons = [];

            // Buttons
            if ($this->getUser()->featureEnabled('library.modify')
                && $user->checkEditable($media)
            ) {
                // Edit
                $media->buttons[] = array(
                    'id' => 'content_button_edit',
                    'url' => $this->urlFor($request, 'library.edit.form', ['id' => $media->mediaId]),
                    'text' => __('Edit')
                );

                // Copy Button
                $media->buttons[] = array(
                    'id' => 'media_button_copy',
                    'url' => $this->urlFor($request, 'library.copy.form', ['id' => $media->mediaId]),
                    'text' => __('Copy')
                );

                // Select Folder
                if ($this->getUser()->featureEnabled('folder.view')) {
                    $media->buttons[] = [
                        'id' => 'library_button_selectfolder',
                        'url' => $this->urlFor($request, 'library.selectfolder.form', ['id' => $media->mediaId]),
                        'text' => __('Select Folder'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url', 'value' => $this->urlFor($request, 'library.selectfolder', [
                                    'id' => $media->mediaId
                                ])
                            ],
                            ['name' => 'commit-method', 'value' => 'put'],
                            ['name' => 'id', 'value' => 'library_button_selectfolder'],
                            ['name' => 'text', 'value' => __('Move to Folder')],
                            ['name' => 'rowtitle', 'value' => $media->name],
                            ['name' => 'form-callback', 'value' => 'moveFolderMultiSelectFormOpen']
                        ]
                    ];
                }
            }

            if ($this->getUser()->featureEnabled('library.modify')
                && $user->checkDeleteable($media)
            ) {
                // Delete Button
                $media->buttons[] = [
                    'id' => 'content_button_delete',
                    'url' => $this->urlFor($request,'library.delete.form', ['id' => $media->mediaId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'library.delete', ['id' => $media->mediaId])],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'content_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $media->name],
                        ['name' => 'form-callback', 'value' => 'setDefaultMultiSelectFormOpen']
                    ]
                ];
            }

            if ($this->getUser()->featureEnabled('library.modify')
                && $user->checkPermissionsModifyable($media)
            ) {
                // Permissions
                $media->buttons[] = [
                    'id' => 'content_button_permissions',
                    'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'Media', 'id' => $media->mediaId]),
                    'text' => __('Share'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'user.permissions.multi', ['entity' => 'Media', 'id' => $media->mediaId])],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'content_button_permissions'],
                        ['name' => 'text', 'value' => __('Share')],
                        ['name' => 'rowtitle', 'value' => $media->name],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        ['name' => 'custom-handler-url', 'value' => $this->urlFor($request,'user.permissions.multi.form', ['entity' => 'Media'])],
                        ['name' => 'content-id-name', 'value' => 'mediaId']
                    ]
                ];
            }

            // Download
            // No feature permissions here, anyone can get a file based on sharing.
            $media->buttons[] = ['divider' => true];
            $media->buttons[] = array(
                'id' => 'content_button_download',
                'linkType' => '_self', 'external' => true,
                'url' => $this->urlFor($request, 'library.download', ['id' => $media->mediaId]) . '?attachment=' . urlencode($media->fileName),
                'text' => __('Download')
            );

            // Set Enable Stat
            if ($this->getUser()->featureEnabled('library.modify')
                && $this->getUser()->checkEditable($media)
            ) {
                $media->buttons[] = ['divider' => true];

                $media->buttons[] = array(
                    'id' => 'library_button_setenablestat',
                    'url' => $this->urlFor($request,'library.setenablestat.form', ['id' => $media->mediaId]),
                    'text' => __('Enable stats collection?'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor($request,'library.setenablestat', ['id' => $media->mediaId])),
                        array('name' => 'commit-method', 'value' => 'put'),
                        array('name' => 'id', 'value' => 'library_button_setenablestat'),
                        array('name' => 'text', 'value' => __('Enable stats collection?')),
                        array('name' => 'rowtitle', 'value' => $media->name),
                        ['name' => 'form-callback', 'value' => 'setEnableStatMultiSelectFormOpen']
                    )
                );
            }

            if ($this->getUser()->featureEnabled(['schedule.view', 'layout.view'])) {
                $media->buttons[] = ['divider' => true];

                $media->buttons[] = array(
                    'id' => 'usage_report_button',
                    'url' => $this->urlFor($request, 'library.usage.form', ['id' => $media->mediaId]),
                    'text' => __('Usage Report')
                );
            }

            // Schedule
            if ($this->getUser()->featureEnabled('schedule.add')
                && in_array($media->mediaType, ['image', 'video'])
                && ($this->getUser()->checkEditable($media)
                    || $this->getConfig()->getSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 1)
            ) {
                $media->buttons[] = [
                    'id' => 'library_button_schedule',
                    'url' => $this->urlFor(
                        $request,
                        'schedule.add.form',
                        ['id' => $media->mediaId, 'from' => 'Library']
                    ),
                    'text' => __('Schedule')
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->mediaFactory->countLast();
        $this->getState()->setData($mediaList);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/library/search",
     *  operationId="librarySearchAll",
     *  tags={"library"},
     *  summary="Library Search All",
     *  description="Search all library files from local and connectors",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/SearchResult")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function search(Request $request, Response $response): Response
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());
        $provider = $parsedQueryParams->getString('provider', ['default' => 'local']);

        $searchResults = new SearchResults();
        if ($provider === 'local') {
            // Sorting options.
            // only allow from a preset list
            $sortCol = match ($parsedQueryParams->getString('sortCol')) {
                'mediaId' => '`media`.`mediaId`',
                'orientation' => '`media`.`orientation`',
                'width' => '`media`.`width`',
                'height' => '`media`.`height`',
                'duration' => '`media`.`duration`',
                'fileSize' => '`media`.`fileSize`',
                'createdDt' => '`media`.`createdDt`',
                'modifiedDt' => '`media`.`modifiedDt`',
                default => '`media`.`name`',
            };
            $sortDir = match ($parsedQueryParams->getString('sortDir')) {
                'DESC' => ' DESC',
                default => ' ASC'
            };

            $mediaList = $this->mediaFactory->query([$sortCol . $sortDir], $this->gridRenderFilter([
                'name' => $parsedQueryParams->getString('media'),
                'useRegexForName' => $parsedQueryParams->getCheckbox('useRegexForName'),
                'nameExact' => $parsedQueryParams->getString('nameExact'),
                'type' => $parsedQueryParams->getString('type'),
                'types' => $parsedQueryParams->getArray('types'),
                'tags' => $parsedQueryParams->getString('tags'),
                'exactTags' => $parsedQueryParams->getCheckbox('exactTags'),
                'ownerId' => $parsedQueryParams->getInt('ownerId'),
                'folderId' => $parsedQueryParams->getInt('folderId'),
                'assignable' => 1,
                'retired' => 0,
                'orientation' => $parsedQueryParams->getString('orientation', ['defaultOnEmptyString' => true])
            ], $parsedQueryParams));

            // Add some additional row content
            foreach ($mediaList as $media) {
                $searchResult = new SearchResult();
                $searchResult->id = $media->mediaId;
                $searchResult->source = 'local';
                $searchResult->type = $media->mediaType;
                $searchResult->title = $media->name;
                $searchResult->width = $media->width;
                $searchResult->height = $media->height;
                $searchResult->description = '';
                $searchResult->duration = $media->duration;

                // Thumbnail
                $module = $this->moduleFactory->getByType($media->mediaType);
                if ($module->hasThumbnail) {
                    $searchResult->thumbnail = $this->urlFor($request, 'library.download', [
                            'id' => $media->mediaId
                        ], [
                            'preview' => 1,
                            'isThumb' => 1
                        ]);
                }

                // Add the result
                $searchResults->data[] = $searchResult;
            }
        } else {
            $this->getLog()->debug('Dispatching event, for provider ' . $provider);

            // Do we have a type filter
            $types = $parsedQueryParams->getArray('types');
            $type = $parsedQueryParams->getString('type');
            if ($type !== null) {
                $types[] = $type;
            }

            // Hand off to any other providers that may want to provide results.
            $event = new LibraryProviderEvent(
                $searchResults,
                $parsedQueryParams->getInt('start', ['default' => 0]),
                $parsedQueryParams->getInt('length', ['default' => 10]),
                $parsedQueryParams->getString('media'),
                $types,
                $parsedQueryParams->getString('orientation'),
                $provider
            );

            try {
                $this->getDispatcher()->dispatch($event, $event->getName());
            } catch (\Exception $exception) {
                $this->getLog()->error('Library search: Exception in dispatched event: ' . $exception->getMessage());
                $this->getLog()->debug($exception->getTraceAsString());
            }
        }

        return $response->withJson($searchResults);
    }

    /**
     * Get list of Library providers with their details.
     *
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     */
    public function providersList(Request $request, Response $response): Response|\Psr\Http\Message\ResponseInterface
    {
        $event = new LibraryProviderListEvent();
        $this->getDispatcher()->dispatch($event, $event->getName());

        $providers = $event->getProviders();

        return $response->withJson($providers);
    }

    /**
     * Media Delete Form
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
        $media = $this->mediaFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($media)) {
            throw new AccessDeniedException();
        }

        $this->getDispatcher()->dispatch(MediaFullLoadEvent::$NAME, new MediaFullLoadEvent($media));
        $media->load(['deleting' => true]);

        $this->getState()->template = 'library-form-delete';
        $this->getState()->setData([
            'media' => $media,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Media
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Delete(
     *  path="/library/{mediaId}",
     *  operationId="libraryDelete",
     *  tags={"library"},
     *  summary="Delete Media",
     *  description="Delete Media from the Library",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="forceDelete",
     *      in="formData",
     *      description="If the media item has been used should it be force removed from items that uses it?",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="purge",
     *      in="formData",
     *      description="Should this Media be added to the Purge List for all Displays?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete(Request $request, Response $response, $id)
    {
        $media = $this->mediaFactory->getById($id);
        $params = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkDeleteable($media)) {
            throw new AccessDeniedException();
        }

        // Check
        $this->getDispatcher()->dispatch(new MediaFullLoadEvent($media), MediaFullLoadEvent::$NAME);
        $media->load(['deleting' => true]);

        if ($media->isUsed() && $params->getCheckbox('forceDelete') == 0) {
            throw new InvalidArgumentException(__('This library item is in use.'));
        }

        $this->getDispatcher()->dispatch(
            new MediaDeleteEvent($media, null, $params->getCheckbox('purge')),
            MediaDeleteEvent::$NAME
        );

        // Delete
        $media->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $media->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add a file to the library
     *  expects to be fed by the blueimp file upload handler
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/library",
     *  operationId="libraryAdd",
     *  tags={"library"},
     *  summary="Add Media",
     *  description="Add Media to the Library, optionally replacing an existing media item, optionally adding to a playlist.",
     *  @SWG\Parameter(
     *      name="files",
     *      in="formData",
     *      description="The Uploaded File",
     *      type="file",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Optional Media Name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="oldMediaId",
     *      in="formData",
     *      description="Id of an existing media file which should be replaced with the new upload",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="updateInLayouts",
     *      in="formData",
     *      description="Flag (0, 1), set to 1 to update this media in all layouts (use with oldMediaId) ",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="deleteOldRevisions",
     *      in="formData",
     *      description="Flag (0 , 1), to either remove or leave the old file revisions (use with oldMediaId)",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Comma separated string of Tags that should be assigned to uploaded Media",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="expires",
     *      in="formData",
     *      description="Date in Y-m-d H:i:s format, will set expiration date on the uploaded Media",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="formData",
     *      description="A playlistId to add this uploaded media to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetFromDt",
     *      in="formData",
     *      description="Date in Y-m-d H:i:s format, will set widget start date. Requires a playlistId.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetToDt",
     *      in="formData",
     *      description="Date in Y-m-d H:i:s format, will set widget end date. Requires a playlistId.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="deleteOnExpiry",
     *      in="formData",
     *      description="Flag (0, 1), set to 1 to remove the Widget from the Playlist when the widgetToDt has been reached",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="applyToMedia",
     *      in="formData",
     *      description="Flag (0, 1), set to 1 to apply the widgetFromDt as the expiry date on the Media",
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
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     */
    public function add(Request $request, Response $response)
    {
        $parsedBody = $this->getSanitizer($request->getParams());
        $options = $parsedBody->getArray('options', ['default' => []]);

        // Folders
        $folderId = $parsedBody->getInt('folderId');

        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }

        if ($parsedBody->getInt('playlistId') !== null) {
            $playlist = $this->playlistFactory->getById($parsedBody->getInt('playlistId'));

            if ($playlist->isDynamic === 1) {
                throw new InvalidArgumentException(__('This Playlist is dynamically managed so cannot accept manual assignments.'), 'isDynamic');
            }
        }

        $options = array_merge([
            'oldMediaId' => null,
            'updateInLayouts' => 0,
            'deleteOldRevisions' => 0,
            'allowMediaTypeChange' => 0
        ], $options);

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Handle any expiry date provided.
        // this can come from the API via `expires` or via a widgetToDt
        $expires = $parsedBody->getDate('expires');
        $widgetFromDt = $parsedBody->getDate('widgetFromDt');
        $widgetToDt = $parsedBody->getDate('widgetToDt');

        // If applyToMedia has been selected, and we have a widgetToDt, then use that as our expiry
        if ($widgetToDt !== null && $parsedBody->getCheckbox('applyToMedia', ['checkboxReturnInteger' => false])) {
            $expires = $widgetToDt;
        }

        // Validate that this date is in the future.
        if ($expires !== null && $expires->isBefore(Carbon::now())) {
            throw new InvalidArgumentException(__('Cannot set Expiry date in the past'), 'expires');
        }

        // Make sure the library exists
        MediaService::ensureLibraryExists($libraryFolder);

        // Get Valid Extensions
        if ($parsedBody->getInt('oldMediaId', ['default' => $options['oldMediaId']]) !== null) {
            $media = $this->mediaFactory->getById($parsedBody->getInt('oldMediaId', ['default' => $options['oldMediaId']]));
            $folderId = $media->folderId;
            $validExt = $this->moduleFactory->getValidExtensions(['type' => $media->mediaType, 'allowMediaTypeChange' => $options['allowMediaTypeChange']]);
        } else {
            $validExt = $this->moduleFactory->getValidExtensions();
        }

        // Make sure there is room in the library
        $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        $options = [
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'oldMediaId' => $parsedBody->getInt('oldMediaId', ['default' => $options['oldMediaId']]),
            'widgetId' => $parsedBody->getInt('widgetId'),
            'updateInLayouts' => $parsedBody->getCheckbox('updateInLayouts', ['default' => $options['updateInLayouts']]),
            'deleteOldRevisions' => $parsedBody->getCheckbox('deleteOldRevisions', ['default' => $options['deleteOldRevisions']]),
            'allowMediaTypeChange' => $options['allowMediaTypeChange'],
            'displayOrder' => $parsedBody->getInt('displayOrder'),
            'playlistId' => $parsedBody->getInt('playlistId'),
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i',
            'libraryLimit' => $libraryLimit,
            'libraryQuotaFull' => ($libraryLimit > 0 && $this->getMediaService()->libraryUsage() > $libraryLimit),
            'expires' => $expires === null ? null : $expires->format('U'),
            'widgetFromDt' => $widgetFromDt === null ? null : $widgetFromDt->format('U'),
            'widgetToDt' => $widgetToDt === null ? null : $widgetToDt->format('U'),
            'deleteOnExpiry' => $parsedBody->getCheckbox('deleteOnExpiry', ['checkboxReturnInteger' => true]),
            'oldFolderId' => $folderId,
        ];

        // Output handled by UploadHandler
        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: ' . json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        new XiboUploadHandler($libraryFolder . 'temp/', $this->getLog()->getLoggerInterface(), $options);

        // Explicitly set the Content-Type header to application/json
        $response = $response->withHeader('Content-Type', 'application/json');

        return $this->render($request, $response);
    }

    /**
     * Edit Form
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
        $media = $this->mediaFactory->getById($id);

        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $media->enableStat = ($media->enableStat == null) ? $this->getConfig()->getSetting('MEDIA_STATS_ENABLED_DEFAULT') : $media->enableStat;

        $this->getState()->template = 'library-form-edit';
        $this->getState()->setData([
            'media' => $media,
            'validExtensions' => implode('|', $this->moduleFactory->getValidExtensions(['type' => $media->mediaType])),
            'expiryDate' => ($media->expires == 0 ) ? null : Carbon::createFromTimestamp($media->expires)->format(DateFormatHelper::getSystemFormat(), $media->expires)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Media
     *
     * @SWG\Put(
     *  path="/library/{mediaId}",
     *  operationId="libraryEdit",
     *  tags={"library"},
     *  summary="Edit Media",
     *  description="Edit a Media Item in the Library",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Media Item Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The duration in seconds for this Media Item",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="formData",
     *      description="Flag indicating if this media is retired",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Comma separated list of Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="updateInLayouts",
     *      in="formData",
     *      description="Flag indicating whether to update the duration in all Layouts the Media is assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="expires",
     *      in="formData",
     *      description="Date in Y-m-d H:i:s format, will set expiration date on the Media item",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this media should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
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
    public function edit(Request $request, Response $response, $id)
    {
        $media = $this->mediaFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        if ($media->mediaType == 'font') {
            throw new InvalidArgumentException(__('Sorry, Fonts do not have any editable properties.'));
        }

        $media->name = $sanitizedParams->getString('name');
        $media->duration = $sanitizedParams->getInt('duration');
        $media->retired = $sanitizedParams->getCheckbox('retired');

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            if (is_array($sanitizedParams->getParam('tags'))) {
                $tags = $this->tagFactory->tagsFromJson($sanitizedParams->getArray('tags'));
            } else {
                $tags = $this->tagFactory->tagsFromString($sanitizedParams->getString('tags'));
            }

            $media->updateTagLinks($tags);
        }

        $media->enableStat = $sanitizedParams->getString('enableStat');
        $media->folderId = $sanitizedParams->getInt('folderId', ['default' => $media->folderId]);
        $media->orientation = $sanitizedParams->getString('orientation', ['default' => $media->orientation]);

        if ($media->hasPropertyChanged('folderId')) {
            if ($media->folderId === 1) {
                $this->checkRootFolderAllowSave();
            }
            $folder = $this->folderFactory->getById($media->folderId);
            $media->permissionsFolderId = ($folder->getPermissionFolderId() == null)
                ? $folder->id
                : $folder->getPermissionFolderId();
        }

        if ($sanitizedParams->getDate('expires') != null) {
            if ($sanitizedParams->getDate('expires')->format('U') > Carbon::now()->format('U')) {
                $media->expires = $sanitizedParams->getDate('expires')->format('U');
            } else {
                throw new InvalidArgumentException(__('Cannot set Expiry date in the past'), 'expires');
            }
        } else {
            $media->expires = 0;
        }

        // Should we update the media in all layouts?
        if ($sanitizedParams->getCheckbox('updateInLayouts') == 1
            || $media->hasPropertyChanged('enableStat')
        ) {
            foreach ($this->widgetFactory->getByMediaId($media->mediaId, 0) as $widget) {
                if ($widget->useDuration == 1) {
                    $widget->calculateDuration($this->moduleFactory->getByType($widget->type));
                } else {
                    $widget->calculatedDuration = $media->duration;
                }
                $widget->save();
            }
        }

        $media->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);

        return $this->render($request, $response);
    }

    /**
     * Tidy Library
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function tidyForm(Request $request, Response $response)
    {
        if ($this->getConfig()->getSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1) {
            throw new ConfigurationException(__('Sorry this function is disabled.'));
        }

        // Work out how many files there are
        $media = $this->mediaFactory->query(null, ['unusedOnly' => 1, 'ownerId' => $this->getUser()->userId]);

        $sumExcludingGeneric = 0;
        $countExcludingGeneric = 0;
        $sumGeneric = 0;
        $countGeneric = 0;

        foreach ($media as $item) {
            if ($item->mediaType == 'genericfile') {
                $countGeneric++;
                $sumGeneric = $sumGeneric + $item->fileSize;
            }
            else {
                $countExcludingGeneric++;
                $sumExcludingGeneric = $sumExcludingGeneric + $item->fileSize;
            }
        }

        $this->getState()->template = 'library-form-tidy';
        $this->getState()->setData([
            'sumExcludingGeneric' => ByteFormatter::format($sumExcludingGeneric),
            'sumGeneric' => ByteFormatter::format($sumGeneric),
            'countExcludingGeneric' => $countExcludingGeneric,
            'countGeneric' => $countGeneric,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Tidies up the library
     *
     * @SWG\Delete(
     *  path="/library/tidy",
     *  operationId="libraryTidy",
     *  tags={"library"},
     *  summary="Tidy Library",
     *  description="Routine tidy of the library, removing unused files.",
     *  @SWG\Parameter(
     *      name="tidyGenericFiles",
     *      in="formData",
     *      description="Also delete generic files?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function tidy(Request $request, Response $response)
    {
        if ($this->getConfig()->getSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1) {
            throw new ConfigurationException(__('Sorry this function is disabled.'));
        }

        $tidyGenericFiles = $this->getSanitizer($request->getParams())->getCheckbox('tidyGenericFiles');

        $this->getLog()->audit('Media', 0, 'Tidy library started', [
            'tidyGenericFiles' => $tidyGenericFiles,
            'initiator' => $this->getUser()->userId
        ]);

        // Get a list of media that is not in use (for this user)
        $media = $this->mediaFactory->query(null, ['unusedOnly' => 1, 'ownerId' => $this->getUser()->userId]);

        $i = 0;
        foreach ($media as $item) {
            if ($tidyGenericFiles != 1 && $item->mediaType == 'genericfile') {
                continue;
            }

            // Eligible for delete
            $i++;
            $this->getDispatcher()->dispatch(new MediaDeleteEvent($item), MediaDeleteEvent::$NAME);
            $item->delete();
        }

        $this->getLog()->audit('Media', 0, 'Tidy library complete', [
            'countDeleted' => $i,
            'initiator' => $this->getUser()->userId
        ]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Library Tidy Complete'),
            'countDeleted' => $i
        ]);

        return $this->render($request, $response);
    }

    /**
     * @return string
     */
    public function getLibraryCacheUri()
    {
        return $this->getConfig()->getSetting('LIBRARY_LOCATION') . '/cache';
    }

    /**
     * @SWG\Get(
     *  path="/library/download/{mediaId}/{type}",
     *  operationId="libraryDownload",
     *  tags={"library"},
     *  summary="Download Media",
     *  description="Download a Media file from the Library",
     *  produces={"application/octet-stream"},
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID to Download",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="path",
     *      description="The Module Type of the Download",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(type="file"),
     *      @SWG\Header(
     *          header="X-Sendfile",
     *          description="Apache Send file header - if enabled.",
     *          type="string"
     *      ),
     *      @SWG\Header(
     *          header="X-Accel-Redirect",
     *          description="nginx send file header - if enabled.",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function download(Request $request, Response $response, $id)
    {
        $this->setNoOutput();

        // We can download by mediaId or by mediaName.
        if (is_numeric($id)) {
            $media = $this->mediaFactory->getById($id);
        } else {
            $media = $this->mediaFactory->getByName($id);
        }

        $this->getLog()->debug('download: Download request for mediaId ' . $id
            . '. Media is a ' . $media->mediaType . ', is system file:' . $media->moduleSystemFile);

        // Create the appropriate module
        if ($media->mediaType === 'module') {
            $module = $this->moduleFactory->getByType('image');
        } else {
            $module = $this->moduleFactory->getByType($media->mediaType);
        }

        // We are not able to download region specific modules
        if ($module->regionSpecific == 1) {
            throw new NotFoundException(__('Cannot download region specific module'));
        }

        // Hand over to the widget downloader
        $downloader = new WidgetDownloader(
            $this->getConfig()->getSetting('LIBRARY_LOCATION'),
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $this->getConfig()->getSetting('DEFAULT_RESIZE_LIMIT', 6000)
        );
        $downloader->useLogger($this->getLog()->getLoggerInterface());

        $params = $this->getSanitizer($request->getParams());

        // Check if preview is allowed for the module
        if ($params->getCheckbox('preview') == 1 && $module->allowPreview === 1) {
            $this->getLog()->debug('download: preview mode, seeing if we can output an image/video');

            // Output a 1px image if we're not allowed to see the media.
            if (!$this->getUser()->checkViewable($media)) {
                echo Img::make($this->getConfig()->uri('img/1x1.png', true))->encode();
                return $this->render($request, $response);
            }

            // Various different behaviours for the different types of file.
            if ($module->type === 'image') {
                $response = $downloader->imagePreview(
                    $params,
                    $media->storedAs,
                    $response,
                    $this->getUser()->checkViewable($media),
                );
            } else if ($module->type === 'video') {
                $response = $downloader->imagePreview(
                    $params,
                    $media->mediaId . '_videocover.png',
                    $response,
                    $this->getUser()->checkViewable($media),
                );
            } else {
                $response = $downloader->download($media, $response, $media->getMimeType());
            }
        } else {
            $this->getLog()->debug('download: not preview mode, expect a full download');

            // We are not a preview, and therefore we ought to check sharing before we download
            if (!$this->getUser()->checkViewable($media)) {
                throw new AccessDeniedException();
            }

            $response = $downloader->download($media, $response, null, $params->getString('attachment'));
        }

        return $this->render($request, $response);
    }

    /**
     * Thumbnail for the libary page
     *  this is called by library-page datatable
     *
     * @SWG\Get(
     *  path="/library/thumbnail/{mediaId}",
     *  operationId="libraryThumbnail",
     *  tags={"library"},
     *  summary="Download Thumbnail",
     *  description="Download thumbnail for a Media file from the Library",
     *  produces={"application/octet-stream"},
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID to Download",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(type="file"),
     *      @SWG\Header(
     *          header="X-Sendfile",
     *          description="Apache Send file header - if enabled.",
     *          type="string"
     *      ),
     *      @SWG\Header(
     *          header="X-Accel-Redirect",
     *          description="nginx send file header - if enabled.",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function thumbnail(Request $request, Response $response, $id)
    {
        $this->setNoOutput();

        // We can download by mediaId or by mediaName.
        if (is_numeric($id)) {
            $media = $this->mediaFactory->getById($id);
        } else {
            $media = $this->mediaFactory->getByName($id);
        }

        $this->getLog()->debug('thumbnail: Thumbnail request for mediaId ' . $id
            . '. Media is a ' . $media->mediaType);

        // Permissions.
        if (!$this->getUser()->checkViewable($media)) {
            // Output a 1px image if we're not allowed to see the media.
            echo Img::make($this->getConfig()->uri('img/1x1.png', true))->encode();
            return $this->render($request, $response);
        }

        // Hand over to the widget downloader
        $downloader = new WidgetDownloader(
            $this->getConfig()->getSetting('LIBRARY_LOCATION'),
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $this->getConfig()->getSetting('DEFAULT_RESIZE_LIMIT', 6000)
        );
        $downloader->useLogger($this->getLog()->getLoggerInterface());

        $response = $downloader->thumbnail(
            $media,
            $response,
            $this->getConfig()->uri('img/error.png', true)
        );

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function mcaas(Request $request, Response $response, $id)
    {
        // This is only available through the API
        if (!$this->isApi($request)) {
            throw new AccessDeniedException(__('Route is available through the API'));
        }

        $options = [
            'oldMediaId' => $id,
            'updateInLayouts' => 1,
            'deleteOldRevisions' => 1,
            'allowMediaTypeChange' => 1
        ];

        // Call Add with the oldMediaId
        return $this->add($request->withParsedBody(['options' => $options]), $response);
    }

    /**
     * @SWG\Post(
     *  path="/library/{mediaId}/tag",
     *  operationId="mediaTag",
     *  tags={"library"},
     *  summary="Tag Media",
     *  description="Tag a Media with one or more tags",
     * @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media Id to Tag",
     *      type="integer",
     *      required=true
     *   ),
     * @SWG\Parameter(
     *      name="tag",
     *      in="formData",
     *      description="An array of tags",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
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
    public function tag(Request $request, Response $response, $id)
    {
        // Edit permission
        // Get the media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $tags = $this->getSanitizer($request->getParams())->getArray('tag');

        if (count($tags) <= 0) {
            throw new InvalidArgumentException(__('No tags to assign'));
        }

        foreach ($tags as $tag) {
            $media->assignTag($this->tagFactory->tagFromString($tag));
        }

        $media->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Tagged %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Post(
     *  path="/library/{mediaId}/untag",
     *  operationId="mediaUntag",
     *  tags={"library"},
     *  summary="Untag Media",
     *  description="Untag a Media with one or more tags",
     * @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media Id to Untag",
     *      type="integer",
     *      required=true
     *   ),
     * @SWG\Parameter(
     *      name="tag",
     *      in="formData",
     *      description="An array of tags",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
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
    public function untag(Request $request, Response $response, $id)
    {
        // Edit permission
        // Get the media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $tags = $this->getSanitizer($request->getParams())->getArray('tag');

        if (count($tags) <= 0) {
            throw new InvalidArgumentException(__('No tags to unassign'), 'tag');
        }

        foreach ($tags as $tag) {
            $media->unassignTag($this->tagFactory->tagFromString($tag));
        }

        $media->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Untagged %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);

        return $this->render($request, $response);
    }

    /**
     * Library Usage Report Form
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
        $media = $this->mediaFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        // Get a list of displays that this mediaId is used on
        $displays = $this->displayFactory->query($this->gridRenderSort($sanitizedParams), $this->gridRenderFilter(['disableUserCheck' => 1, 'mediaId' => $id], $sanitizedParams));

        $this->getState()->template = 'library-form-usage';
        $this->getState()->setData([
            'media' => $media,
            'countDisplays' => count($displays)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/library/usage/{mediaId}",
     *  operationId="libraryUsageReport",
     *  tags={"library"},
     *  summary="Get Library Item Usage Report",
     *  description="Get the records for the library item usage report",
     * @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media Id",
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
        $media = $this->mediaFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        // Get a list of displays that this mediaId is used on by direct assignment
        $displays = $this->displayFactory->query($this->gridRenderSort($sanitizedParams), $this->gridRenderFilter(['mediaId' => $id], $sanitizedParams));

        // have we been provided with a date/time to restrict the scheduled events to?
        $mediaFromDate = $sanitizedParams->getDate('mediaEventFromDate');
        $mediaToDate = $sanitizedParams->getDate('mediaEventToDate');

        // Media query array
        $mediaQuery = [
            'mediaId' => $id
        ];

        if ($mediaFromDate !== null) {
            $mediaQuery['futureSchedulesFrom'] = $mediaFromDate->format('U');
        }

        if ($mediaToDate !== null) {
            $mediaQuery['futureSchedulesTo'] = $mediaToDate->format('U');
        }

        // Query for events
        $events = $this->scheduleFactory->query(null, $mediaQuery);

        // Total records returned from the schedules query
        $totalRecords = $this->scheduleFactory->countLast();

        foreach ($events as $row) {
            /* @var \Xibo\Entity\Schedule $row */

            // Generate this event
            // Assess the date?
            if ($mediaFromDate !== null && $mediaToDate !== null) {
                try {
                    $scheduleEvents = $row->getEvents($mediaFromDate, $mediaToDate);
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
                    $found = false;

                    // Check to see if our ID is already in our list
                    foreach ($displays as $existing) {
                        if ($existing->displayId === $display->displayId) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found)
                        $displays[] = $display;
                }
            }
        }

        if ($this->isApi($request) && $displays == []) {
            $displays = [
                'data' =>__('Specified Media item is not in use.')];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $totalRecords;
        $this->getState()->setData($displays);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/library/usage/layouts/{mediaId}",
     *  operationId="libraryUsageLayoutsReport",
     *  tags={"library"},
     *  summary="Get Library Item Usage Report for Layouts",
     *  description="Get the records for the library item usage report for Layouts",
     * @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media Id",
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
        $media = $this->mediaFactory->getById($id);

        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());
        $layouts = $this->layoutFactory->query(
            $this->gridRenderSort($sanitizedParams),
            $this->gridRenderFilter([
                'mediaId' => $id,
                'showDrafts' => 1
            ], $sanitizedParams)
        );

        if (!$this->isApi($request)) {
            foreach ($layouts as $layout) {
                $layout->includeProperty('buttons');

                // Add some buttons for this row
                if ($this->getUser()->checkEditable($layout)) {
                    // Design Button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_design',
                        'linkType' => '_self', 'external' => true,
                        'url' => $this->urlFor($request,'layout.designer', ['id' => $layout->layoutId]),
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
                'data' =>__('Specified Media item is not in use.')
            ];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->layoutFactory->countLast();
        $this->getState()->setData($layouts);

        return $this->render($request, $response);
    }

    /**
     * Copy Media form
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
        // Get the Media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'library-form-copy';
        $this->getState()->setData([
            'media' => $media,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copies a Media
     *
     * @SWG\Post(
     *  path="/library/copy/{mediaId}",
     *  operationId="mediaCopy",
     *  tags={"library"},
     *  summary="Copy Media",
     *  description="Copy a Media, providing a new name and tags if applicable",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The media ID to Copy",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The name for the new Media",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="The Optional tags for new Media",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media"),
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
    public function copy(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check Permissions
        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        // Load the media for Copy
        $media = clone $media;

        // Set new Name and tags
        $media->name = $sanitizedParams->getString('name');

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            if (is_array($sanitizedParams->getParam('tags'))) {
                $tags = $this->tagFactory->tagsFromJson($sanitizedParams->getArray('tags'));
            } else {
                $tags = $this->tagFactory->tagsFromString($sanitizedParams->getString('tags'));
            }
            $media->updateTagLinks($tags);
        }

        // Set the Owner to user making the Copy
        $media->setOwner($this->getUser()->userId);

        // Set from global setting
        if ($media->enableStat == null) {
            $media->enableStat = $this->getConfig()->getSetting('MEDIA_STATS_ENABLED_DEFAULT');
        }

        // Save the new Media
        $media->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Copied as %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);

        return $this->render($request,  $response);
    }


    /**
     * @SWG\Get(
     *  path="/library/{mediaId}/isused/",
     *  operationId="mediaIsUsed",
     *  tags={"library"},
     *  summary="Media usage check",
     *  description="Checks if a Media is being used",
     * @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media Id",
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
    public function isUsed(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);
        $this->getDispatcher()->dispatch(new MediaFullLoadEvent($media), MediaFullLoadEvent::$NAME);

        // Check Permissions
        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        // Get count, being the number of times the media needs to appear to be true ( or use the default 0)
        $count = $this->getSanitizer($request->getParams())->getInt('count', ['default' => 0]);

        // Check and return result
        $this->getState()->setData([
            'isUsed' => $media->isUsed($count)
        ]);

        return $this->render($request, $response);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function uploadFromUrlForm(Request $request, Response $response)
    {
        $this->getState()->template = 'library-form-uploadFromUrl';

        $this->getState()->setData([
            'uploadSizeMessage' => sprintf(__('This form accepts files up to a maximum size of %s'), Environment::getMaxUploadSize())
        ]);

        return $this->render($request, $response);
    }

    /**
     * Upload Media via URL
     *
     * @SWG\Post(
     *  path="/library/uploadUrl",
     *  operationId="uploadFromUrl",
     *  tags={"library"},
     *  summary="Upload Media from URL",
     *  description="Upload Media to CMS library from an external URL",
     *  @SWG\Parameter(
     *      name="url",
     *      in="formData",
     *      description="The URL to the media",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="formData",
     *      description="The type of the media, image, video etc",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="extension",
     *      in="formData",
     *      description="Optional extension of the media, jpg, png etc. If not set in the request it will be retrieved from the headers",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option to enable the collection of Media Proof of Play statistics, On, Off or Inherit.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="optionalName",
     *      in="formData",
     *      description="An optional name for this media file, if left empty it will default to the file name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="expires",
     *      in="formData",
     *      description="Date in Y-m-d H:i:s format, will set expiration date on the Media item",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this media should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media"),
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
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws LibraryFullException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function uploadFromUrl(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Params
        $url = $sanitizedParams->getString('url');
        $type = $sanitizedParams->getString('type');
        $optionalName = $sanitizedParams->getString('optionalName');
        $extension = $sanitizedParams->getString('extension');
        $enableStat = $sanitizedParams->getString('enableStat', [
            'default' => $this->getConfig()->getSetting('MEDIA_STATS_ENABLED_DEFAULT')
        ]);

        // Folders
        $folderId = $sanitizedParams->getInt('folderId');
        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }

        $folder = $this->folderFactory->getById($folderId, 0);

        if ($sanitizedParams->hasParam('expires')) {
            if ($sanitizedParams->getDate('expires')->format('U') > Carbon::now()->format('U')) {
                $expires = $sanitizedParams->getDate('expires')->format('U');
            } else {
                throw new InvalidArgumentException(__('Cannot set Expiry date in the past'), 'expires');
            }
        } else {
            $expires = 0;
        }

        // Validate the URL
        if (!v::url()->notEmpty()->validate($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(__('Provided URL is invalid'), 'url');
        }

        // remote file size
        $downloadInfo = $this->getMediaService()->getDownloadInfo($url);

        // check if we have extension provided in the request (available via API)
        // if not get it from the headers
        if (!empty($extension)) {
            $ext = $extension;
        } else {
            $ext = $downloadInfo['extension'];
        }

        // Unsupported links (ie Youtube links, etc) will return a null extension, thus, throw an error
        if (is_null($ext)) {
            throw new NotFoundException(sprintf(__('Extension %s is not supported.'), $ext));
        }

        // Initialise the library and do some checks
        $this->getMediaService()
            ->initLibrary()
            ->checkLibraryOrQuotaFull(true)
            ->checkMaxUploadSize($downloadInfo['size']);

        // check if we have type provided in the request (available via API), if not get the module type from
        // the extension
        if (!empty($type)) {
            $module = $this->getModuleFactory()->getByType($type);
        } else {
            $module = $this->getModuleFactory()->getByExtension($ext);
            $module = $this->getModuleFactory()->getByType($module->type);
        }

        // if we were provided with optional Media name set it here, otherwise get it from download info
        $name = empty($optionalName) ? htmlspecialchars($downloadInfo['filename']) : $optionalName;

        // double check that provided Module Type and Extension are valid
        if (!Str::contains($module->getSetting('validExtensions'), $ext)) {
            throw new NotFoundException(
                sprintf(
                    __('Invalid Module type or extension. Module type %s does not allow for %s extension'),
                    $module->type,
                    $ext
                )
            );
        }

        // add our media to queueDownload and process the downloads
        $media = $this->mediaFactory->queueDownload(
            $name,
            str_replace(' ', '%20', htmlspecialchars_decode($url)),
            $expires,
            [
                'fileType' => strtolower($module->type),
                'duration' => $module->defaultDuration,
                'extension' => $ext,
                'enableStat' => $enableStat,
                'folderId' => $folder->getId(),
                'permissionsFolderId' => $folder->getPermissionFolderIdOrThis()
            ]
        );

        $this->mediaFactory->processDownloads(
            function (Media $media) use ($module) {
                // Success
                $this->getLog()->debug('Successfully uploaded Media from URL, Media Id is ' . $media->mediaId);
                $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');
                $realDuration = $module->fetchDurationOrDefaultFromFile($libraryFolder . $media->storedAs);
                if ($realDuration !== $media->duration) {
                    $media->updateDuration($realDuration);
                }
            },
            function (Media $media) {
                throw new InvalidArgumentException(__('Download rejected for an unknown reason.'));
            },
            function ($message) {
                // Download rejected.
                throw new InvalidArgumentException(sprintf(__('Download rejected due to %s'), $message));
            }
        );

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Media upload from URL was successful'),
            'id' => $media->mediaId,
            'data' => $media
        ]);

        return $this->render($request, $response);
    }

    /**
     * This is called when video finishes uploading.
     * Saves provided base64 image as an actual image to the library
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function addThumbnail($request, $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        MediaService::ensureLibraryExists($libraryLocation);

        $imageData = $request->getParam('image');
        $mediaId = $sanitizedParams->getInt('mediaId');
        $media = $this->mediaFactory->getById($mediaId);

        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        try {
            Img::configure(array('driver' => 'gd'));

            // Load the image
            $image = Img::make($imageData);
            $image->save($libraryLocation . $mediaId . '_' . $media->mediaType . 'cover.png');
        } catch (\Exception $exception) {
            $this->getLog()->error('Exception adding Video cover image. e = ' . $exception->getMessage());
            throw new InvalidArgumentException(__('Invalid image data'));
        }

        $media->width = $image->getWidth();
        $media->height = $image->getHeight();
        $media->orientation = ($media->width >= $media->height) ? 'landscape' : 'portrait';
        $media->save(['saveTags' => false, 'validate' => false]);

        return $response->withStatus(204);
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
        // Get the Media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $data = [
            'media' => $media
        ];

        $this->getState()->template = 'library-form-selectfolder';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Put(
     *  path="/library/{id}/selectfolder",
     *  operationId="librarySelectFolder",
     *  tags={"library"},
     *  summary="Media Select folder",
     *  description="Select Folder for Media",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID",
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
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Campaign")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
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
    public function selectFolder(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $folderId = $this->getSanitizer($request->getParams())->getInt('folderId');
        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        $media->folderId = $folderId;
        $folder = $this->folderFactory->getById($media->folderId);
        $media->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();

        $media->save(['saveTags' => false]);

        if ($media->parentId != 0) {
            $this->updateMediaRevision($media, $folderId);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Media %s moved to Folder %s'), $media->name, $folder->text)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Connector import.
     *
     *  Note: this doesn't have a Swagger document because it is only available via the web UI.
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function connectorImport(Request $request, Response $response)
    {
        $params = $this->getSanitizer($request->getParams());
        $items = $params->getArray('items');

        // Folders
        $folderId = $params->getInt('folderId');
        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }
        $folder = $this->folderFactory->getById($folderId, 0);

        // Stats
        $enableStat = $params->getString('enableStat', [
            'default' => $this->getConfig()->getSetting('MEDIA_STATS_ENABLED_DEFAULT')
        ]);

        // Initialise the library.
        $this->getMediaService()
            ->initLibrary()
            ->checkLibraryOrQuotaFull(true);

        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Hand these off to the connector to format into a downloadable response.
        $importQueue = [];
        foreach ($items as $item) {
            $import = new ProviderImport();
            $import->searchResult = new SearchResult();
            $import->searchResult->provider = new ProviderDetails();
            $import->searchResult->provider->id = $item['provider']['id'];
            $import->searchResult->title = $item['title'];
            $import->searchResult->id = $item['id'];
            $import->searchResult->type = $item['type'];
            $import->searchResult->download = $item['download'];
            $import->searchResult->duration = (int)$item['duration'];
            $import->searchResult->videoThumbnailUrl = $item['videoThumbnailUrl'];
            $importQueue[] = $import;
        }
        $event = new LibraryProviderImportEvent($importQueue);
        $this->getDispatcher()->dispatch($event, $event->getName());

        // Pull out our events and upload
        foreach ($importQueue as $import) {
            try {
                // Has this been configured for upload?
                if ($import->isConfigured) {
                    // Make sure we have a URL
                    if (empty($import->url)) {
                        throw new InvalidArgumentException('Missing or invalid URL', 'url');
                    }

                    // This ensures that apiRef will be unique for each provider and resource id
                    $apiRef = $import->searchResult->provider->id . '_' . $import->searchResult->id;

                    // Queue this for upload.
                    // Use a module to make sure our type, etc is supported.
                    $module = $this->getModuleFactory()->getByType($import->searchResult->type);
                    $import->media = $this->mediaFactory->queueDownload(
                        $import->searchResult->title,
                        str_replace(' ', '%20', htmlspecialchars_decode($import->url)),
                        0,
                        [
                            'fileType' => strtolower($module->type),
                            'duration' => !(empty($import->searchResult->duration))
                                ? $import->searchResult->duration
                                : $module->defaultDuration,
                            'enableStat' => $enableStat,
                            'folderId' => $folder->getId(),
                            'permissionsFolderId' => $folder->permissionsFolderId,
                            'apiRef' => $apiRef
                        ]
                    );
                } else {
                    throw new GeneralException(__('Not configured by any active connector.'));
                }
            } catch (\Exception $e) {
                $import->setError($e->getMessage());
            }
        }

        // Process all of those downloads
        $this->mediaFactory->processDownloads(
            function (Media $media) use ($importQueue, $libraryLocation) {
                // Success
                // if we have video thumbnail url from provider, download it now
                foreach ($importQueue as $import) {
                    /** @var ProviderImport $import */
                    if ($import->media->getId() === $media->getId()
                        && $media->mediaType === 'video'
                        && !empty($import->searchResult->videoThumbnailUrl)
                    ) {
                        try {
                            $filePath = $libraryLocation . $media->getId() . '_' . $media->mediaType . 'cover.png';

                            // Expect a quick download.
                            $client = new Client($this->getConfig()->getGuzzleProxy(['timeout' => 20]));
                            $client->request(
                                'GET',
                                $import->searchResult->videoThumbnailUrl,
                                ['sink' => $filePath]
                            );

                            list($imgWidth, $imgHeight) = @getimagesize($filePath);
                            $media->updateOrientation($imgWidth, $imgHeight);
                        } catch (\Exception $exception) {
                            // if we failed, corrupted file might still be created, remove it here
                            unlink($libraryLocation . $media->getId() . '_' . $media->mediaType . 'cover.png');
                            $this->getLog()->error(sprintf(
                                'Downloading thumbnail for video %s, from url %s, failed with message %s',
                                $media->name,
                                $import->searchResult->videoThumbnailUrl,
                                $exception->getMessage()
                            ));
                        }
                    }
                }
            },
            function ($media) use ($importQueue) {
                // Failure
                // Pull out the import which failed.
                foreach ($importQueue as $import) {
                    /** @var ProviderImport $import */
                    if ($import->media->getId() === $media->getId()) {
                        $import->setError(__('Download failed'));
                    }
                }
            }
        );

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => __('Imported'),
            'data' => $event->getItems()
        ]);

        return $this->render($request, $response);
    }

    /**
     * Check if we already have a full screen Layout for this Media
     * @param Media $media
     * @return int|null
     * @throws NotFoundException
     */
    private function hasFullScreenLayout(Media $media): ?int
    {
        return $this->layoutFactory->getLinkedFullScreenLayout('media', $media->mediaId)?->campaignId;
    }

    /**
     * Update media files with revisions
     * @param Media $media
     * @param $folderId
     */
    private function updateMediaRevision(Media $media, $folderId)
    {
        $oldMedia = $this->mediaFactory->getParentById($media->mediaId);
        $oldMedia->folderId = $folderId;
        $folder = $this->folderFactory->getById($oldMedia->folderId);
        $folder->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();

        $oldMedia->save(['saveTags' => false, 'validate' => false]);
    }
}
