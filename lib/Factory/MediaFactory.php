<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (MediaFactory.php) is part of Xibo.
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


namespace Xibo\Factory;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Xibo\Entity\Media;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class MediaFactory
 * @package Xibo\Factory
 */
class MediaFactory extends BaseFactory
{
    /** @var Media[] */
    private $remoteDownloadQueue = [];

    /** @var Media[] */
    private $remoteDownloadNotRequiredQueue = [];

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param PermissionFactory $permissionFactory
     * @param TagFactory $tagFactory
     * @param PlaylistFactory $playlistFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $config, $permissionFactory, $tagFactory, $playlistFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;
        $this->permissionFactory = $permissionFactory;
        $this->tagFactory = $tagFactory;
        $this->playlistFactory = $playlistFactory;
    }

    /**
     * Create Empty
     * @return Media
     */
    public function createEmpty()
    {
        return new Media($this->getStore(), $this->getLog(), $this->config, $this, $this->permissionFactory, $this->tagFactory, $this->playlistFactory);
    }

    /**
     * Create New Media
     * @param string $name
     * @param string $fileName
     * @param string $type
     * @param int $ownerId
     * @param int $duration
     * @return Media
     */
    public function create($name, $fileName, $type, $ownerId, $duration = 0)
    {
        $media = $this->createEmpty();
        $media->name = $name;
        $media->fileName = $fileName;
        $media->mediaType = $type;
        $media->ownerId = $ownerId;
        $media->duration = $duration;

        return $media;
    }

    /**
     * Create System Module File
     * @param $name
     * @param string $file
     * @return Media
     */
    public function createModuleSystemFile($name, $file = '')
    {
        return $this->createModuleFile($name, $file, 1);
    }

    /**
     * Create Module File
     * @param $name
     * @param $file
     * @param $systemFile
     * @return Media
     */
    public function createModuleFile($name, $file = '', $systemFile = 0)
    {
        if ($file == '') {
            $file = $name;
            $name = basename($file);
        }

        try {
            $media = $this->getByNameAndType($name, 'module');

            // Reassert the new file (which we might want to download)
            $media->fileName = $file;
            $media->storedAs = $name;
        }
        catch (NotFoundException $e) {
            $media = $this->createEmpty();
            $media->name = $name;
            $media->fileName = $file;
            $media->mediaType = 'module';
            $media->expires = 0;
            $media->storedAs = $name;
            $media->ownerId = $this->getUserFactory()->getSystemUser()->getOwnerId();
            $media->moduleSystemFile = $systemFile;
        }

        return $media;
    }

    /**
     * Create module files from folder
     * @param string $folder The path to the folder to add.
     * @return array[Media]
     */
    public function createModuleFileFromFolder($folder)
    {
        $media = [];

        if (!is_dir($folder))
            throw new \InvalidArgumentException(__('Not a folder'));

        foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
            if (is_dir($folder . DIRECTORY_SEPARATOR . $file)) continue;
            
            $file = $this->createModuleSystemFile($file, $folder . DIRECTORY_SEPARATOR . $file);
            $file->moduleSystemFile = true;

            $media[] = $file;
        }

        return $media;
    }

    /**
     * Queue remote file download
     * @param $name
     * @param $uri
     * @param $expiry
     * @param array $requestOptions
     * @return Media
     */
    public function queueDownload($name, $uri, $expiry, $requestOptions = [])
    {
        // Determine the save name
        if (!isset($requestOptions['fileType'])) {
            $media = $this->createModuleFile($name, $uri);
            $media->isRemote = true;
        } else {
            $media = $this->createEmpty();
            $media->name = $name;
            $media->fileName = $uri;
            $media->ownerId = $this->getUserFactory()->getUser()->userId;
            $media->mediaType = $requestOptions['fileType'];
            $media->duration = $requestOptions['duration'];
            $media->moduleSystemFile = 0;
            $media->isRemote = false;
            $media->urlDownload = true;
            $media->extension = $requestOptions['extension'];
            $media->enableStat = $requestOptions['enableStat'];
        }

        $this->getLog()->debug('Queue download of: ' . $uri . ', current mediaId for this download is ' . $media->mediaId . '.');

        // We update the desired expiry here - isSavedRequired is tested against the original value
        $media->expires = $expiry;

        // Save the file, but do not download yet.
        $media->saveAsync(['requestOptions' => $requestOptions]);

        // Add to our collection of queued downloads
        // but only if its not already in the queue (we might have tried to queue it multiple times in the same request)
        if ($media->isSaveRequired) {

            $this->getLog()->debug('We are required to download as this file is either expired or not existing');

            $queueItem = true;
            if ($media->getId() != null) {
                // Existing media, check to see if we're already queued
                foreach ($this->remoteDownloadQueue as $queue) {
                    // If we find this item already, don't queue
                    if ($queue->getId() === $media->getId()) {
                        $queueItem = false;
                        break;
                    }
                }
            }

            if ($queueItem)
                $this->remoteDownloadQueue[] = $media;

        } else {
            // Queue in the not required download queue
            $this->getLog()->debug('Download not required as this file exists and is up to date. Expires = ' . $media->getOriginalValue('expires'));

            $queueItem = true;
            if ($media->getId() != null) {
                // Existing media, check to see if we're already queued
                foreach ($this->remoteDownloadNotRequiredQueue as $queue) {
                    // If we find this item already, don't queue
                    if ($queue->getId() === $media->getId()) {
                        $queueItem = false;
                        break;
                    }
                }
            }

            if ($queueItem)
                $this->remoteDownloadNotRequiredQueue[] = $media;
        }

        // Return the media item
        return $media;
    }

    /**
     * Process the queue of downloads
     * @param null|callable $success success callable
     * @param null|callable $failure failure callable
     */
    public function processDownloads($success = null, $failure = null)
    {
        if (count($this->remoteDownloadQueue) > 0) {

            $this->getLog()->debug('Processing Queue of ' . count($this->remoteDownloadQueue) . ' downloads.');

            // Create a generator and Pool
            $log = $this->getLog();
            $queue = $this->remoteDownloadQueue;
            $client = new Client($this->config->getGuzzleProxy());

            $downloads = function () use ($client, $queue) {
                foreach ($queue as $media) {
                    $url = $media->downloadUrl();
                    $sink = $media->downloadSink();
                    $requestOptions = array_merge($media->downloadRequestOptions(),  ['save_to' => $sink]);

                    yield function () use ($client, $url, $requestOptions) {
                        return $client->getAsync($url, $requestOptions);
                    };
                }
            };

            $pool = new Pool($client, $downloads(), [
                'concurrency' => 5,
                'fulfilled' => function ($response, $index) use ($log, $queue, $success, $failure) {
                    /** @var Media $item */
                    $item = $queue[$index];

                    // File is downloaded, call save to move it appropriately
                    try {
                        $item->saveFile();

                        // If a success callback has been provided, call it
                        if ($success !== null && is_callable($success))
                            $success($item);

                    } catch (\Exception $e) {
                        $this->getLog()->error('Unable to save:' . $item->mediaId . '. ' . $e->getMessage());

                        // Remove it
                        $item->delete(['rollback' => true]);

                        // If a failure callback has been provided, call it
                        if ($failure !== null && is_callable($failure))
                            $failure($item);
                    }
                },
                'rejected' => function ($reason, $index) use ($log) {
                    /* @var RequestException $reason */
                    $log->error(sprintf('Rejected Request %d to %s because %s', $index, $reason->getRequest()->getUri(), $reason->getMessage()));
                }
            ]);

            $promise = $pool->promise();
            $promise->wait();
        }

        // Handle the downloads that did not require downloading
        if (count($this->remoteDownloadNotRequiredQueue) > 0) {
            $this->getLog()->debug('Processing Queue of ' . count($this->remoteDownloadNotRequiredQueue) . ' items which do not need downloading.');

            foreach ($this->remoteDownloadNotRequiredQueue as $item) {
                // If a success callback has been provided, call it
                if ($success !== null && is_callable($success))
                    $success($item);
            }
        }

        // Clear the queue for next time.
        $this->remoteDownloadQueue = [];
        $this->remoteDownloadNotRequiredQueue = [];
    }

    /**
     * Get by Media Id
     * @param int $mediaId
     * @return Media
     * @throws NotFoundException
     */
    public function getById($mediaId)
    {
        $media = $this->query(null, array('disableUserCheck' => 1, 'mediaId' => $mediaId, 'allModules' => 1));

        if (count($media) <= 0)
            throw new NotFoundException(__('Cannot find media'));

        return $media[0];
    }

    /**
     * Get by Parent Media Id
     * @param int $mediaId
     * @return Media
     * @throws NotFoundException
     */
    public function getParentById($mediaId)
    {
        $media = $this->query(null, array('disableUserCheck' => 1, 'parentMediaId' => $mediaId, 'allModules' => 1));

        if (count($media) <= 0)
            throw new NotFoundException(__('Cannot find media'));

        return $media[0];
    }

    /**
     * Get by Media Name
     * @param string $name
     * @return Media
     * @throws NotFoundException
     */
    public function getByName($name)
    {
        $media = $this->query(null, array('disableUserCheck' => 1, 'nameExact' => $name, 'allModules' => 1));

        if (count($media) <= 0)
            throw new NotFoundException(__('Cannot find media'));

        return $media[0];
    }

    /**
     * Get by Media Name
     * @param string $name
     * @param string $type
     * @return Media
     * @throws NotFoundException
     */
    public function getByNameAndType($name, $type)
    {
        $media = $this->query(null, array('disableUserCheck' => 1, 'nameExact' => $name, 'type' => $type, 'allModules' => 1));

        if (count($media) <= 0)
            throw new NotFoundException(__('Cannot find media'));

        return $media[0];
    }

    /**
     * Get by Owner Id
     * @param int $ownerId
     * @return array[Media]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'ownerId' => $ownerId, 'isEdited' => 1));
    }

    /**
     * Get by Type
     * @param string $type
     * @return array[Media]
     */
    public function getByMediaType($type)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'type' => $type, 'allModules' => 1));
    }

    /**
     * Get by Display Group Id
     * @param int $displayGroupId
     * @return array[Media]
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'displayGroupId' => $displayGroupId));
    }

    /**
     * Get Media by LayoutId
     * @param int $layoutId
     * @param int $edited
     * @return array[Media]
     */
    public function getByLayoutId($layoutId, $edited = -1)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'layoutId' => $layoutId, 'isEdited' => $edited]);
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return Media[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null)
            $sortOrder = ['name'];

        $newSortOrder = [];
        foreach ($sortOrder as $sort) {
            if ($sort == '`revised`') {
                $newSortOrder[] = '`parentId`';
                continue;
            }

            if ($sort == '`revised` DESC') {
                $newSortOrder[] = '`parentId` DESC';
                continue;
            }
            $newSortOrder[] = $sort;
        }
        $sortOrder = $newSortOrder;

        $entries = array();

        $params = array();
        $select = '
            SELECT  media.mediaId,
               media.name,
               media.type AS mediaType,
               media.duration,
               media.userId AS ownerId,
               media.fileSize,
               media.storedAs,
               media.valid,
               media.moduleSystemFile,
               media.expires,
               media.md5,
               media.retired,
               media.isEdited,
               IFNULL(parentmedia.mediaId, 0) AS parentId,
               `media`.released,
               `media`.apiRef,
               `media`.createdDt,
               `media`.modifiedDt,
               `media`.enableStat,
            ';

        $select .= " (SELECT GROUP_CONCAT(DISTINCT tag) FROM tag INNER JOIN lktagmedia ON lktagmedia.tagId = tag.tagId WHERE lktagmedia.mediaId = media.mediaID GROUP BY lktagmedia.mediaId) AS tags, ";
        $select .= " (SELECT GROUP_CONCAT(IFNULL(value, 'NULL')) FROM tag INNER JOIN lktagmedia ON lktagmedia.tagId = tag.tagId WHERE lktagmedia.mediaId = media.mediaId GROUP BY lktagmedia.mediaId) AS tagValues, ";
        $select .= "        `user`.UserName AS owner, ";
        $select .= "     (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                              FROM `permission`
                                INNER JOIN `permissionentity`
                                ON `permissionentity`.entityId = permission.entityId
                                INNER JOIN `group`
                                ON `group`.groupId = `permission`.groupId
                             WHERE entity = :entity
                                AND objectId = media.mediaId
                                AND view = 1
                            ) AS groupsWithPermissions, ";
        $params['entity'] = 'Xibo\\Entity\\Media';

        $select .= "   media.originalFileName AS fileName ";

        $body = " FROM media ";
        $body .= "   LEFT OUTER JOIN media parentmedia ";
        $body .= "   ON parentmedia.editedMediaId = media.mediaId ";

        // Media might be linked to the system user (userId 0)
        $body .= "   LEFT OUTER JOIN `user` ON `user`.userId = `media`.userId ";

        if ($this->getSanitizer()->getInt('displayGroupId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkmediadisplaygroup`
                ON lkmediadisplaygroup.mediaid = media.mediaid
                    AND lkmediadisplaygroup.displayGroupId = :displayGroupId
            ';

            $params['displayGroupId'] = $this->getSanitizer()->getInt('displayGroupId', $filterBy);
        }

        $body .= " WHERE 1 = 1 ";

        if ($this->getSanitizer()->getInt('notPlayerSoftware', $filterBy) == 1) {
            $body .= ' AND media.type <> \'playersoftware\' ';
        }

        if ($this->getSanitizer()->getInt('notSavedReport', $filterBy) == 1) {
            $body .= ' AND media.type <> \'savedreport\' ';
        }

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\Media', $body, $params, '`media`.mediaId', '`media`.userId', $filterBy);

        if ($this->getSanitizer()->getInt('allModules', $filterBy) == 0) {
            $body .= ' AND media.type <> \'module\' ';
        }

        if ($this->getSanitizer()->getInt('assignable', -1, $filterBy) == 1) {
            $body .= '
                AND media.type <> \'genericfile\'
                AND media.type <> \'playersoftware\'
                AND media.type <> \'savedreport\'
                AND media.type <> \'font\'
            ';
        }

        if ($this->getSanitizer()->getInt('assignable', -1, $filterBy) == 0) {
            $body .= '
                AND (media.type = \'genericfile\'
                OR media.type = \'playersoftware\'
                OR media.type = \'savedreport\'
                OR media.type = \'font\')
            ';
        }

        // Unused only?
        if ($this->getSanitizer()->getInt('unusedOnly', $filterBy) !== null) {

            $body .= '
                AND media.mediaId NOT IN (SELECT mediaId FROM `lkwidgetmedia`)
                AND media.mediaId NOT IN (SELECT mediaId FROM `lkmediadisplaygroup`)
                AND media.mediaId NOT IN (SELECT backgroundImageId FROM `layout` WHERE backgroundImageId IS NOT NULL)
                AND media.type <> \'module\'
                AND media.type <> \'font\'
                AND media.type <> \'playersoftware\'
                AND media.type <> \'savedreport\'
            ';

            // DataSets with library images
            $dataSetSql = '
                SELECT dataset.dataSetId, datasetcolumn.heading
                  FROM dataset
                    INNER JOIN datasetcolumn
                    ON datasetcolumn.DataSetID = dataset.DataSetID
                 WHERE DataTypeID = 5;
            ';

            $dataSets = $this->getStore()->select($dataSetSql, []);

            if (count($dataSets) > 0) {

                $body .= ' AND media.mediaID NOT IN (';

                $first = true;
                foreach ($dataSets as $dataSet) {

                    if (!$first)
                        $body .= ' UNION ALL ';

                    $first = false;

                    $dataSetId = $this->getSanitizer()->getInt('dataSetId', $dataSet);
                    $heading = $this->getSanitizer()->getString('heading', $dataSet);

                    $body .= ' SELECT `' .  $heading . '` AS mediaId FROM `dataset_' . $dataSetId . '`';
                }

                $body .= ') ';
            }
        }

        if ($this->getSanitizer()->getString('name', $filterBy) != null) {
            $terms = explode(',', $this->getSanitizer()->getString('name', $filterBy));
            $this->nameFilter('media', 'name', $terms, $body, $params, ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
        }

        if ($this->getSanitizer()->getString('nameExact', $filterBy) != '') {
            $body .= ' AND media.name = :exactName ';
            $params['exactName'] = $this->getSanitizer()->getString('nameExact', $filterBy);
        }

        if ($this->getSanitizer()->getInt('mediaId', -1, $filterBy) != -1) {
            $body .= " AND media.mediaId = :mediaId ";
            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', $filterBy);
        } else if ($this->getSanitizer()->getInt('parentMediaId', $filterBy) !== null) {
            $body .= ' AND media.editedMediaId = :mediaId ';
            $params['mediaId'] = $this->getSanitizer()->getInt('parentMediaId', $filterBy);
        } else if ($this->getSanitizer()->getInt('isEdited', -1, $filterBy) != -1) {
            $body .= ' AND media.isEdited <> -1 ';
        } else {
            $body .= ' AND media.isEdited = 0 ';
        }

        if ($this->getSanitizer()->getString('type', $filterBy) != '') {
            $body .= 'AND media.type = :type ';
            $params['type'] = $this->getSanitizer()->getString('type', $filterBy);
        }

        if ($this->getSanitizer()->getInt('imageProcessing', $filterBy) !== null) {
            $body .= 'AND ( media.type = \'image\' OR (media.type = \'module\' AND media.moduleSystemFile = 0) ) ';
        }

        if ($this->getSanitizer()->getString('storedAs', $filterBy) != '') {
            $body .= 'AND media.storedAs = :storedAs ';
            $params['storedAs'] = $this->getSanitizer()->getString('storedAs', $filterBy);
        }

        if ($this->getSanitizer()->getInt('ownerId', $filterBy) !== null) {
            $body .= " AND media.userid = :ownerId ";
            $params['ownerId'] = $this->getSanitizer()->getInt('ownerId', $filterBy);
        }

        // User Group filter
        if ($this->getSanitizer()->getInt('ownerUserGroupId', 0, $filterBy) != 0) {
            $body .= ' AND media.userid IN (SELECT DISTINCT userId FROM `lkusergroup` WHERE groupId =  :ownerUserGroupId) ';
            $params['ownerUserGroupId'] = $this->getSanitizer()->getInt('ownerUserGroupId', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('released', $filterBy) !== null) {
            $body .= " AND media.released = :released ";
            $params['released'] = $this->getSanitizer()->getInt('released', $filterBy);
        }

        if ($this->getSanitizer()->getInt('retired', -1, $filterBy) == 1)
            $body .= " AND media.retired = 1 ";

        if ($this->getSanitizer()->getInt('retired', -1, $filterBy) == 0)
            $body .= " AND media.retired = 0 ";

        // Expired files?
        if ($this->getSanitizer()->getInt('expires', $filterBy) != 0) {
            $body .= ' 
                AND media.expires < :expires 
                AND IFNULL(media.expires, 0) <> 0 
                AND ( media.mediaId NOT IN (SELECT mediaId FROM `lkwidgetmedia`) OR media.type <> \'module\')
            ';
            $params['expires'] = $this->getSanitizer()->getInt('expires', $filterBy);
        }

        if ($this->getSanitizer()->getInt('layoutId', $filterBy) !== null) {
            // handles the closure table link with sub-playlists
            $body .= '
                AND media.mediaId IN (
                    SELECT `lkwidgetmedia`.mediaId
                      FROM region
                        INNER JOIN playlist
                        ON playlist.regionId = region.regionId
                        INNER JOIN lkplaylistplaylist
                        ON lkplaylistplaylist.parentId = playlist.playlistId
                        INNER JOIN widget
                        ON widget.playlistId = lkplaylistplaylist.childId
                        INNER JOIN lkwidgetmedia
                        ON widget.widgetId = lkwidgetmedia.widgetId
                     WHERE region.layoutId = :layoutId ';

            if ($this->getSanitizer()->getInt('widgetId', $filterBy) !== null) {
                $body .= ' AND `widget`.widgetId = :widgetId ';
                $params['widgetId'] = $this->getSanitizer()->getInt('widgetId', $filterBy);
            }

            $body .= '    )
                AND media.type <> \'module\'
            ';
            $params['layoutId'] = $this->getSanitizer()->getInt('layoutId', $filterBy);
        }

        // Tags
        if ($this->getSanitizer()->getString('tags', $filterBy) != '') {

            $tagFilter = $this->getSanitizer()->getString('tags', $filterBy);

            if (trim($tagFilter) === '--no-tag') {
                $body .= ' AND `media`.mediaId NOT IN (
                    SELECT `lktagmedia`.mediaId
                     FROM tag
                        INNER JOIN `lktagmedia`
                        ON `lktagmedia`.tagId = tag.tagId
                    )
                ';
            } else {
                $operator = $this->getSanitizer()->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

                $body .= " AND `media`.mediaId IN (
                SELECT `lktagmedia`.mediaId
                  FROM tag
                    INNER JOIN `lktagmedia`
                    ON `lktagmedia`.tagId = tag.tagId
                ";

                $tags = explode(',', $tagFilter);
                $this->tagFilter($tags, $operator, $body, $params);
            }
        }

        // File size
        if ($this->getSanitizer()->getString('fileSize', $filterBy) != null) {
            $fileSize = $this->parseComparisonOperator($this->getSanitizer()->getString('fileSize', $filterBy));

            $body .= ' AND `media`.fileSize ' . $fileSize['operator'] . ' :fileSize ';
            $params['fileSize'] = $fileSize['variable'];
        }

        // Duration
        if ($this->getSanitizer()->getString('duration', $filterBy) != null) {
            $duration = $this->parseComparisonOperator($this->getSanitizer()->getString('duration', $filterBy));

            $body .= ' AND `media`.duration ' . $duration['operator'] . ' :duration ';
            $params['duration'] = $duration['variable'];
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;
        
        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $media = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'duration', 'size', 'released', 'moduleSystemFile', 'isEdited', 'expires'
                ]
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['entity']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}