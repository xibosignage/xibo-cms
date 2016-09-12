<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner, Spring Signage Ltd
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

use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Media;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\LibraryFullException;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\XiboUploadHandler;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Library
 * @package Xibo\Controller
 */
class Library extends Base
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /** @var  PoolInterface */
    private $pool;

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

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var  RegionFactory */
    private $regionFactory;

    /** @var  DataSetFactory */
    private $dataSetFactory;

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
     * @param PoolInterface $pool
     * @param UserFactory $userFactory
     * @param ModuleFactory $moduleFactory
     * @param TagFactory $tagFactory
     * @param MediaFactory $mediaFactory
     * @param WidgetFactory $widgetFactory
     * @param PermissionFactory $permissionFactory
     * @param LayoutFactory $layoutFactory
     * @param PlaylistFactory $playlistFactory
     * @param UserGroupFactory $userGroupFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param RegionFactory $regionFactory
     * @param DataSetFactory $dataSetFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $pool, $userFactory, $moduleFactory, $tagFactory, $mediaFactory, $widgetFactory, $permissionFactory, $layoutFactory, $playlistFactory, $userGroupFactory, $displayGroupFactory, $regionFactory, $dataSetFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->moduleFactory = $moduleFactory;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->pool = $pool;
        $this->userFactory = $userFactory;
        $this->tagFactory = $tagFactory;
        $this->permissionFactory = $permissionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->playlistFactory = $playlistFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->regionFactory = $regionFactory;
        $this->dataSetFactory = $dataSetFactory;
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
     * Get UserGroup Factory
     * @return UserGroupFactory
     */
    public function getUserGroupFactory()
    {
        return $this->userGroupFactory;
    }

    /**
     * Get RegionFactory
     * @return RegionFactory
     */
    public function getRegionFactory()
    {
        return $this->regionFactory;
    }

    /**
     * Get DisplayGroup Factory
     * @return DisplayGroupFactory
     */
    public function getDisplayGroupFactory()
    {
        return $this->displayGroupFactory;
    }

    /**
     * @return DataSetFactory
     */
    public function getDataSetFactory()
    {
        return $this->dataSetFactory;
    }

    /**
     * Displays the page logic
     */
    function displayPage()
    {
        // Users we have permission to see
        $this->getState()->template = 'library-page';
        $this->getState()->setData([
            'users' => $this->userFactory->query(),
            'modules' => $this->moduleFactory->query(['module'], ['regionSpecific' => 0, 'enabled' => 1])
        ]);
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
     *      in="formData",
     *      description="Filter by Media Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="media",
     *      in="formData",
     *      description="Filter by Media Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="formData",
     *      description="Filter by Media Type",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerId",
     *      in="formData",
     *      description="Filter by Owner Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="formData",
     *      description="Filter by Retired",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="Filter by Duration - a number or less-than,greater-than,less-than-equal or great-than-equal followed by a | followed by a number",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="fileSize",
     *      in="formData",
     *      description="Filter by File Size - a number or less-than,greater-than,less-than-equal or great-than-equal followed by a | followed by a number",
     *      type="string",
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
     */
    function grid()
    {
        $user = $this->getUser();

        // Construct the SQL
        $mediaList = $this->mediaFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'mediaId' => $this->getSanitizer()->getInt('mediaId'),
            'name' => $this->getSanitizer()->getString('media'),
            'type' => $this->getSanitizer()->getString('type'),
            'tags' => $this->getSanitizer()->getString('tags'),
            'ownerId' => $this->getSanitizer()->getInt('ownerId'),
            'retired' => $this->getSanitizer()->getInt('retired'),
            'duration' => $this->getSanitizer()->getString('duration'),
            'fileSize' => $this->getSanitizer()->getString('fileSize')
        ]));

        // Add some additional row content
        foreach ($mediaList as $media) {
            /* @var \Xibo\Entity\Media $media */
            $media->revised = ($media->parentId != 0) ? 1 : 0;

            // Thumbnail URL
            $media->thumbnail = '';

            if ($media->mediaType == 'image') {
                $download = $this->urlFor('library.download', ['id' => $media->mediaId]) . '?preview=1';
                $media->thumbnail = '<a class="img-replace" data-toggle="lightbox" data-type="image" href="' . $download . '"><img src="' . $download . '&width=100&height=56" /></i></a>';
            }

            $media->fileSizeFormatted = ByteFormatter::format($media->fileSize);

            if ($this->isApi())
                break;

            $media->includeProperty('buttons');
            $media->buttons = array();

            // Buttons
            if ($user->checkEditable($media)) {
                // Edit
                $media->buttons[] = array(
                    'id' => 'content_button_edit',
                    'url' => $this->urlFor('library.edit.form', ['id' => $media->mediaId]),
                    'text' => __('Edit')
                );
            }

            if ($user->checkDeleteable($media)) {
                // Delete
                $media->buttons[] = array(
                    'id' => 'content_button_delete',
                    'url' => $this->urlFor('library.delete.form', ['id' => $media->mediaId]),
                    'text' => __('Delete')
                );
            }

            if ($user->checkPermissionsModifyable($media)) {
                // Permissions
                $media->buttons[] = array(
                    'id' => 'content_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'Media', 'id' => $media->mediaId]),
                    'text' => __('Permissions')
                );
            }

            // Download
            $media->buttons[] = array(
                'id' => 'content_button_download',
                'linkType' => '_self', 'external' => true,
                'url' => $this->urlFor('library.download', ['id' => $media->mediaId]) . '?attachment=' . $media->fileName,
                'text' => __('Download')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->mediaFactory->countLast();
        $this->getState()->setData($mediaList);
    }

    /**
     * Media Delete Form
     * @param int $mediaId
     */
    public function deleteForm($mediaId)
    {
        $media = $this->mediaFactory->getById($mediaId);

        if (!$this->getUser()->checkDeleteable($media))
            throw new AccessDeniedException();

        $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory);
        $media->load(['deleting' => true]);

        $this->getState()->template = 'library-form-delete';
        $this->getState()->setData([
            'media' => $media,
            'help' => $this->getHelp()->link('Library', 'Delete')
        ]);
    }

    /**
     * Delete Media
     * @param int $mediaId
     *
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
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete($mediaId)
    {
        $media = $this->mediaFactory->getById($mediaId);

        if (!$this->getUser()->checkDeleteable($media))
            throw new AccessDeniedException();

        // Check
        $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory);
        $media->load(['deleting' => true]);

        if ($media->isUsed() && $this->getSanitizer()->getCheckbox('forceDelete') == 0)
            throw new \InvalidArgumentException(__('This library item is in use.'));

        // Delete
        $media->delete();

        // Do we need to reassess fonts?
        if ($media->mediaType == 'font') {
            $this->installFonts();
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $media->name)
        ]);
    }

    /**
     * Add a file to the library
     *  expects to be fed by the blueimp file upload handler
     * @throws \Exception
     *
     * @SWG\Post(
     *  path="/library",
     *  operationId="libraryAdd",
     *  tags={"library"},
     *  summary="Add Media",
     *  description="Add Media to the Library",
     *  @SWG\Parameter(
     *      name="file",
     *      in="formData",
     *      description="The Uploaded File",
     *      type="file",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     *
     * @param array $options
     */
    public function add($options = [])
    {
        $options = array_merge([
            'oldMediaId' => null,
            'updateInLayouts' => 0,
            'deleteOldRevisions' => 0,
            'allowMediaTypeChange' => 0
        ], $options);

        $libraryFolder = $this->getConfig()->GetSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        self::ensureLibraryExists($libraryFolder);

        // Get Valid Extensions
        if ($this->getSanitizer()->getInt('oldMediaId') !== null) {
            $media = $this->mediaFactory->getById($this->getSanitizer()->getInt('oldMediaId'));
            $validExt = $this->moduleFactory->getValidExtensions(['type' => $media->mediaType]);
        }
        else
            $validExt = $this->moduleFactory->getValidExtensions();

        // Make sure there is room in the library
        $libraryLimit = $this->getConfig()->GetSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        $options = array(
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'oldMediaId' => $this->getSanitizer()->getInt('oldMediaId', $options['oldMediaId']),
            'widgetId' => $this->getSanitizer()->getInt('widgetId'),
            'updateInLayouts' => $this->getSanitizer()->getCheckbox('updateInLayouts', $options['updateInLayouts']),
            'deleteOldRevisions' => $this->getSanitizer()->getCheckbox('deleteOldRevisions', $options['deleteOldRevisions']),
            'allowMediaTypeChange' => $options['allowMediaTypeChange'],
            'playlistId' => $this->getSanitizer()->getInt('playlistId'),
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor('library.add'),
            'upload_url' => $this->urlFor('library.add'),
            'image_versions' => array(),
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i',
            'libraryLimit' => $libraryLimit,
            'libraryQuotaFull' => ($libraryLimit > 0 && $this->libraryUsage() > $libraryLimit)
        );

        // Output handled by UploadHandler
        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: %s', json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        new XiboUploadHandler($options);
    }

    /**
     * Edit Form
     * @param int $mediaId
     */
    public function editForm($mediaId)
    {
        $media = $this->mediaFactory->getById($mediaId);

        if (!$this->getUser()->checkEditable($media))
            throw new AccessDeniedException();

        $this->getState()->template = 'library-form-edit';
        $this->getState()->setData([
            'media' => $media,
            'validExtensions' => implode('|', $this->moduleFactory->getValidExtensions(['type' => $media->mediaType])),
            'help' => $this->getHelp()->link('Library', 'Edit')
        ]);
    }

    /**
     * Edit Media
     * @param int $mediaId
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
     *      description="Flag indicating if this Layout is retired",
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
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media")
     *  )
     * )
     */
    public function edit($mediaId)
    {
        $media = $this->mediaFactory->getById($mediaId);

        if (!$this->getUser()->checkEditable($media))
            throw new AccessDeniedException();

        if ($media->mediaType == 'font')
            throw new \InvalidArgumentException(__('Sorry, Fonts do not have any editable properties.'));

        $media->name = $this->getSanitizer()->getString('name');
        $media->duration = $this->getSanitizer()->getInt('duration');
        $media->retired = $this->getSanitizer()->getCheckbox('retired');
        $media->replaceTags($this->tagFactory->tagsFromString($this->getSanitizer()->getString('tags')));

        // Should we update the media in all layouts?
        if ($this->getSanitizer()->getCheckbox('updateInLayouts') == 1) {
            foreach ($this->widgetFactory->getByMediaId($media->mediaId) as $widget) {
                /* @var Widget $widget */
                $widget->duration = $media->duration;
                $widget->save();
            }
        }

        $media->save();

        // Are we a font
        if ($media->mediaType == 'font') {
            // We may have made changes and need to regenerate
            $this->installFonts();
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);
    }

    /**
     * Tidy Library
     */
    public function tidyForm()
    {
        if ($this->getConfig()->GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            throw new ConfigurationException(__('Sorry this function is disabled.'));

        // Work out how many files there are
        $media = $this->mediaFactory->query(null, ['unusedOnly' => 1, 'ownerId' => $this->getUser()->userId]);

        $size = ByteFormatter::format(array_sum(array_map(function ($element) {
            return $element->fileSize;
        }, $media)));

        $this->getState()->template = 'library-form-tidy';
        $this->getState()->setData([
            'size' => $size,
            'quantity' => count($media),
            'help' => $this->getHelp()->link('Content', 'TidyLibrary')
        ]);
    }

    /**
     * Tidies up the library
     *
     * @SWG\Post(
     *  path="/library/tidy",
     *  operationId="libraryTidy",
     *  tags={"library"},
     *  summary="Tidy Library",
     *  description="Routine tidy of the library, removing unused files.",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     */
    public function tidy()
    {
        if ($this->getConfig()->GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            throw new ConfigurationException(__('Sorry this function is disabled.'));

        // Get a list of media that is not in use (for this user)
        $media = $this->mediaFactory->query(null, ['unusedOnly' => 1, 'ownerId' => $this->getUser()->userId]);

        $i = 0;
        foreach ($media as $item) {
            /* @var Media $item */
            $i++;
            $item->load();
            $item->delete();
        }

        // Return
        $this->getState()->hydrate([
            'message' => __('Library Tidy Complete'),
            'countDeleted' => $i
        ]);
    }

    /**
     * Make sure the library exists
     * @param string $libraryFolder
     * @throws ConfigurationException when the library is not writable
     */
    public static function ensureLibraryExists($libraryFolder)
    {
        // Check that this location exists - and if not create it..
        if (!file_exists($libraryFolder))
            mkdir($libraryFolder, 0777, true);

        if (!file_exists($libraryFolder . '/temp'))
            mkdir($libraryFolder . '/temp', 0777, true);

        if (!file_exists($libraryFolder . '/cache'))
            mkdir($libraryFolder . '/cache', 0777, true);

        if (!file_exists($libraryFolder . '/screenshots'))
            mkdir($libraryFolder . '/screenshots', 0777, true);

        // Check that we are now writable - if not then error
        if (!is_writable($libraryFolder))
            throw new ConfigurationException(__('Library not writable'));
    }

    /**
     * @return string
     */
    public function getLibraryCacheUri()
    {
        return $this->getConfig()->GetSetting('LIBRARY_LOCATION') . '/cache';
    }

    /**
     * Library Usage
     * @return int
     */
    public function libraryUsage()
    {
        $results = $this->store->select('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', array());

        return $this->getSanitizer()->int($results[0]['SumSize']);
    }

    /**
     * Gets a file from the library
     * @param int $mediaId
     * @param string $type
     *
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
     */
    public function download($mediaId, $type = '')
    {
        $this->getLog()->debug('Download request for mediaId %d and type %s', $mediaId, $type);

        $media = $this->mediaFactory->getById($mediaId);

        if (!$this->getUser()->checkViewable($media))
            throw new AccessDeniedException();

        if ($type != '') {
            $widget = $this->moduleFactory->create($type);
            $widgetOverride = $this->widgetFactory->createEmpty();
            $widgetOverride->assignMedia($media->mediaId);
            $widget->setWidget($widgetOverride);

        } else {
            // Make a media module
            $widget = $this->moduleFactory->createWithMedia($media);
        }

        $widget->getResource();

        $this->setNoOutput(true);
    }

    /**
     * Return the CMS flavored font css
     */
    public function fontCss()
    {
        // Regenerate the CSS for fonts
        $css = $this->installFonts(['invalidateCache' => false]);

        // Work out the etag
        $app = $this->getApp();
        $app->response()->header('Content-Type', 'text/css');
        $app->etag(md5($css['css']));

        // Return the CSS to the browser as a file
        $out = fopen('php://output', 'w');
        fputs($out, $css['css']);
        fclose($out);

        $this->setNoOutput(true);
    }

    /**
     * Get font CKEditor config
     * @return string
     */
    public function fontCKEditorConfig()
    {
        if (DBVERSION < 120)
            return null;

        // Regenerate the CSS for fonts
        $css = $this->installFonts(['invalidateCache' => false]);

        return $css['ckeditor'];
    }

    /**
     * Installs fonts
     * @param array $options
     * @return array
     */
    public function installFonts($options = [])
    {
        $options = array_merge([
            'invalidateCache' => true
        ], $options);

        $this->getLog()->debug('Install Fonts called with options: %s', json_encode($options));

        // Get the item from the cache
        $cssItem = $this->pool->getItem('fontCss');

        // Get the CSS
        $cssDetails = $cssItem->get();

        if ($options['invalidateCache'] || $cssItem->isMiss()) {
            $this->getLog()->info('Regenerating font cache');

            $fontTemplate = '@font-face {
    font-family: \'[family]\';
    src: url(\'[url]\');
}';

            // Save a fonts.css file to the library for use as a module
            $fonts = $this->mediaFactory->getByMediaType('font');

            $css = '';
            $localCss = '';
            $ckEditorString = '';

            if (count($fonts) > 0) {

                foreach ($fonts as $font) {
                    /* @var Media $font */

                    // Skip unreleased fonts
                    if ($font->released == 0)
                        continue;

                    // Separate out the display name and the referenced name (referenced name cannot contain any odd characters or numbers)
                    $displayName = $font->name;
                    $familyName = strtolower(preg_replace('/\s+/', ' ', preg_replace('/\d+/u', '', $font->name)));

                    // Css for the client contains the actual stored as location of the font.
                    $css .= str_replace('[url]', $font->storedAs, str_replace('[family]', $familyName, $fontTemplate));

                    // Css for the local CMS contains the full download path to the font
                    $url = $this->urlFor('library.download', ['type' => 'font', 'id' => $font->mediaId]) . '?download=1&downloadFromLibrary=1';
                    $localCss .= str_replace('[url]', $url, str_replace('[family]', $familyName, $fontTemplate));

                    // CKEditor string
                    $ckEditorString .= $displayName . '/' . $familyName . ';';
                }

                // Make sure the library exists, otherwise we can't copy the temporary file.
                $this->ensureLibraryExists($this->getConfig()->GetSetting('LIBRARY_LOCATION'));

                // Put the player CSS into the temporary library location
                $tempUrl = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . 'temp/fonts.css';
                file_put_contents($tempUrl, $css);

                // Install it (doesn't expire, isn't a system file, force update)
                $media = $this->mediaFactory->createModuleSystemFile('fonts.css', $tempUrl);
                $media->expires = 0;
                $media->moduleSystemFile = true;
                $media->force = true;
                $media->save();

                $cssDetails = [
                    'css' => $localCss,
                    'ckeditor' => $ckEditorString
                ];

                $cssItem->set($cssDetails);
                $cssItem->expiresAfter(new \DateInterval('P30D'));
                $this->pool->saveDeferred($cssItem);

                // Clear the display cache
                $this->pool->deleteItem('/display');
            }
        } else {
            $this->getLog()->debug('CMS font CSS returned from Cache.');
        }

        // Return a fonts css string for use locally (in the CMS)
        return $cssDetails;
    }

    /**
     * Installs all files related to the enabled modules
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
    }

    /**
     * Remove temporary files
     */
    public function removeTempFiles()
    {
        $libraryTemp = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . 'temp';

        if (!is_dir($libraryTemp))
            return;

        // Dump the files in the temp folder
        foreach (scandir($libraryTemp) as $item) {
            if ($item == '.' || $item == '..')
                continue;

            $this->getLog()->debug('Deleting temp file: ' . $item);

            unlink($libraryTemp . DIRECTORY_SEPARATOR . $item);
        }
    }

    /**
     * Removes all expired media files
     */
    public function removeExpiredFiles()
    {
        // Get a list of all expired files and delete them
        foreach ($this->mediaFactory->query(null, array('expires' => time(), 'allModules' => 1)) as $entry) {
            /* @var \Xibo\Entity\Media $entry */
            // If the media type is a module, then pretend its a generic file
            $this->getLog()->info('Removing Expired File %s', $entry->name);
            $entry->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory);
            $entry->delete();
        }
    }

    /**
     * @param $mediaId
     * @throws LibraryFullException
     */
    public function mcaas($mediaId)
    {
        // This is only available through the API
        if (!$this->isApi())
            throw new AccessDeniedException(__('Route is available through the API'));

        // We need to get the access token we used to authorize this request.
        // as we are API we can expect that in the $app.
        /** @var $accessToken \League\OAuth2\Server\Entity\AccessTokenEntity */
        $accessToken = $this->getApp()->server->getAccessToken();

        // Call Add with the oldMediaId
        $this->add([
            'oldMediaId' => $mediaId,
            'updateInLayouts' => 1,
            'deleteOldRevisions' => 1,
            'allowMediaTypeChange' => 1
        ]);

        // Expire the token
        $accessToken->expire();
    }

    /**
     * @SWG\Post(
     *  path="/media/{mediaId}/tag",
     *  operationId="mediaTag",
     *  tags={"media"},
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
     * @param $mediaId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function tag($mediaId)
    {
        // Edit permission
        // Get the media
        $media = $this->mediaFactory->getById($mediaId);

        // Check Permissions
        if (!$this->getUser()->checkEditable($media))
            throw new AccessDeniedException();

        $tags = $this->getSanitizer()->getStringArray('tag');

        if (count($tags) <= 0)
            throw new \InvalidArgumentException(__('No tags to assign'));

        foreach ($tags as $tag) {
            $media->assignTag($this->tagFactory->tagFromString($tag));
        }

        $media->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Tagged %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);
    }

    /**
     * @SWG\Delete(
     *  path="/media/{mediaId}/tag",
     *  operationId="mediaUntag",
     *  tags={"media"},
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
     * @param $mediaId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function untag($mediaId)
    {
        // Edit permission
        // Get the media
        $media = $this->mediaFactory->getById($mediaId);

        // Check Permissions
        if (!$this->getUser()->checkEditable($media))
            throw new AccessDeniedException();

        $tags = $this->getSanitizer()->getStringArray('tag');

        if (count($tags) <= 0)
            throw new \InvalidArgumentException(__('No tags to assign'));

        foreach ($tags as $tag) {
            $media->unassignTag($this->tagFactory->tagFromString($tag));
        }

        $media->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Untagged %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);
    }
}
