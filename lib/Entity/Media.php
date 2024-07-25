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
namespace Xibo\Entity;

use Carbon\Carbon;
use Mimey\MimeTypes;
use Respect\Validation\Validator as v;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Media
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Media implements \JsonSerializable
{
    use EntityTrait;
    use TagLinkTrait;

    /**
     * @SWG\Property(description="The Media ID")
     * @var int
     */
    public $mediaId;

    /**
     * @SWG\Property(description="The ID of the User that owns this Media")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The Parent ID of this Media if it has been revised")
     * @var int
     */
    public $parentId;

    /**
     * @SWG\Property(description="The Name of this Media")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="The module type of this Media")
     * @var string
     */
    public $mediaType;

    /**
     * @SWG\Property(description="The file name of the media as stored in the library")
     * @var string
     */
    public $storedAs;

    /**
     * @SWG\Property(description="The original file name as it was uploaded")
     * @var string
     */
    public $fileName;

    // Thing that might be referred to
    /**
     * @SWG\Property(description="Tags associated with this Media, array of TagLink objects")
     * @var TagLink[]
     */
    public $tags = [];

    /**
     * @SWG\Property(description="The file size in bytes")
     * @var int
     */
    public $fileSize;

    /**
     * @SWG\Property(description="The duration to use when assigning this media to a Layout widget")
     * @var int
     */
    public $duration = 0;

    /**
     * @SWG\Property(description="Flag indicating whether this media is valid.")
     * @var int
     */
    public $valid = 0;

    /**
     * @SWG\Property(description="DEPRECATED: Flag indicating whether this media is a system file or not")
     * @var int
     */
    public $moduleSystemFile = 0;

    /**
     * @SWG\Property(description="Timestamp indicating when this media should expire")
     * @var int
     */
    public $expires = 0;

    /**
     * @SWG\Property(description="Flag indicating whether this media is retired")
     * @var int
     */
    public $retired = 0;

    /**
     * @SWG\Property(description="Flag indicating whether this media has been edited and replaced with a newer file")
     * @var int
     */
    public $isEdited = 0;

    /**
     * @SWG\Property(description="A MD5 checksum of the stored media file")
     * @var string
     */
    public $md5;

    /**
     * @SWG\Property(description="The username of the User that owns this media")
     * @var string
     */
    public $owner;

    /**
     * @SWG\Property(description="A comma separated list of groups/users with permissions to this Media")
     * @var string
     */
    public $groupsWithPermissions;

    /**
     * @SWG\Property(description="A flag indicating whether this media has been released")
     * @var int
     */
    public $released = 1;

    /**
     * @SWG\Property(description="An API reference")
     * @var string
     */
    public $apiRef;

    /**
     * @var string
     * @SWG\Property(
     *  description="The datetime the Media was created"
     * )
     */
    public $createdDt;

    /**
     * @var string
     * @SWG\Property(
     *  description="The datetime the Media was last modified"
     * )
     */
    public $modifiedDt;

    /**
     * @var string
     * @SWG\Property(
     *  description="The option to enable the collection of Media Proof of Play statistics"
     * )
     */
    public $enableStat;

    /**
     * @var string
     * @SWG\Property(
     *  description="The orientation of the Media file"
     * )
     */
    public $orientation;

    /**
     * @var int
     * @SWG\Property(description="The width of the Media file")
     */
    public $width;

    /**
     * @var int
     * @SWG\Property(description="The height of the Media file")
     */
    public $height;

    // Private
    /** @var TagLink[] */
    private $unlinkTags = [];
    /** @var TagLink[] */
    private $linkTags = [];
    private $requestOptions = [];
    private $datesToFormat = ['expires'];
    // New file revision
    public $isSaveRequired;
    public $isRemote;

    public $cloned = false;
    public $newExpiry;
    public $alwaysCopy = false;

    /**
     * @SWG\Property(description="The id of the Folder this Media belongs to")
     * @var int
     */
    public $folderId;

    /**
     * @SWG\Property(description="The id of the Folder responsible for providing permissions for this Media")
     * @var int
     */
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
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param ConfigServiceInterface $config
     * @param MediaFactory $mediaFactory
     * @param PermissionFactory $permissionFactory
     */
    public function __construct($store, $log, $dispatcher, $config, $mediaFactory, $permissionFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->config = $config;
        $this->mediaFactory = $mediaFactory;
        $this->permissionFactory = $permissionFactory;
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
        ], $options);

        if ($options['validate'] && $this->mediaType !== 'module') {
            $this->validate($options);
        }

        // Add or edit
        if ($this->mediaId == null || $this->mediaId == 0) {
            $this->add();

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
                || ($expires > 0 && $expires < Carbon::now()->format('U'))
                || ($this->mediaType === 'module' && !file_exists($this->downloadSink(false)));

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

        // Should we bring back this items parent?
        try {
            // Revert
            $parentMedia = $this->mediaFactory->getParentById($this->mediaId);
            $parentMedia->isEdited = 0;
            $parentMedia->parentId = null;
            $parentMedia->save(['validate' => false, 'audit' => false]);

            $auditMessage .= ' and reverted old revision';
            $auditContext['revertedMediaId'] = $parentMedia->mediaId;
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

        // Sanitize what we have left.
        $fileName = htmlspecialchars($fileName);

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
                $this->getLog()->debug('Copying cloned file: ' . $libraryFolder . $this->fileName);
                // Copy the file into the library
                if (!@copy($libraryFolder . $this->fileName, $libraryFolder . $saveName)) {
                    throw new ConfigurationException(__('Problem copying file in the Library Folder'));
                }
            } else {
                $this->getLog()->debug('Moving temporary file: ' . $libraryFolder . 'temp/' . $this->fileName);
                // Move the file into the library
                if (!$this->moveFile($libraryFolder . 'temp/' . $this->fileName, $libraryFolder . $saveName)) {
                    throw new ConfigurationException(__('Problem moving uploaded file into the Library Folder'));
                }
            }

            // Set the storedAs
            $this->storedAs = $saveName;
        } else {
            // We have pre-defined where we want this to be stored
            if (empty($this->storedAs)) {
                // Assume we want to set this automatically (i.e. we are set to always copy)
                $this->storedAs = $saveName;
            }

            if ($this->isRemote) {
                $this->getLog()->debug('Moving temporary file: ' . $libraryFolder . 'temp/' . $this->name);

                // Move the file into the library
                if (!$this->moveFile($libraryFolder . 'temp/' . $this->name, $libraryFolder . $this->storedAs)) {
                    throw new ConfigurationException(__('Problem moving downloaded file into the Library Folder'));
                }
            } else {
                $this->getLog()->debug('Copying specified file: ' . $this->fileName);

                if (!@copy($this->fileName, $libraryFolder . $this->storedAs)) {
                    $this->getLog()->error(sprintf('Cannot copy %s to %s', $this->fileName, $libraryFolder . $this->storedAs));
                    throw new ConfigurationException(__('Problem copying provided file into the Library Folder'));
                }
            }
        }

        // Work out the MD5
        $this->md5 = md5_file($libraryFolder . $this->storedAs);
        $this->fileSize = filesize($libraryFolder . $this->storedAs);

        // Set to valid
        $this->valid = 1;

        // Resize image dimensions if threshold exceeds
        // This also sets orientation
        $this->assessDimensions();

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

    private function assessDimensions(): void
    {
        if ($this->mediaType === 'image' || ($this->mediaType === 'module' && $this->moduleSystemFile === 0)) {
            $libraryFolder = $this->config->getSetting('LIBRARY_LOCATION');
            $filePath = $libraryFolder . $this->storedAs;
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
     * Delete a Library File
     */
    private function deleteFile()
    {
        // Make sure storedAs isn't null
        if ($this->storedAs == null) {
            $this->getLog()->error(sprintf('Deleting media [%s] with empty stored as. Skipping library file delete.', $this->name));
            return;
        }

        // Library location
        $libraryLocation = $this->config->getSetting("LIBRARY_LOCATION");

        // 3 things to check for..
        // the actual file, the thumbnail, the background
        // video cover image and its thumbnail
        if (file_exists($libraryLocation . $this->storedAs)) {
            unlink($libraryLocation . $this->storedAs);
        }

        if (file_exists($libraryLocation . 'tn_' . $this->storedAs)) {
            unlink($libraryLocation . 'tn_' . $this->storedAs);
        }

        if (file_exists($libraryLocation . 'tn_' . $this->mediaId . '_videocover.png')) {
            unlink($libraryLocation . 'tn_' . $this->mediaId . '_videocover.png');
        }

        if (file_exists($libraryLocation . $this->mediaId . '_videocover.png')) {
            unlink($libraryLocation . $this->mediaId . '_videocover.png');
        }
    }

    /**
     * Workaround for moving files across file systems
     * @param $from
     * @param $to
     * @return bool
     */
    private function moveFile($from, $to)
    {
        // Try to move the file first
        $moved = rename($from, $to);

        if (!$moved) {
            $this->getLog()->info('Cannot move file: ' . $from . ' to ' . $to . ', will try and copy/delete instead.');

            // Copy
            $moved = copy($from, $to);

            // Delete
            if (!@unlink($from)) {
                $this->getLog()->error('Cannot delete file: ' . $from . ' after copying to ' . $to);
            }
        }

        return $moved;
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
     * Download Sink
     * @return string
     */
    public function downloadSink($temp = true)
    {
        return $this->config->getSetting('LIBRARY_LOCATION')
            . ($temp ? 'temp' . DIRECTORY_SEPARATOR : '')
            . $this->name;
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
}
