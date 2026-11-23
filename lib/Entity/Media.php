<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */
namespace Xibo\Entity;

use Carbon\Carbon;
use Intervention\Image\ImageManager;
use Mimey\MimeTypes;
use OpenApi\Attributes as OA;
use Respect\Validation\Validator as v;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\CacheableUrlWindow;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Environment;
use Xibo\Helper\LibraryFile;
use Xibo\Helper\StashLock;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\DownloadUrlUnavailableException;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Media
 * @package Xibo\Entity
 */
#[OA\Schema]
class Media implements \JsonSerializable
{
    use EntityTrait;
    use TagLinkTrait;

    /** The longest side of a thumbnail, in pixels. */
    public const THUMBNAIL_SIZE = 320;

    /** The start of every cached image preview source's name, see getPreviewSourceCachePrefix(). */
    public const PREVIEW_SOURCE_CACHE_PREFIX = 'tn_src_';

    /** The lock held while a request makes a media item's thumbnail, followed by its mediaId. */
    private const THUMBNAIL_LOCK_PREFIX = 'locks/media_thumbnail/';

    /** Marks media whose thumbnail could not be made from its current file, so it is not retried for a day. */
    private const THUMBNAIL_FAILED_PREFIX = 'media_thumbnail_failed/';
    private const THUMBNAIL_FAILED_TTL = 86400;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Media ID')]
    public $mediaId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The ID of the User that owns this Media')]
    public $ownerId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Parent ID of this Media if it has been revised')]
    public $parentId;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Name of this Media')]
    public $name;

    /**
     * @var string
     */
    #[OA\Property(description: 'The module type of this Media')]
    public $mediaType;

    /**
     * @var string
     */
    #[OA\Property(description: 'The file name of the media as stored in the library')]
    public $storedAs;

    /**
     * @var string
     */
    #[OA\Property(description: 'The original file name as it was uploaded')]
    public $fileName;

    // Thing that might be referred to
    /**
     * @var TagLink[]
     */
    #[OA\Property(description: 'Tags associated with this Media, array of TagLink objects')]
    public $tags = [];

    /**
     * @var int
     */
    #[OA\Property(description: 'The file size in bytes')]
    public $fileSize;

    /**
     * @var int
     */
    #[OA\Property(description: 'The duration to use when assigning this media to a Layout widget')]
    public $duration = 0;

    /**
     * @var int
     */
    #[OA\Property(description: 'Flag indicating whether this media is valid.')]
    public $valid = 0;

    /**
     * @var int
     */
    #[OA\Property(description: 'DEPRECATED: Flag indicating whether this media is a system file or not')]
    public $moduleSystemFile = 0;

    /**
     * @var int
     */
    #[OA\Property(description: 'Timestamp indicating when this media should expire')]
    public $expires = 0;

    /**
     * @var int
     */
    #[OA\Property(description: 'Flag indicating whether this media is retired')]
    public $retired = 0;

    /**
     * @var int
     */
    #[OA\Property(description: 'Flag indicating whether this media has been edited and replaced with a newer file')]
    public $isEdited = 0;

    /**
     * @var string
     */
    #[OA\Property(description: 'A MD5 checksum of the stored media file')]
    public $md5;

    /**
     * @var string
     */
    #[OA\Property(description: 'The username of the User that owns this media')]
    public $owner;

    /**
     * @var string
     */
    #[OA\Property(description: 'A comma separated list of groups/users with permissions to this Media')]
    public $groupsWithPermissions;

