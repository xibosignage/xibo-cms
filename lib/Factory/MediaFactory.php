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


namespace Xibo\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use Xibo\Entity\Media;
use Xibo\Entity\User;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Environment;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class MediaFactory
 * @package Xibo\Factory
 */
class MediaFactory extends BaseFactory
{
    use TagTrait;

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
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /**
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param PermissionFactory $permissionFactory
     * @param PlaylistFactory $playlistFactory
     */
    public function __construct($user, $userFactory, $config, $permissionFactory, $playlistFactory)
    {
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;
        $this->permissionFactory = $permissionFactory;
        $this->playlistFactory = $playlistFactory;
    }

    /**
     * Create Empty
     * @return Media
     */
    public function createEmpty()
    {
        return new Media(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this->config,
            $this,
            $this->permissionFactory
        );
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
     * Create Module File
     * @param $name
     * @param string|null $file
     * @return Media
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function createModuleFile($name, ?string $file = ''): Media
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
        } catch (NotFoundException $e) {
            $media = $this->createEmpty();
            $media->name = $name;
            $media->fileName = $file;
            $media->mediaType = 'module';
            $media->expires = 0;
            $media->storedAs = $name;
            $media->ownerId = $this->getUserFactory()->getSystemUser()->getOwnerId();
            $media->moduleSystemFile = 0;
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
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
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
            $media->isRemote = true;
            $media->setUnmatchedProperty('urlDownload', true);
            $media->setUnmatchedProperty('extension', $requestOptions['extension'] ?? null);
            $media->enableStat = $requestOptions['enableStat'];
            $media->folderId = $requestOptions['folderId'];
            $media->permissionsFolderId = $requestOptions['permissionsFolderId'];
            $media->apiRef = $requestOptions['apiRef'] ?? null;
        }

        $this->getLog()->debug('Queue download of: ' . $uri . ', current mediaId for this download is '
            . $media->mediaId . '.');

        // We update the desired expiry here - isSavedRequired is tested against the original value
        $media->expires = $expiry;

        // Save the file, but do not download yet.
        $media->saveAsync(['requestOptions' => $requestOptions]);

        // Add to our collection of queued downloads
        // but only if it's not already in the queue (we might have tried to queue it multiple times in
        // the same request)
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

            if ($queueItem) {
                $this->remoteDownloadQueue[] = $media;
            }
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