    /**
     * @var string[]
     */
    #[OA\Property(
        description: 'An array of groups/users with permissions to this Media',
        type: 'array',
        items: new OA\Items(type: 'string')
    )]
    public $groupsWithPermissionsList = [];

    /**
     * @var int
     */
    #[OA\Property(description: 'A flag indicating whether this media has been released')]
    public $released = 1;

    /**
     * @var string
     */
    #[OA\Property(description: 'An API reference')]
    public $apiRef;

    /**
     * @var string
     */
    #[OA\Property(description: 'The datetime the Media was created')]
    public $createdDt;

    /**
     * @var string
     */
    #[OA\Property(description: 'The datetime the Media was last modified')]
    public $modifiedDt;

    /**
     * @var string
     */
    #[OA\Property(description: 'The option to enable the collection of Media Proof of Play statistics')]
    public $enableStat;

    /**
     * @var string
     */
    #[OA\Property(description: 'The orientation of the Media file')]
    public $orientation;

    /**
     * @var int
     */
    #[OA\Property(description: 'The width of the Media file')]
    public $width;

    /**
     * @var int
     */
    #[OA\Property(description: 'The height of the Media file')]
    public $height;

    /**
     * @var string|null
     */
    #[OA\Property(
        description: 'The version of the stored thumbnail, or null when none is stored.'
            . ' A video has one once it has a cover.'
    )]
    public $thumbnailVersion;

    /**
     * Where the file is while files are copied to new storage: 'local' until copied, else the configured store.
     * @var string|null
     */
    public $storageEngine;

    // Private
    /** @var TagLink[] */
    private $unlinkTags = [];
    /** @var TagLink[] */
    private $linkTags = [];
    private $requestOptions = [];

    /** The thumbnail version of the media this was cloned from, copied with its file. */
    private ?string $clonedThumbnailVersion = null;

    /** The storageEngine of the media this was cloned from, where its file is read from. */
    private ?string $clonedStorageEngine = null;

    /** Whether this save added the media, so its file is the first stored under its key. */
    private bool $isNewMedia = false;

    /** Whether this object stored a file under its key. */
    private bool $fileStored = false;

    /** Whether the last thumbnail failed for lack of memory, which another process may have. */
    private bool $thumbnailNeededMoreMemory = false;
    private $datesToFormat = ['expires'];
    // New file revision
    public $isSaveRequired;
    public $isRemote;

    public $cloned = false;
    public $newExpiry;
    public $alwaysCopy = false;

    /**
     * @var int
     */
    #[OA\Property(description: "The id of the Folder this Media belongs to")]
    public $folderId;

    /**
     * @var int
     */
    #[OA\Property(description: "The id of the Folder responsible for providing permissions for this Media")]
    public $permissionsFolderId;

    public $widgets = [];
    public $displayGroups = [];
    public $layoutBackgroundImages = [];
    private $permissions = [];

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var LibraryStorageServiceInterface
     */
    private $libraryStorageService;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param ConfigServiceInterface $config
     * @param MediaFactory $mediaFactory
     * @param PermissionFactory $permissionFactory
     * @param LibraryStorageServiceInterface $libraryStorageService
     */
    public function __construct(
        $store,
        $log,
        $dispatcher,
        $config,
        $mediaFactory,
        $permissionFactory,
        $libraryStorageService
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->config = $config;
        $this->mediaFactory = $mediaFactory;
        $this->permissionFactory = $permissionFactory;
        $this->libraryStorageService = $libraryStorageService;
    }

    public function __clone()
    {
        // Clear the ID's and all widget/displayGroup assignments
        $this->mediaId = null;
        $this->widgets = [];
        $this->displayGroups = [];
        $this->layoutBackgroundImages = [];
        $this->permissions = [];
        $this->tags = [];

        // We need to do something with the name
        $this->name = sprintf(
            __('Copy of %s on %s'),
            $this->name,
            Carbon::now()->format(DateFormatHelper::getSystemFormat())
        );

        // Set so that when we add, we copy the existing file in the library
        $this->fileName = $this->storedAs;
        $this->storedAs = null;
        $this->cloned = true;

        // A video's cover isn't copied, so neither is its thumbnail.
        $this->clonedThumbnailVersion = $this->mediaType === 'image' ? $this->thumbnailVersion : null;
        $this->thumbnailVersion = null;

        // The copy is written to the configured store, wherever the source's file is.
        $this->clonedStorageEngine = $this->storageEngine;
        $this->storageEngine = null;

        // Nothing is stored for the copy until save() copies the file.
        $this->isNewMedia = false;
        $this->fileStored = false;
    }

    /**
     * Get Id
     * @return int
     */
    public function getId(): int
    {
        return $this->mediaId;
    }

    /**
     * @return int
     */
    public function getPermissionFolderId(): int
    {
        return $this->permissionsFolderId;
    }

    /**
     * Get Owner Id
     * @return int
     */
    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /**
     * Get the MIME type for this media
     * @return string
     */
    public function getMimeType(): string
    {
        $mimeTypes = new MimeTypes();
        $ext = explode('.', $this->storedAs);
        return $mimeTypes->getMimeType($ext[count($ext) - 1]);
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner(int $ownerId)
    {
        $this->ownerId = $ownerId;
    }

    /**
     * @return int
     * @throws GeneralException
     */
    private function countUsages()
    {
        $this->load(['fullInfo' => true]);

        return count($this->widgets) + count($this->displayGroups) + count($this->layoutBackgroundImages);
    }

    /**
     * Is this media used
     * @param int $usages threshold
     * @return bool
     * @throws GeneralException
     */
    public function isUsed($usages = 0)
    {
        return $this->countUsages() > $usages;
    }

    /**
     * Validate
     * @param array $options
     * @throws GeneralException
     */
    public function validate($options)
    {
        if (!v::stringType()->notEmpty()->validate($this->mediaType)) {
            throw new InvalidArgumentException(__('Unknown Media Type'), 'type');
        }

        if (!v::stringType()->notEmpty()->length(1, 100)->validate($this->name)) {
            throw new InvalidArgumentException(__('The name must be between 1 and 100 characters'), 'name');
        }

        // Check the naming of this item to ensure it doesn't conflict
        $params = [];
        $checkSQL = 'SELECT `name`, `mediaId`, `apiRef` FROM `media` WHERE `name` = :name AND userid = :userId';

        if ($this->mediaId != 0) {
            $checkSQL .= ' AND mediaId <> :mediaId AND IsEdited = 0 ';
            $params['mediaId'] = $this->mediaId;
        }
        else if ($options['oldMedia'] != null && $this->name == $options['oldMedia']->name) {
            $checkSQL .= ' AND IsEdited = 0 ';
        }

        $params['name'] = $this->name;
        $params['userId'] = $this->ownerId;

        $result = $this->getStore()->select($checkSQL, $params);

        if (count($result) > 0) {
            // If the media is imported from a provider (ie Pixabay, etc), use it instead of importing again.
            if (isset($this->apiRef) && $this->apiRef === $result[0]['apiRef']) {
                $this->mediaId = intval($result[0]['mediaId']);
            } else if ($options['isMediaReassigned']) {
                $this->name = $this->getReassignedMediaName($result[0]['name']);
            } else {
                throw new DuplicateEntityException(__('Media you own already has this name. Please choose another.'));
            }
        }
    }

    /**
     * Load
     * @param array $options
     * @throws GeneralException
     */
    public function load($options = [])
    {
        if ($this->loaded || $this->mediaId == null) {
            return;
        }

        $options = array_merge([
            'deleting' => false,
            'fullInfo' => false
        ], $options);

        $this->getLog()->debug(sprintf('Loading Media. Options = %s', json_encode($options)));

        // Are we loading for a delete? If so load the child models, unless we're a module file in which case
        // we've no need.
        if ($this->mediaType !== 'module' && ($options['deleting'] || $options['fullInfo'])) {
            // Permissions
            $this->permissions = $this->permissionFactory->getByObjectId(get_class($this), $this->mediaId);
        }

        $this->loaded = true;
    }

    /**
     * Save this media
     * @param array $options
     * @throws ConfigurationException
     * @throws DuplicateEntityException
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function save($options = [])
    {
        $this->getLog()->debug('Save for mediaId: ' . $this->mediaId);

        $options = array_merge([
            'validate' => true,
            'oldMedia' => null,
            'deferred' => false,
            'saveTags' => true,
            'audit' => true,
            'isMediaReassigned' => false
        ], $options);

        if ($options['validate'] && $this->mediaType !== 'module') {
            $this->validate($options);
        }

        // Add or edit
        if ($this->mediaId == null || $this->mediaId == 0) {
            $this->add();
            $this->isNewMedia = true;

            // Always set force to true as we always want to save new files
            $this->isSaveRequired = true;

            if ($options['audit']) {
                $this->audit($this->mediaId, 'Added', [
                    'mediaId' => $this->mediaId,
                    'name' => $this->name,
                    'mediaType' => $this->mediaType,
                    'fileName' => $this->fileName,
                    'folderId' => $this->folderId
                ]);
            }
        } else {
            $this->edit();

            // If the media file is invalid, then force an update (only applies to module files)
            $expires = $this->getOriginalValue('expires');
            $this->isSaveRequired = $this->isSaveRequired
                || $this->valid == 0
                || ($this->isRemote && $expires > 0 && $expires < Carbon::now()->format('U'))
                || ($this->mediaType === 'module' && $this->moduleFileNeedsResave());

            if ($options['audit']) {
                $this->audit($this->mediaId, 'Updated', $this->getChangedProperties());
            }
        }

        if ($options['deferred']) {
            $this->getLog()->debug('Media Update deferred until later');
        } else {
            $this->getLog()->debug('Media Update happening now');

            // Call save file
            if ($this->isSaveRequired) {
                $this->saveFile();
            }
        }

        if ($options['saveTags']) {
            // Remove unwanted ones
            if (is_array($this->unlinkTags)) {
                foreach ($this->unlinkTags as $tag) {
                    $this->unlinkTagFromEntity('lktagmedia', 'mediaId', $this->mediaId, $tag->tagId);
                }
            }

            // Save the tags
            if (is_array($this->linkTags)) {
                foreach ($this->linkTags as $tag) {
                    $this->linkTagToEntity('lktagmedia', 'mediaId', $this->mediaId, $tag->tagId, $tag->value);
                }
            }
        }
    }

    /**
     * Save Async
     * @param array $options
     * @return $this
     * @throws ConfigurationException
     * @throws DuplicateEntityException
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    public function saveAsync($options = [])
    {
        $options = array_merge([
            'deferred' => true,
            'requestOptions' => []
        ], $options);
        $this->requestOptions = $options['requestOptions'];
        $this->save($options);

        return $this;
    }

    /**
     * Delete
     * @param array $options
     * @throws GeneralException
     */
    public function delete($options = [])
    {
        $options = array_merge([
            'rollback' => false
        ], $options);

        if ($options['rollback']) {
            $this->deleteRecord();
            $this->deleteFile();
            return;
        }

        $this->load(['deleting' => true]);

        // Prepare some contexts for auditing
        $auditMessage = 'Deleted';
        $auditContext = [
            'mediaId' => $this->mediaId,
            'name' => $this->name,
            'mediaType' => $this->mediaType,
            'fileName' => $this->fileName,
        ];

        // Should we bring back this item's parent?
        try {
            $parentMedia = $this->mediaFactory->getParentById($this->mediaId);

            // If the parent media is expired, delete it directly
            // Or if the current media is expired, delete the parent regardless of its own expiration
            if ((!empty($parentMedia->expires) && $parentMedia->expires < Carbon::now()->timestamp) ||
                (!empty($this->expires) && $this->expires < Carbon::now()->timestamp)
            ) {
                $parentMedia->delete();

                $auditMessage .= ' and deleted old revision';
                $auditContext['deletedParentMediaId'] = $parentMedia->mediaId;
            } else {
                // Otherwise, revert the parent media
                $parentMedia->isEdited = 0;
                $parentMedia->parentId = null;
                $parentMedia->save(['validate' => false, 'audit' => false]);

                $auditMessage .= ' and reverted old revision';
                $auditContext['revertedMediaId'] = $parentMedia->mediaId;
            }
        } catch (NotFoundException) {
            // No parent, this is fine.
        }

        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->delete();
        }

        $this->unlinkAllTagsFromEntity('lktagmedia', 'mediaId', $this->mediaId);

        $this->deleteRecord();
        $this->deleteFile();

        $this->audit($this->mediaId, $auditMessage, $auditContext);
    }

    /**
     * Add
     */
    private function add()
    {
        // The originalFileName column has limit of 254 characters
        // if the filename basename that we are about to save is still over the limit, attempt to strip query string
        // we cannot make any operations directly on $this->fileName, as that might be still needed to processDownloads
        $fileName = basename($this->fileName);
        if (strpos(basename($fileName), '?')) {
            $fileName = substr(basename($fileName), 0, strpos(basename($fileName), '?'));
        }

        // Sanitize what we have left and make it fit in the space we have.
        $fileName = substr(htmlspecialchars($fileName), 0, 254);

        $this->mediaId = $this->getStore()->insert('
            INSERT INTO `media` (`name`, `type`, duration, originalFilename, userID, retired, moduleSystemFile, released, apiRef, valid, `createdDt`, `modifiedDt`, `enableStat`, `folderId`, `permissionsFolderId`, `orientation`, `width`, `height`)
              VALUES (:name, :type, :duration, :originalFileName, :userId, :retired, :moduleSystemFile, :released, :apiRef, :valid, :createdDt, :modifiedDt, :enableStat, :folderId, :permissionsFolderId, :orientation, :width, :height)
        ', [
            'name' => $this->name,
            'type' => $this->mediaType,
            'duration' => $this->duration,
            'originalFileName' => $fileName,
            'userId' => $this->ownerId,
            'retired' => $this->retired,
            'moduleSystemFile' => (($this->moduleSystemFile) ? 1 : 0),
            'released' => $this->released,
            'apiRef' => $this->apiRef,
            'valid' => $this->valid,
            'createdDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'modifiedDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'enableStat' => $this->enableStat,
            'folderId' => ($this->folderId === null) ? 1 : $this->folderId,
            'permissionsFolderId' => ($this->permissionsFolderId == null) ? 1 : $this->permissionsFolderId,
            'orientation' => $this->orientation,
            'width' => ($this->width === null) ? null : $this->width,
            'height' => ($this->height === null) ? null : $this->height,
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        $sql = '
          UPDATE `media`
            SET `name` = :name,
                duration = :duration,
                retired = :retired,
                moduleSystemFile = :moduleSystemFile,
                editedMediaId = :editedMediaId,
                isEdited = :isEdited,
                userId = :userId,
                released = :released,
                apiRef = :apiRef,
                modifiedDt = :modifiedDt,
                `enableStat` = :enableStat,
                expires = :expires,
                folderId = :folderId,
                permissionsFolderId = :permissionsFolderId,
                orientation = :orientation,
                width = :width,
                height = :height
           WHERE mediaId = :mediaId
        ';

        $params = [
            'name' => $this->name,
            'duration' => $this->duration,
            'retired' => $this->retired,
            'moduleSystemFile' => $this->moduleSystemFile,
            'editedMediaId' => $this->parentId,
            'isEdited' => $this->isEdited,
            'userId' => $this->ownerId,
            'released' => $this->released,
            'apiRef' => $this->apiRef,
            'mediaId' => $this->mediaId,
            'modifiedDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'enableStat' => $this->enableStat,
            'expires' => $this->expires,
            'folderId' => $this->folderId,
            'permissionsFolderId' => $this->permissionsFolderId,
            'orientation' => $this->orientation,
            'width' => ($this->width === null) ? null : $this->width,
            'height' => ($this->height === null) ? null : $this->height,
        ];

        $this->getStore()->update($sql, $params);
    }

    /**
     * Delete record
     */
    private function deleteRecord()
    {
        // Delete direct assignments to displays. This will be module files assigned by widgets
        // no need to notify the display as the next time it collects is sufficient to delete these
        $this->getStore()->update('DELETE FROM `display_media` WHERE mediaID = :mediaId', [
            'mediaId' => $this->mediaId
        ]);

        // Delete the media entry itself
        $this->getStore()->update('DELETE FROM `media` WHERE mediaID = :mediaId', ['mediaId' => $this->mediaId]);
    }

    /**
     * Save File to Library
     *  works on files that are already in the File system
     * @throws ConfigurationException
     */
    public function saveFile()
    {
        $libraryFolder = $this->config->getSetting('LIBRARY_LOCATION');

        // Work out the extension
        $lastPeriod = strrchr($this->fileName, '.');

        // Determine the save name
        if ($lastPeriod === false) {
            $saveName = $this->mediaId;
        } else {
            $saveName = $this->mediaId . '.' . strtolower(substr($lastPeriod, 1));
        }

        if ($this->getUnmatchedProperty('urlDownload', false) === true) {
            // for upload via URL, handle cases where URL do not have specified extension in url
            // we either have a long string after lastPeriod or nothing
            $extension = $this->getUnmatchedProperty('extension');
            if (isset($extension) && (strlen($lastPeriod) > 3 || $lastPeriod === false)) {
                $saveName = $this->mediaId . '.' . $extension;
            }

            // if needed strip any not needed characters from the storedAs, this should be just <mediaId>.<extension>
            if (strpos(basename($saveName), '?')) {
                $saveName = substr(basename($saveName), 0, strpos(basename($saveName), '?'));
            }

            $this->storedAs = $saveName;
        }

        $this->getLog()->debug('saveFile for "' . $this->name . '" [' . $this->mediaId . '] with storedAs = "'
            . $this->storedAs . '", fileName = "' . $this->fileName . '" to "' . $saveName . '". Always Copy = "'
            . $this->alwaysCopy . '", Cloned = "' . $this->cloned . '"');

        // If the storesAs is empty, then set it to be the moved file name
        if (empty($this->storedAs) && !$this->alwaysCopy) {
            // We could be a fresh file entirely, or we could be a clone
            if ($this->cloned) {
                $this->getLog()->debug('Copying cloned file: ' . $this->fileName);
                // Copies the file in storage, as a clone already carries the source's file details.
                $this->assertNewFileKeyFree($saveName);
                try {
                    $this->journalledWrite(
                        $saveName,
                        fn () => $this->copyFileFrom($this->clonedStorageEngine, $this->fileName, $saveName)
                    );
                } catch (GeneralException $exception) {
                    throw new ConfigurationException(__('Problem copying file in the Library Folder'));
                }
                $this->fileStored = true;
                $this->storedAs = $saveName;
                if ($this->clonedThumbnailVersion !== null) {
                    $this->copyThumbnail($this->fileName, $saveName, $this->clonedThumbnailVersion);
                }
            } else {
                $this->getLog()->debug('Moving temporary file: ' . $libraryFolder . 'temp/' . $this->fileName);
                // Move the file into the library
                $this->assessAndStoreLocalFile(
                    $libraryFolder . 'temp' . DIRECTORY_SEPARATOR . $this->fileName,
                    $saveName,
                    __('Problem moving uploaded file into the Library Folder')
                );
                $this->storedAs = $saveName;
            }
        } else {
            // We have pre-defined where we want this to be stored
            if (empty($this->storedAs)) {
                // Assume we want to set this automatically (i.e. we are set to always copy)
                $this->storedAs = $saveName;
            }

            if ($this->isRemote) {
                $this->getLog()->debug(
                    'Moving temporary file: ' . $libraryFolder . 'temp/' . basename($this->name)
                );

                // Move the file into the library
                $this->assessAndStoreLocalFile(
                    $libraryFolder . 'temp' . DIRECTORY_SEPARATOR . basename($this->name),
                    $this->storedAs,
                    __('Problem moving downloaded file into the Library Folder')
                );
            } else {
                $this->getLog()->debug('Copying specified file: ' . $this->fileName);

                // Finalize a copy, so the caller's file is left in place.
                $copyPath = $this->copyToTemp($this->fileName, $libraryFolder);
                try {
                    $this->assessAndStoreLocalFile(
                        $copyPath,
                        $this->storedAs,
                        __('This media has expired and cannot be replaced.')
                    );
                } finally {
                    @unlink($copyPath);
                }
            }
        }

        // Set to valid
        $this->valid = 1;

        // Update the MD5 and storedAs to suit
        $this->getStore()->update('
            UPDATE `media` 
                SET md5 = :md5,
                    fileSize = :fileSize,
                    storedAs = :storedAs,
                    expires = :expires,
                    released = :released,
                    orientation = :orientation,
                    width = :width,
                    height = :height,
                    valid = :valid 
             WHERE mediaId = :mediaId
        ', [
            'fileSize' => $this->fileSize,
            'md5' => $this->md5,
            'storedAs' => $this->storedAs,
            'expires' => $this->expires,
            'released' => $this->released,
            'orientation' => $this->orientation,
            'valid' => $this->valid,
            'width' => ($this->width === null) ? null : $this->width,
            'height' => ($this->height === null) ? null : $this->height,
            'mediaId' => $this->mediaId,
        ]);
    }

    /**
     * Copy a local file into the library's temp folder
     * @throws ConfigurationException
     */
    private function copyToTemp(string $sourcePath, string $libraryFolder): string
    {
        $copyPath = $libraryFolder . 'temp' . DIRECTORY_SEPARATOR . uniqid('copy_', true);
        if (!@copy($sourcePath, $copyPath)) {
            $this->getLog()->error(sprintf('Cannot copy %s to %s', $sourcePath, $copyPath));
            throw new ConfigurationException(__('This media has expired and cannot be replaced.'));
        }

        return $copyPath;
    }

    /**
     * Compute md5/fileSize/image-dimensions from a file
     * @throws ConfigurationException
     */
    private function assessAndStoreLocalFile(
        string $localSourcePath,
        string $key,
        string $failureMessage
    ): void {
        if (!file_exists($localSourcePath)) {
            $this->getLog()->error('Cannot find local source file: ' . $localSourcePath);
            throw new ConfigurationException($failureMessage);
        }

        $this->md5 = md5_file($localSourcePath);
        $this->fileSize = filesize($localSourcePath);
        $this->assessDimensions($localSourcePath);

        // Made while the source is still a local file, as storing it removes it.
        $thumbnailPath = $this->mediaType === 'image' ? $this->createThumbnailFile($localSourcePath, $key) : null;

        try {
            $this->assertNewFileKeyFree($key);

            try {
                $this->getJournal()->storeLocalFile($localSourcePath, $key);
            } catch (GeneralException $exception) {
                $this->getLog()->error(
                    'Problem finalizing ' . $localSourcePath . ' to ' . $key . ': ' . $exception->getMessage()
                );
                throw new ConfigurationException($failureMessage);
            }
        } catch (GeneralException $exception) {
            @unlink($localSourcePath);
            if ($thumbnailPath !== null) {
                @unlink($thumbnailPath);
            }
            throw $exception;
        }
        $this->fileStored = true;

        if ($this->mediaType === 'image') {
            $this->storeThumbnail($thumbnailPath, $key, $this->md5);
        }
    }

    /**
     * Refuses to overwrite a file already stored under a new media item's key, as it may belong to another CMS sharing
     * the storage. A local library is this CMS's own, so a leftover file there is overwritten, as before.
     * @throws ConfigurationException
     */
    private function assertNewFileKeyFree(string $key): void
    {
        if (!$this->isNewMedia || $this->mediaType === 'module' || $this->libraryStorageService->hasLocalFiles()) {
            return;
        }

        try {
            $exists = $this->libraryStorageService->exists($key);
        } catch (GeneralException $exception) {
            $this->getLog()->error('Cannot check library storage for ' . $key . ': ' . $exception->getMessage());
            throw new ConfigurationException(__('Problem moving uploaded file into the Library Folder'));
        }

        if ($exists) {
            $this->getLog()->critical('A library file already exists for new media ' . $this->mediaId . ' at '
                . $key . '. Is another CMS using the same library storage, or was the database restored? '
                . 'Refusing to overwrite it.');
            throw new ConfigurationException(
                __('A file for this media already exists in library storage. Please contact your administrator.')
            );
        }
    }

    /**
     * @param string $filePath a real local path to the file (the local source, read before it's
     *                         finalized into permanent storage - see assessAndStoreLocalFile())
     */
    private function assessDimensions(string $filePath): void
    {
        if ($this->mediaType === 'image' || ($this->mediaType === 'module' && $this->moduleSystemFile === 0)) {
            list($imgWidth, $imgHeight) = @getimagesize($filePath);

            $resizeThreshold = $this->config->getSetting('DEFAULT_RESIZE_THRESHOLD');
            $resizeLimit = $this->config->getSetting('DEFAULT_RESIZE_LIMIT');

            $this->width = $imgWidth;
            $this->height = $imgHeight;
            $this->orientation = ($imgWidth >= $imgHeight) ? 'landscape' : 'portrait';

            // Media released set to 0 for large size images
            // if image size is greater than Resize Limit then we flag that image as too big
            if ($resizeLimit > 0 && ($imgWidth > $resizeLimit || $imgHeight > $resizeLimit)) {
                $this->released = 2;
                $this->getLog()->debug('Image size is too big. MediaId '. $this->mediaId);
            } elseif ($resizeThreshold > 0) {
                if ($imgWidth > $imgHeight) { // 'landscape';
                    if ($imgWidth <= $resizeThreshold) {
                        $this->released = 1;
                    } else {
                        if ($resizeThreshold > 0) {
                            $this->released = 0;
                            $this->getLog()->debug('Image exceeded threshold, released set to 0. MediaId '. $this->mediaId);
                        }
                    }
                } else { // 'portrait';
                    if ($imgHeight <= $resizeThreshold) {
                        $this->released = 1;
                    } else {
                        if ($resizeThreshold > 0) {
                            $this->released = 0;
                            $this->getLog()->debug('Image exceeded threshold, released set to 0. MediaId '. $this->mediaId);
                        }
                    }
                }
            }
        }
    }

    /**
     * The storage key of a video's cover image.
     * @param int|string $mediaId
     * @return string
     */
    public static function getVideoCoverKey($mediaId): string
    {
        return $mediaId . '_videocover.png';
    }

    /**
     * The mediaId a video cover key (see getVideoCoverKey()) belongs to, or null if $key isn't a cover key.
     */
    public static function getMediaIdFromVideoCoverKey(string $key): ?int
    {
        return preg_match('/^(\d+)_videocover\.png$/', $key, $matches) ? (int)$matches[1] : null;
    }

    /**
     * Whether $key is the video cover of a media id in $mediaIds (an array keyed by mediaId).
     */
    public static function isVideoCoverOf(string $key, array $mediaIds): bool
    {
        $mediaId = self::getMediaIdFromVideoCoverKey($key);
        return $mediaId !== null && isset($mediaIds[$mediaId]);
    }

    /**
     * Release an image from image processing
     * @param $md5
     * @param $fileSize
     * @param $height
     * @param $width
     */
    public function release($md5, $fileSize, $height, $width)
    {
        // Update the img record
        $this->getStore()->update('UPDATE `media` SET md5 = :md5, fileSize = :fileSize, released = :released, height = :height, width = :width, modifiedDt = :modifiedDt WHERE mediaId = :mediaId', [
            'fileSize' => $fileSize,
            'md5' => $md5,
            'released' => 1,
            'mediaId' => $this->mediaId,
            'height' => $height,
            'width' => $width,
            'modifiedDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat())
        ]);
    }

    /**
     * Store a new cover for this video from a local file (removed once stored), and the thumbnail made from it.
     * @throws GeneralException
     */
    public function storeVideoCover(string $localPath): void
    {
        $key = self::getVideoCoverKey($this->mediaId);
        $version = md5_file($localPath);

        // A video has a cover exactly when it has a thumbnail, so a cover without one isn't stored.
        $thumbnailPath = $this->createThumbnailFile($localPath, $key)
            ?? throw new InvalidArgumentException(__('Invalid image data'), 'image');

        try {
            $this->getJournal()->storeLocalFile($localPath, $key);
        } catch (GeneralException $exception) {
            @unlink($thumbnailPath);
            throw $exception;
        }

        $this->storeThumbnail($thumbnailPath, $key, $version);
        if ($this->thumbnailVersion !== $version) {
            throw new GeneralException('Problem storing the thumbnail of ' . $key);
        }
        $this->deleteLocalCopies($key, true);
    }

    /**
     * The key the thumbnail is made from: the file of an image, the cover of a video.
     */
    public function getThumbnailSourceKey(): string
    {
        return self::getThumbnailSourceKeyFor($this->mediaType, $this->mediaId, $this->storedAs);
    }

    private static function getThumbnailSourceKeyFor(string $mediaType, $mediaId, ?string $storedAs): string
    {
        return $mediaType === 'video' ? self::getVideoCoverKey($mediaId) : (string)$storedAs;
    }

    /**
     * The key of the stored thumbnail, or null if none is stored.
     */
    public function getThumbnailKey(): ?string
    {
        return $this->thumbnailVersion === null
            ? null
            : self::getThumbnailKeyFor($this->mediaType, $this->mediaId, $this->storedAs, $this->thumbnailVersion);
    }

    /**
     * getThumbnailKey() for a media row.
     */
    public static function getThumbnailKeyFor(string $mediaType, $mediaId, ?string $storedAs, string $version): string
    {
        return self::getThumbnailKeyPrefix(self::getThumbnailSourceKeyFor($mediaType, $mediaId, $storedAs)) . $version;
    }

    public function getThumbnailContentType(): string
    {
        return self::getThumbnailContentTypeFor($this->getThumbnailSourceKey());
    }

    /**
     * A thumbnail keeps its source's format when the source's extension names one, and is a PNG otherwise.
     */
    private static function getThumbnailContentTypeFor(string $sourceKey): string
    {
        return match (strtolower(pathinfo($sourceKey, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    /**
     * The local library, when this media waits to be copied and its thumbnail is still there; otherwise null,
     * for the configured store.
     * @throws GeneralException
     */
    public function getLocalThumbnailStorage(): ?LibraryStorageServiceInterface
    {
        $key = $this->getThumbnailKey();
        if ($key === null || $this->storageEngine === null) {
            return null;
        }

        $storage = $this->getStorageFor($this->storageEngine);
        return $storage !== $this->libraryStorageService && $storage->exists($key) ? $storage : null;
    }

    /**
     * A URL the browser can load the stored thumbnail from directly, the same for every request in a window so it is
     * cached, or null to use the thumbnail route.
     */
    public function getThumbnailUrl(int $now): ?string
    {
        $key = $this->getThumbnailKey();
        if ($key === null) {
            return null;
        }

        try {
            // A thumbnail still in the local library has no URL, so the thumbnail route sends it.
            return ($this->getLocalThumbnailStorage() ?? $this->libraryStorageService)->getUserDownloadUrl(
                $key,
                $this->getThumbnailContentType(),
                'inline',
                CacheableUrlWindow::start($now),
                CacheableUrlWindow::cacheControl()
            );
        } catch (DownloadUrlUnavailableException $exception) {
            $this->getLog()->error('getThumbnailUrl: ' . $exception->getMessage());
            return null;
        }
    }

    /**
     * Makes and stores the thumbnail of media that has none, or whose stored $staleVersion is missing, under a lock
     * so only one request makes it, adopting a thumbnail already stored under the current version.
     * @return bool false if another request held the lock throughout, as that request is making it
     * @throws NotFoundException if the source is gone
     * @throws InvalidArgumentException if no thumbnail can be made from the source
     * @throws GeneralException if the thumbnail can't be stored
     */
    public function fillThumbnail(PoolInterface $pool, ?string $staleVersion = null, int $tries = 5): bool
    {
        $failed = $pool->getItem(self::THUMBNAIL_FAILED_PREFIX . $this->mediaId . '/' . ($this->md5 ?: 'none'));
        if ($failed->isHit()) {
            throw new InvalidArgumentException((string)$failed->get());
        }

        $lock = new StashLock(
            $pool,
            self::THUMBNAIL_LOCK_PREFIX . $this->mediaId,
            $this->getLog()->getLoggerInterface()
        );
        if (!$lock->acquire(60, 1000, $tries)) {
            return false;
        }

        $release = fn () => $lock->release();
        $this->thumbnailNeededMoreMemory = false;
        try {
            // Another request may have stored it while this one waited.
            $this->thumbnailVersion = $this->getStore()->select(
                'SELECT `thumbnailVersion` FROM `media` WHERE `mediaId` = :mediaId',
                ['mediaId' => $this->mediaId]
            )[0]['thumbnailVersion'] ?? null;

            if ($this->thumbnailVersion === $staleVersion
                && ($staleVersion === null || !$this->libraryStorageService->exists($this->getThumbnailKey()))
            ) {
                $this->makeThumbnailFromStorage($staleVersion !== null);
            }
        } catch (\Throwable $exception) {
            $release();
            if ($exception instanceof InvalidArgumentException && !$this->thumbnailNeededMoreMemory) {
                $failed->set($exception->getMessage());
                $failed->expiresAfter(self::THUMBNAIL_FAILED_TTL);
                $pool->save($failed);
            }
            throw $exception;
        }

        // Released once the version is committed, so a waiting request reads it.
        $this->getStore()->afterCommit($release);
        return true;
    }

    /**
     * Makes the thumbnail from the stored source and stores it, or adopts one already stored under its version.
     * @throws GeneralException
     */
    private function makeThumbnailFromStorage(bool $isRemake): void
    {
        $sourceKey = $this->getThumbnailSourceKey();
        $version = null;

        if ($this->mediaType === 'image') {
            $this->thumbnailNeededMoreMemory = !self::canDecode((int)$this->width, (int)$this->height);
            if ($this->thumbnailNeededMoreMemory || $this->exceedsResizeLimit((int)$this->width, (int)$this->height)) {
                throw new InvalidArgumentException(__('Image too large'));
            }

            $version = empty($this->md5) ? null : $this->md5;
            $key = self::getThumbnailKeyPrefix($sourceKey) . $version;
            if (!$isRemake && $version !== null && $this->libraryStorageService->exists($key)) {
                $this->setThumbnailVersion($version);
                return;
            }
        }

        // An image is read from where its file is; a video's cover is always in the configured store.
        $sourceStorage = $sourceKey === $this->storedAs
            ? $this->getStorageFor($this->storageEngine)
            : $this->libraryStorageService;
        $sourcePath = $sourceStorage->downloadToTemp($sourceKey);
        try {
            $version ??= md5_file($sourcePath);
            $thumbnailPath = $this->createThumbnailFile($sourcePath, $sourceKey);
        } finally {
            $sourceStorage->releaseTemp($sourcePath);
        }

        if ($thumbnailPath === null) {
            $this->setThumbnailVersion(null);
            throw new InvalidArgumentException(__('Invalid image data'));
        }

        $this->storeThumbnail($thumbnailPath, $sourceKey, $version);
        if ($this->thumbnailVersion !== $version) {
            throw new GeneralException('Thumbnail could not be made for media ' . $this->mediaId);
        }
    }

    private function exceedsResizeLimit(int $width, int $height): bool
    {
        $resizeLimit = (int)$this->config->getSetting('DEFAULT_RESIZE_LIMIT');
        return $resizeLimit > 0 && ($width > $resizeLimit || $height > $resizeLimit);
    }

    /**
     * Whether GD, at about 5 bytes a pixel, can decode a $width x $height image in the memory left.
     */
    private static function canDecode(int $width, int $height): bool
    {
        $limit = Environment::getMemoryLimitBytes();
        return $limit <= 0 || $width * $height * 5 < $limit - memory_get_usage(true);
    }

    /**
     * A thumbnail of a local image in the library temp folder, or null if it can't be read or, for an image, is over
     * the resize limit.
     */
    private function createThumbnailFile(string $sourcePath, string $sourceKey): ?string
    {
        $size = @getimagesize($sourcePath);
        if ($size === false) {
            $this->getLog()->debug('No thumbnail for media ' . $this->mediaId . ': not a readable image.');
            return null;
        }

        // A cover has already been decoded by the request that uploaded it.
        if ($this->mediaType === 'image' && $this->exceedsResizeLimit($size[0], $size[1])) {
            $this->getLog()->debug('No thumbnail for media ' . $this->mediaId . ': over the resize limit.');
            return null;
        }

        // Running out of memory can't be caught, and would fail the whole request.
        if (!self::canDecode($size[0], $size[1])) {
            $this->thumbnailNeededMoreMemory = true;
            $this->getLog()->error('No thumbnail for media ' . $this->mediaId . ': too large to decode in the '
                . 'memory available.');
            return null;
        }

        $path = $this->config->getSetting('LIBRARY_LOCATION') . 'temp' . DIRECTORY_SEPARATOR
            . uniqid('thumbnail_', true);
        try {
            // Upsizing is allowed, as before.
            ImageManager::gd()
                ->read($sourcePath)
                ->scale(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE)
                ->encodeByMediaType(self::getThumbnailContentTypeFor($sourceKey))
                ->save($path);
        } catch (\Throwable $exception) {
            $this->getLog()->error('Cannot make a thumbnail for media ' . $this->mediaId . ': '
                . $exception->getMessage());
            @unlink($path);
            return null;
        }

        return $path;
    }

    /**
     * Stores a thumbnail made from $sourceKey under $version and records it, or records that there is none when it
     * wasn't made or can't be stored.
     */
    private function storeThumbnail(?string $thumbnailPath, string $sourceKey, string $version): void
    {
        if ($thumbnailPath === null) {
            $this->setThumbnailVersion(null);
            return;
        }

        $key = self::getThumbnailKeyPrefix($sourceKey) . $version;
        $journal = $this->getJournal();
        try {
            $journalId = $journal->storeLocalFile($thumbnailPath, $key, false);
        } catch (GeneralException $exception) {
            $this->getLog()->error('Cannot store thumbnail ' . $key . ': ' . $exception->getMessage());
            $this->setThumbnailVersion(null);
            return;
        }
        $this->setThumbnailVersion($version);

        // The upload record is cleared once the thumbnail version is saved.
        $journal->clearUploadAfterCommit($journalId);
    }

    /**
     * Copies the thumbnail of a cloned image, leaving the clone without one if it can't be copied.
     */
    private function copyThumbnail(string $sourceKey, string $destKey, string $version): void
    {
        $key = self::getThumbnailKeyPrefix($destKey) . $version;
        $sourceThumbnail = self::getThumbnailKeyPrefix($sourceKey) . $version;
        // A file waiting to be copied may still have its thumbnail in the local library.
        $engine = $this->clonedStorageEngine === LibraryStorageRegistryInterface::LOCAL_ENGINE
            && $this->getStorageFor($this->clonedStorageEngine)->exists($sourceThumbnail)
                ? $this->clonedStorageEngine
                : null;
        try {
            $this->journalledWrite($key, fn () => $this->copyFileFrom($engine, $sourceThumbnail, $key));
        } catch (GeneralException $exception) {
            $this->getLog()->error('Cannot copy thumbnail to ' . $key . ': ' . $exception->getMessage());
            return;
        }
        $this->setThumbnailVersion($version);
    }

    /**
     * Writes $key with $write, journalled so a file written for a transaction that then rolls back is removed by the
     * next tidy.
     * @throws GeneralException
     */
    private function journalledWrite(string $key, callable $write): void
    {
        $journal = $this->getJournal();
        $journalId = $journal->recordUpload($key);
        $write();
        $journal->clearUploadAfterCommit($journalId);
    }

    /**
     * Where a file recorded with $storageEngine is: the configured store unless it waits to be copied to it.
     */
    private function getStorageFor(?string $storageEngine): LibraryStorageServiceInterface
    {
        return $storageEngine === null
            ? $this->libraryStorageService
            : $this->mediaFactory->getStorageFor($storageEngine);
    }

    /**
     * Copies $sourceKey from the store $storageEngine names to $destKey in the configured store.
     * @throws GeneralException
     */
    private function copyFileFrom(?string $storageEngine, string $sourceKey, string $destKey): void
    {
        $source = $this->getStorageFor($storageEngine);
        if ($source === $this->libraryStorageService) {
            $this->libraryStorageService->copy($sourceKey, $destKey);
            return;
        }

        $path = $source->downloadToTemp($sourceKey);
        try {
            $copy = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/copy_' . bin2hex(random_bytes(8));
            if (!copy($path, $copy)) {
                throw new GeneralException('Problem copying file in the Library Folder: ' . $sourceKey);
            }
        } finally {
            $source->releaseTemp($path);
        }
        $this->libraryStorageService->moveFromLocalPath($copy, $destKey);
    }

    /**
     * The journal for this media's storage.
     */
    private function getJournal(): LibraryFileJournal
    {
        return new LibraryFileJournal($this->getStore(), $this->libraryStorageService, LibraryAsset::MEDIA);
    }

    /**
     * Records the stored thumbnail's version, deleting the one it replaces once committed.
     */
    private function setThumbnailVersion(?string $version): void
    {
        if ($version === $this->thumbnailVersion) {
            return;
        }

        $oldKey = $this->getThumbnailKey();
        if ($oldKey !== null) {
            (new LibraryFileJournal($this->getStore(), $this->libraryStorageService, LibraryAsset::MEDIA))
                ->deleteAfterCommit($oldKey, $this->getLog()->getLoggerInterface());
        }

        $this->getStore()->update(
            'UPDATE `media` SET `thumbnailVersion` = :thumbnailVersion WHERE `mediaId` = :mediaId',
            [
                'thumbnailVersion' => $version,
                'mediaId' => $this->mediaId,
            ]
        );
        $this->thumbnailVersion = $version;
    }

    /**
     * Delete a Library File
     */
    private function deleteFile()
    {
        // Make sure storedAs isn't null
        if ($this->storedAs == null) {
            $this->getLog()->error(sprintf('Deleting media [%s] with empty stored as. Skipping library file delete.', $this->name));
            return;
        }

        // A new media item that never stored a file owns nothing under its key, so nothing is deleted.
        if ($this->isNewMedia && !$this->fileStored) {
            $this->getLog()->debug('Deleting new media ' . $this->mediaId . ' that never stored a file.'
                . ' Leaving whatever is stored under ' . $this->storedAs . ' alone.');
            return;
        }

        // Deletes are journalled with the row and carried out after commit, so a failure is retried by the next tidy.
        $storedAs = $this->storedAs;
        $mediaId = $this->mediaId;
        (new LibraryFileJournal(
            $this->getStore(),
            $this->getStorageFor($this->storageEngine),
            LibraryAsset::MEDIA
        ))->deleteAfterCommit($storedAs, $this->getLog()->getLoggerInterface());

        $journal = new LibraryFileJournal($this->getStore(), $this->libraryStorageService, LibraryAsset::MEDIA);
        $keys = [];
        // A file waiting to be copied may already be in storage, uploaded but not yet recorded.
        if ($this->storageEngine === LibraryStorageRegistryInterface::LOCAL_ENGINE) {
            $keys[] = $storedAs;
        }
        if ($this->mediaType === 'video') {
            $keys[] = self::getVideoCoverKey($mediaId);
        }
        if ($this->thumbnailVersion !== null) {
            $keys[] = $this->getThumbnailKey();
        }
        foreach ($keys as $key) {
            $journal->deleteAfterCommit($key, $this->getLog()->getLoggerInterface());
        }

        // Replicas may still hold thumbnails cached locally before they were stored.
        $this->getStore()->afterCommit(function () use ($storedAs, $mediaId) {
            $this->deleteLocalCopies($storedAs);
            $this->deleteLocalCopies(self::getVideoCoverKey($mediaId));
        });
    }

    /**
     * The start of a library file's thumbnail keys, which end with their version (see getThumbnailKey()).
     */
    public static function getThumbnailKeyPrefix(string $key): string
    {
        return 'tn_' . $key . '_';
    }

    /**
     * Locally cached copies of a library file kept as the source for image previews, when the library isn't on
     * local disk, are named with this prefix plus a version of the file (see WidgetDownloader::imagePreview()).
     */
    public static function getPreviewSourceCachePrefix(string $key): string
    {
        return self::PREVIEW_SOURCE_CACHE_PREFIX . $key . '_';
    }

    /**
     * Deletes cached versions under $prefix except $keepPath and files another request is still writing.
     * @throws InvalidArgumentException if $prefix would resolve outside the library
     */
    public static function deleteCachedVersions(
        string $libraryLocation,
        string $prefix,
        ?string $keepPath = null
    ): void {
        $prefixPath = LibraryFile::resolve($libraryLocation, $prefix);
        $paths = glob(preg_replace('/([*?\[\]])/', '\\\\$1', $prefixPath) . '*') ?: [];
        if (str_ends_with($prefixPath, '_') && str_starts_with($prefix, 'tn_')) {
            // tn_{key}: the prefix without its trailing separator.
            $paths[] = substr($prefixPath, 0, -1);
        }

        foreach ($paths as $path) {
            if ($path === $keepPath
                || (str_ends_with($path, '.part') && @filemtime($path) > time() - 3600)
                || !is_file($path)
            ) {
                continue;
            }
            @unlink($path);
        }
    }

    /**
     * Deletes the local copies of a library file's thumbnails and preview sources, or only its preview sources.
     */
    private function deleteLocalCopies(string $key, bool $isPreviewSourcesOnly = false): void
    {
        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');
        try {
            if (!$isPreviewSourcesOnly) {
                self::deleteCachedVersions($libraryLocation, self::getThumbnailKeyPrefix($key));
            }
            self::deleteCachedVersions($libraryLocation, self::getPreviewSourceCachePrefix($key));
        } catch (InvalidArgumentException $exception) {
            $this->getLog()->error('Problem resolving local copies of ' . $key . ': ' . $exception->getMessage());
        }
    }

    /**
     * Download URL
     * @return string
     */
    public function downloadUrl()
    {
        return $this->fileName;
    }

    /**
     * Whether a module file is missing or the wrong size, keeping it when storage can't be checked.
     */
    private function moduleFileNeedsResave(): bool
    {
        try {
            $size = $this->libraryStorageService->size($this->storedAs);
        } catch (NotFoundException) {
            return true;
        } catch (GeneralException $exception) {
            $this->getLog()->error('Cannot check module file ' . $this->storedAs . ': ' . $exception->getMessage());
            return false;
        }

        return $this->fileSize > 0 && $size !== (int)$this->fileSize;
    }

    /**
     * Download Sink
     * @return string
     */
    public function downloadSink($temp = true)
    {
        return $this->config->getSetting('LIBRARY_LOCATION')
            . ($temp ? 'temp' . DIRECTORY_SEPARATOR : '')
            . basename($this->name);
    }

    /**
     * Get optional options for downloading media files
     * @return array
     */
    public function downloadRequestOptions()
    {
        return $this->requestOptions;
    }

    /**
     * Update Media duration.
     * This is called on processDownloads when uploading video/audio from url
     * Real duration can be determined in determineRealDuration function in MediaFactory
     * @param int $realDuration
     * @return Media
     */
    public function updateDuration(int $realDuration): Media
    {
        $this->getLog()->debug('Updating duration for MediaId '. $this->mediaId);

        $this->getStore()->update('UPDATE `media` SET duration = :duration WHERE mediaId = :mediaId', [
            'duration' => $realDuration,
            'mediaId' => $this->mediaId
        ]);

        $this->duration = $realDuration;

        return $this;
    }

    /**
     * Update Media orientation.
     * For videos from Library connectors, update the orientation once we have the cover image saved.
     * @param int $width
     * @param int $height
     * @return Media
     */
    public function updateOrientation(int $width, int $height): Media
    {
        $this->getLog()->debug('Updating orientation and resolution for MediaId '. $this->mediaId);

        $this->width = $width;
        $this->height = $height;
        $this->orientation = ($width >= $height) ? 'landscape' : 'portrait';

        $this->getStore()->update('
            UPDATE `media` SET orientation = :orientation, width = :width, height = :height
             WHERE mediaId = :mediaId
        ', [
            'orientation' => $this->orientation,
            'width' => $this->width,
            'height' => $this->height,
            'mediaId' => $this->mediaId
        ]);

        return $this;
    }

    /**
     * Get the reassigned media name
     * @param $media
     * @return string
     */
    private function getReassignedMediaName($media): string
    {
        // Append a numeric suffix if the target user already has a media item with this name
        // However, we first need to check similar names to ensure that there's no conflict in renaming the media.

        // Separate the base and file extension
        $separator = strrpos($media, '.');

        [$base, $ext] = $separator
            ? [substr($media, 0, $separator), substr($media, $separator)]
            : [$media, ''];

        // Get all similar names
        $params = [];
        $checkSQL = 'SELECT `name` FROM `media` WHERE `name` LIKE :name AND userid = :userId';

        $params['name'] = $base . '%';
        $params['userId'] = $this->ownerId;

        $entries = $this->getStore()->select($checkSQL, $params);

        $count = [];

        foreach ($entries as $entry) {
            $name = $entry['name'];

            if ($name === $base . $ext) {
                $count[] = 0;
                continue;
            }

            // Check for numeric suffix like (1), (2), etc. to get the correct subsequent suffix
            $suffix = trim(substr($name, strlen($base), strlen($name) - strlen($base) - strlen($ext)));

            if (strlen($suffix) > 2 && $suffix[0] === '(' && $suffix[-1] === ')') {
                $count[] = (int) substr($suffix, 1, -1);
            }
        }

        $i = 0;

        while (in_array($i, $count, true)) {
            $i++;
        }

        return $base . ($i > 0 ? " ($i)" : '') . $ext;
    }
}