            if ($queueItem) {
                $this->remoteDownloadNotRequiredQueue[] = $media;
            }
        }

        // Return the media item
        return $media;
    }

    /**
     * Process the queue of downloads
     * @param null|callable $success success callable
     * @param null|callable $failure failure callable
     * @param null|callable $rejected rejected callable
     */
    public function processDownloads($success = null, $failure = null, $rejected = null)
    {
        if (count($this->remoteDownloadQueue) > 0) {
            $this->getLog()->debug('Processing Queue of ' . count($this->remoteDownloadQueue) . ' downloads.');

            // Create a generator and Pool
            $log = $this->getLog();
            $queue = $this->remoteDownloadQueue;
            $client = new Client($this->config->getGuzzleProxy(['timeout' => 0]));

            $downloads = function () use ($client, $queue) {
                foreach ($queue as $media) {
                    $url = $media->downloadUrl();
                    $sink = $media->downloadSink();
                    $requestOptions = array_merge($media->downloadRequestOptions(), [
                        'sink' => $sink,
                        'on_headers' => function (ResponseInterface $response) {
                            $this->getLog()->debug('processDownloads: on_headers status code = '
                                . $response->getStatusCode());

                            if ($response->getStatusCode() < 299) {
                                $this->getLog()->debug('processDownloads: successful, headers = '
                                    . var_export($response->getHeaders(), true));

                                // Get the content length
                                $contentLength = $response->getHeaderLine('Content-Length');
                                if (empty($contentLength)
                                    || intval($contentLength) > ByteFormatter::toBytes(Environment::getMaxUploadSize())
                                ) {
                                    throw new \Exception(__('File too large'));
                                }
                            }
                        }
                    ]);

                    yield function () use ($client, $url, $requestOptions) {
                        return $client->getAsync($url, $requestOptions);
                    };
                }
            };

            $pool = new Pool($client, $downloads(), [
                'concurrency' => 5,
                'fulfilled' => function ($response, $index) use ($log, $queue, $success, $failure) {
                    $item = $queue[$index];

                    // File is downloaded, call save to move it appropriately
                    try {
                        $item->saveFile();

                        // If a success callback has been provided, call it
                        if ($success !== null && is_callable($success)) {
                            $success($item);
                        }
                    } catch (\Exception $e) {
                        $this->getLog()->error('processDownloads: Unable to save mediaId '
                            . $item->mediaId . '. ' . $e->getMessage());

                        // Remove it
                        $item->delete(['rollback' => true]);

                        // If a failure callback has been provided, call it
                        if ($failure !== null && is_callable($failure)) {
                            $failure($item);
                        }
                    }
                },
                'rejected' => function ($reason, $index) use ($log, $queue, $rejected) {
                    /* @var RequestException $reason */
                    $log->error(
                        sprintf(
                            'Rejected Request %d to %s because %s',
                            $index,
                            $reason->getRequest()->getUri(),
                            $reason->getMessage()
                        )
                    );

                    // We should remove the media record.
                    $queue[$index]->delete(['rollback' => true]);

                    // If a rejected callback has been provided, call it
                    if ($rejected !== null && is_callable($rejected)) {
                        // Do we have a wrapped exception?
                        $reasonMessage = $reason->getPrevious() !== null
                            ? $reason->getPrevious()->getMessage()
                            : $reason->getMessage();

                        call_user_func($rejected, $reasonMessage);
                    }
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
                if ($success !== null && is_callable($success)) {
                    $success($item);
                }
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
    public function getById($mediaId, bool $isDisableUserCheck = true)
    {
        $media = $this->query(null, [
            'disableUserCheck' => $isDisableUserCheck ? 1 : 0,
            'mediaId' => $mediaId,
            'allModules' => 1,
        ]);

        if (count($media) <= 0) {
            throw new NotFoundException(__('Cannot find media'));
        }

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

        if (count($media) <= 0) {
            throw new NotFoundException(__('Cannot find media'));
        }

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
     * @return Media[]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId, $allModules = 0)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'ownerId' => $ownerId, 'isEdited' => 1, 'allModules' => $allModules]);
    }

    /**
     * Get by Type
     * @param string $type
     * @return Media[]
     * @throws NotFoundException
     */
    public function getByMediaType($type)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'type' => $type, 'allModules' => 1));
    }

    /**
     * Get by Display Group Id
     * @param int $displayGroupId
     * @return Media[]
     * @throws NotFoundException
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        if ($displayGroupId == null) {
            return [];
        }

        return $this->query(null, array('disableUserCheck' => 1, 'displayGroupId' => $displayGroupId));
    }

    /**
     * Get Media by LayoutId
     * @param int $layoutId
     * @param int $edited
     * @param int $excludeDynamicPlaylistMedia
     * @return Media[]
     * @throws NotFoundException
     */
    public function getByLayoutId($layoutId, $edited = -1, $excludeDynamicPlaylistMedia = 0)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'layoutId' => $layoutId, 'isEdited' => $edited, 'excludeDynamicPlaylistMedia' => $excludeDynamicPlaylistMedia]);
    }

    /**
     * Get Media by campaignId
     * @param int $campaignId
     * @return Media[]
     * @throws NotFoundException
     */
    public function getByCampaignId($campaignId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'campaignId' => $campaignId]);
    }

    public function getForMenuBoards()
    {
        return $this->query(null, ['onlyMenuBoardAllowed' => 1]);
    }

    /**
     * @param int $folderId
     * @return Media[]
     * @throws NotFoundException
     */
    public function getByFolderId(int $folderId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'folderId' => $folderId]);
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return Media[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);

        if ($sortOrder === null) {
            $sortOrder = ['name'];
        }

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

        $entries = [];

        $params = [];
        $select = '
            SELECT `media`.mediaId,
               `media`.name,
               `media`.type AS mediaType,
               `media`.duration,
               `media`.userId AS ownerId,
               `media`.fileSize,
               `media`.storedAs,
               `media`.valid,
               `media`.moduleSystemFile,
               `media`.expires,
               `media`.md5,
               `media`.retired,
               `media`.isEdited,
               IFNULL(parentmedia.mediaId, 0) AS parentId,
               `media`.released,
               `media`.apiRef,
               `media`.createdDt,
               `media`.modifiedDt,
               `media`.enableStat,
               `media`.folderId,
               `media`.permissionsFolderId,
               `media`.orientation,
               `media`.width,
               `media`.height,
               `user`.UserName AS owner,
            ';
        $select .= '     (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                              FROM `permission`
                                INNER JOIN `permissionentity`
                                ON `permissionentity`.entityId = permission.entityId
                                INNER JOIN `group`
                                ON `group`.groupId = `permission`.groupId
                             WHERE entity = :entity
                                AND objectId = media.mediaId
                                AND view = 1
                            ) AS groupsWithPermissions, ';
        $params['entity'] = 'Xibo\\Entity\\Media';

        $select .= '   media.originalFileName AS fileName ';

        $body = ' FROM media ';
        $body .= '   LEFT OUTER JOIN media parentmedia ';
        $body .= '   ON parentmedia.editedMediaId = media.mediaId ';

        // Media might be linked to the system user (userId 0)
        $body .= '   LEFT OUTER JOIN `user` ON `user`.userId = `media`.userId ';

        if ($sanitizedFilter->getInt('displayGroupId') !== null) {
            $body .= '
                INNER JOIN `lkmediadisplaygroup`
                ON lkmediadisplaygroup.mediaid = media.mediaid
                    AND lkmediadisplaygroup.displayGroupId = :displayGroupId
            ';

            $params['displayGroupId'] = $sanitizedFilter->getInt('displayGroupId');
        }

        $body .= ' WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('allModules') == 0) {
            $body .= ' AND media.type <> \'module\' ';
        }

        if ($sanitizedFilter->getInt('assignable', ['default'=> -1]) == 1) {
            $body .= '
                AND media.type <> \'genericfile\'
                AND media.type <> \'playersoftware\'
                AND media.type <> \'savedreport\'
                AND media.type <> \'font\'
            ';
        }

        if ($sanitizedFilter->getInt('assignable', ['default'=> -1]) == 0) {
            $body .= '
                AND (media.type = \'genericfile\'
                OR media.type = \'playersoftware\'
                OR media.type = \'savedreport\'
                OR media.type = \'font\')
            ';
        }

        // Unused only?
        if ($sanitizedFilter->getInt('unusedOnly') === 1) {
            $body .= '
                AND `media`.`mediaId` NOT IN (SELECT `mediaId` FROM `display_media`)
                AND `media`.`mediaId` NOT IN (SELECT `mediaId` FROM `lkwidgetmedia`)
                AND `media`.`mediaId` NOT IN (SELECT `mediaId` FROM `lkmediadisplaygroup`)
                AND `media`.`mediaId` NOT IN (SELECT `mediaId` FROM `menu_category` WHERE `mediaId` IS NOT NULL)
                AND `media`.`mediaId` NOT IN (SELECT `mediaId` FROM `menu_product` WHERE `mediaId` IS NOT NULL)
                AND `media`.`mediaId` NOT IN (
                    SELECT `backgroundImageId` FROM `layout` WHERE `backgroundImageId` IS NOT NULL
                )
                AND `media`.`type` <> \'module\'
                AND `media`.`type` <> \'font\'
                AND `media`.`type` <> \'playersoftware\'
                AND `media`.`type` <> \'savedreport\'
            ';

            // DataSets with library images
            $dataSetSql = '
                SELECT dataset.dataSetId, datasetcolumn.heading
                  FROM dataset
                    INNER JOIN datasetcolumn
                    ON datasetcolumn.DataSetID = dataset.DataSetID
                 WHERE DataTypeID = 5 AND `datasetcolumn`.dataSetColumnTypeId <> 2;
            ';

            $dataSets = $this->getStore()->select($dataSetSql, []);

            if (count($dataSets) > 0) {
                $body .= ' AND media.mediaID NOT IN (';

                $first = true;
                foreach ($dataSets as $dataSet) {
                    $sanitizedDataSet = $this->getSanitizer($dataSet);

                    if (!$first) {
                        $body .= ' UNION ALL ';
                    }

                    $first = false;

                    $dataSetId = $sanitizedDataSet->getInt('dataSetId');
                    $heading = $sanitizedDataSet->getString('heading');

                    $body .= ' SELECT `' .  $heading . '` AS mediaId FROM `dataset_' . $dataSetId . '`';
                }

                $body .= ') ';
            }
        }

        // Unlinked only?
        if ($sanitizedFilter->getInt('unlinkedOnly') === 1) {
            $body .= '
                AND `media`.`mediaId` NOT IN (SELECT `mediaId` FROM `display_media`)
            ';
        }

        if ($sanitizedFilter->getString('name') != null) {
            $terms = explode(',', $sanitizedFilter->getString('name'));
            $logicalOperator = $sanitizedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'media',
                'name',
                $terms,
                $body,
                $params,
                ($sanitizedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        if ($sanitizedFilter->getString('nameExact') != '') {
            $body .= ' AND media.name = :exactName ';
            $params['exactName'] = $sanitizedFilter->getString('nameExact');
        }

        if ($sanitizedFilter->getInt('mediaId', ['default'=> -1]) != -1) {
            $body .= ' AND media.mediaId = :mediaId ';
            $params['mediaId'] = $sanitizedFilter->getInt('mediaId');
        } else if ($sanitizedFilter->getInt('parentMediaId') !== null) {
            $body .= ' AND media.editedMediaId = :mediaId ';
            $params['mediaId'] = $sanitizedFilter->getInt('parentMediaId');
        } else if ($sanitizedFilter->getInt('isEdited', ['default' => -1]) != -1) {
            $body .= ' AND media.isEdited <> -1 ';
        } else {
            $body .= ' AND media.isEdited = 0 ';
        }

        if ($sanitizedFilter->getString('type') != '') {
            $body .= 'AND media.type = :type ';
            $params['type'] = $sanitizedFilter->getString('type');
        }

        if (!empty($sanitizedFilter->getArray('types'))) {
            $body .= 'AND (';
            foreach ($sanitizedFilter->getArray('types') as $key => $type) {
                $body .= 'media.type = :types' . $key . ' ';

                if ($key !== array_key_last($sanitizedFilter->getArray('types'))) {
                    $body .= ' OR ';
                }

                $params['types' .  $key] = $type;
            }
            $body .= ') ';
        }

        if ($sanitizedFilter->getInt('imageProcessing') !== null) {
            $body .= 'AND ( media.type = \'image\' OR (media.type = \'module\' AND media.moduleSystemFile = 0) ) ';
        }

        if ($sanitizedFilter->getString('storedAs') != '') {
            $body .= 'AND media.storedAs = :storedAs ';
            $params['storedAs'] = $sanitizedFilter->getString('storedAs');
        }

        if ($sanitizedFilter->getInt('ownerId') !== null) {
            $body .= ' AND media.userid = :ownerId ';
            $params['ownerId'] = $sanitizedFilter->getInt('ownerId');
        }

        // User Group filter
        if ($sanitizedFilter->getInt('ownerUserGroupId', ['default'=> 0]) != 0) {
            $body .= ' AND media.userid IN (SELECT DISTINCT userId FROM `lkusergroup` WHERE groupId =  :ownerUserGroupId) ';
            $params['ownerUserGroupId'] = $sanitizedFilter->getInt('ownerUserGroupId');
        }

        if ($sanitizedFilter->getInt('released') !== null) {
            $body .= ' AND media.released = :released ';
            $params['released'] = $sanitizedFilter->getInt('released');
        }

        if ($sanitizedFilter->getCheckbox('unreleasedOnly') === 1) {
            $body .= ' AND media.released <> 1 ';
        }

        if ($sanitizedFilter->getInt('retired', ['default'=> -1]) == 1)
            $body .= ' AND media.retired = 1 ';

        if ($sanitizedFilter->getInt('retired', ['default'=> -1]) == 0)
            $body .= ' AND media.retired = 0 ';

        // Expired files?
        if ($sanitizedFilter->getInt('expires') != 0) {
            $body .= ' 
                AND media.expires < :expires 
                AND IFNULL(media.expires, 0) <> 0 
                AND ( media.mediaId NOT IN (SELECT mediaId FROM `lkwidgetmedia`) OR media.type <> \'module\')
            ';
            $params['expires'] = $sanitizedFilter->getInt('expires');
        }

        if ($sanitizedFilter->getInt('layoutId') !== null) {
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

            // include Media only for non dynamic Playlists #2392
            if ($sanitizedFilter->getInt('excludeDynamicPlaylistMedia') === 1) {
                $body .= ' AND lkplaylistplaylist.childId IN (SELECT playlistId FROM playlist WHERE playlist.playlistId = lkplaylistplaylist.childId AND playlist.isDynamic = 0) ';
            }

            if ($sanitizedFilter->getInt('widgetId') !== null) {
                $body .= ' AND `widget`.widgetId = :widgetId ';
                $params['widgetId'] = $sanitizedFilter->getInt('widgetId');
            }

            $body .= '    )
                AND media.type <> \'module\'
            ';

            if ($sanitizedFilter->getInt('includeLayoutBackgroundImage') === 1) {
                $body .= ' OR media.mediaId IN ( SELECT `layout`.backgroundImageId FROM `layout` WHERE `layout`.layoutId = :layoutId ) ';
            }

            $params['layoutId'] = $sanitizedFilter->getInt('layoutId');
        }

        if ($sanitizedFilter->getInt('campaignId') !== null) {
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
                        INNER JOIN `lkcampaignlayout` lkcl
                        ON lkcl.layoutid = region.layoutid
                        AND lkcl.CampaignID = :campaignId)';

            $params['campaignId'] = $sanitizedFilter->getInt('campaignId');
        }

        // Tags
        if ($sanitizedFilter->getString('tags') != '') {
            $tagFilter = $sanitizedFilter->getString('tags');

            if (trim($tagFilter) === '--no-tag') {
                $body .= ' AND `media`.mediaId NOT IN (
                    SELECT `lktagmedia`.mediaId
                     FROM tag
                        INNER JOIN `lktagmedia`
                        ON `lktagmedia`.tagId = tag.tagId
                    )
                ';
            } else {
                $operator = $sanitizedFilter->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';
                $logicalOperator = $sanitizedFilter->getString('logicalOperator', ['default' => 'OR']);
                $allTags = explode(',', $tagFilter);
                $notTags = [];
                $tags = [];

                foreach ($allTags as $tag) {
                    if (str_starts_with($tag, '-')) {
                        $notTags[] = ltrim(($tag), '-');
                    } else {
                        $tags[] = $tag;
                    }
                }

                if (!empty($notTags)) {
                    $body .= ' AND `media`.mediaId NOT IN (
                    SELECT `lktagmedia`.mediaId
                      FROM tag
                        INNER JOIN `lktagmedia`
                        ON `lktagmedia`.tagId = tag.tagId
                    ';

                    $this->tagFilter(
                        $notTags,
                        'lktagmedia',
                        'lkTagMediaId',
                        'mediaId',
                        $logicalOperator,
                        $operator,
                        true,
                        $body,
                        $params
                    );
                }

                if (!empty($tags)) {
                    $body .= ' AND `media`.mediaId IN (
                    SELECT `lktagmedia`.mediaId
                      FROM tag
                        INNER JOIN `lktagmedia`
                        ON `lktagmedia`.tagId = tag.tagId
                    ';

                    $this->tagFilter(
                        $tags,
                        'lktagmedia',
                        'lkTagMediaId',
                        'mediaId',
                        $logicalOperator,
                        $operator,
                        false,
                        $body,
                        $params
                    );
                }
            }
        }

        // File size
        if ($sanitizedFilter->getString('fileSize') != null) {
            $fileSize = $this->parseComparisonOperator($sanitizedFilter->getString('fileSize'));

            $body .= ' AND `media`.fileSize ' . $fileSize['operator'] . ' :fileSize ';
            $params['fileSize'] = $fileSize['variable'];
        }

        // Duration
        if ($sanitizedFilter->getInt('duration') != null) {
            $duration = $this->parseComparisonOperator($sanitizedFilter->getInt('duration'));

            $body .= ' AND `media`.duration ' . $duration['operator'] . ' :duration ';
            $params['duration'] = $duration['variable'];
        }

        if ($sanitizedFilter->getInt('folderId') !== null) {
            $body .= ' AND media.folderId = :folderId ';
            $params['folderId'] = $sanitizedFilter->getInt('folderId');
        }

        if ($sanitizedFilter->getInt('onlyMenuBoardAllowed') !== null) {
            $body .= ' AND ( media.type = \'image\' OR media.type = \'video\' ) ';
        }

        if ($sanitizedFilter->getString('orientation') !== null) {
            $body .= ' AND media.orientation = :orientation ';
            $params['orientation'] = $sanitizedFilter->getString('orientation');
        }

        if ($sanitizedFilter->getInt('requiresMetaUpdate') === 1) {
            $body .= ' AND (`media`.orientation IS NULL OR IFNULL(`media`.width, 0) = 0)';
        }

        // View Permissions
        $this->viewPermissionSql(
            'Xibo\Entity\Media',
            $body,
            $params,
            '`media`.mediaId',
            '`media`.userId',
            $filterBy,
            '`media`.permissionsFolderId'
        );

        // Sorting?
        $order = '';
        if (is_array($sortOrder)) {
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($sanitizedFilter->hasParam('start') && $sanitizedFilter->hasParam('length')) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0])
                . ', ' . $sanitizedFilter->getInt('length', ['default'=> 10]);
        }

        $sql = $select . $body . $order . $limit;
        $mediaIds = [];

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $media = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'duration',
                    'size',
                    'released',
                    'moduleSystemFile',
                    'isEdited',
                    'expires',
                    'valid',
                    'width',
                    'height'
                ]
            ]);

            $mediaIds[] = $media->mediaId;

            $media->excludeProperty('layoutBackgroundImages');
            $media->excludeProperty('widgets');
            $media->excludeProperty('displayGroups');

            $entries[] = $media;
        }

        // decorate with TagLinks
        if (count($entries) > 0) {
            $this->decorateWithTagLinks('lktagmedia', 'mediaId', $mediaIds, $entries);
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
