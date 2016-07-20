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
        $media = $this->createModuleFile($name, $file, 1);
        $media->moduleSystemFile = 1;
        return $media;
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
            $media = $this->getByName($name);

            // Reassert the new file (which we might want to download)
            $media->fileName = $file;

            if ($media->mediaType != 'module')
                throw new NotFoundException();
        }
        catch (NotFoundException $e) {
            $media = $this->createEmpty();
            $media->name = $name;
            $media->fileName = $file;
            $media->mediaType = 'module';
            $media->expires = 0;
            $media->storedAs = $name;
            $media->ownerId = 1;
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

            $file = $this->createModuleSystemFile($file, $folder . DIRECTORY_SEPARATOR . $file);
            $file->moduleSystemFile = true;

            $media[] = $file;
        }

        return $media;
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
     * Get by Owner Id
     * @param int $ownerId
     * @return array[Media]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'ownerId' => $ownerId));
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
     * @return array[Media]
     */
    public function getByLayoutId($layoutId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'layoutId' => $layoutId]);
    }

    /**
     * Get Media by LayoutId
     * @param int $layoutId
     * @param int $widgetId
     * @return array[Media]
     */
    public function getByLayoutAndWidget($layoutId, $widgetId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'layoutId' => $layoutId, 'widgetId' => $widgetId]);
    }

    /**
     * @param null $sortOrder
     * @param null $filterBy
     * @return Media[]
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        if ($sortOrder === null)
            $sortOrder = ['name'];

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
               IFNULL((SELECT parentmedia.mediaid FROM media parentmedia WHERE parentmedia.editedmediaid = media.mediaid),0) AS parentId,
        ';

        if (DBVERSION >= 125) {
            $select .= '
               `media`.released,
               `media`.apiRef,
            ';
        }

        $select .= " (SELECT GROUP_CONCAT(DISTINCT tag) FROM tag INNER JOIN lktagmedia ON lktagmedia.tagId = tag.tagId WHERE lktagmedia.mediaId = media.mediaID GROUP BY lktagmedia.mediaId) AS tags, ";
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
        $body .= "   ON parentmedia.MediaID = media.MediaID ";
        $body .= "   INNER JOIN `user` ON `user`.userId = `media`.userId ";

        if ($this->getSanitizer()->getInt('displayGroupId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkmediadisplaygroup`
                ON lkmediadisplaygroup.mediaid = media.mediaid
                    AND lkmediadisplaygroup.displayGroupId = :displayGroupId
            ';

            $params['displayGroupId'] = $this->getSanitizer()->getInt('displayGroupId', $filterBy);
        }

        $body .= " WHERE 1 = 1 ";

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\Media', $body, $params, '`media`.mediaId', '`media`.userId', $filterBy);

        if ($this->getSanitizer()->getInt('allModules', $filterBy) == 0) {
            $body .= ' AND media.type <> \'module\' ';
        }

        // Unused only?
        if ($this->getSanitizer()->getInt('unusedOnly', $filterBy) !== null) {
            $body .= '
                AND media.mediaId NOT IN (SELECT mediaId FROM `lkwidgetmedia`)
                AND media.mediaId NOT IN (SELECT mediaId FROM `lkmediadisplaygroup`)
                AND media.type <> \'module\'
                AND media.type <> \'font\'
            ';
        }

        if ($this->getSanitizer()->getString('name', $filterBy) != '') {
            // convert into a space delimited array
            $names = explode(' ', $this->getSanitizer()->getString('name', $filterBy));
            $i = 0;
            foreach($names as $searchName) {
                $i++;
                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $body .= ' AND media.name NOT LIKE :notLike' . $i . ' ';
                    $params['notLike' . $i] = '%' . ltrim($searchName, '-') . '%';
                }
                else {
                    $body .= ' AND media.name LIKE :like' . $i . ' ';
                    $params['like' . $i] = '%' . $searchName . '%';
                }
            }
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
        } else {
            $body .= ' AND media.isEdited = 0 ';
        }

        if ($this->getSanitizer()->getString('type', $filterBy) != '') {
            $body .= 'AND media.type = :type ';
            $params['type'] = $this->getSanitizer()->getString('type', $filterBy);
        }

        if ($this->getSanitizer()->getString('storedAs', $filterBy) != '') {
            $body .= 'AND media.storedAs = :storedAs ';
            $params['storedAs'] = $this->getSanitizer()->getString('storedAs', $filterBy);
        }

        if ($this->getSanitizer()->getInt('ownerId', $filterBy) !== null) {
            $body .= " AND media.userid = :ownerId ";
            $params['ownerId'] = $this->getSanitizer()->getInt('ownerId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('retired', -1, $filterBy) == 1)
            $body .= " AND media.retired = 1 ";

        if ($this->getSanitizer()->getInt('retired', -1, $filterBy) == 0)
            $body .= " AND media.retired = 0 ";

        // Expired files?
        if ($this->getSanitizer()->getInt('expires', $filterBy) != 0) {
            $body .= ' AND media.expires < :expires AND IFNULL(media.expires, 0) <> 0 ';
            $params['expires'] = $this->getSanitizer()->getInt('expires', $filterBy);
        }

        if ($this->getSanitizer()->getInt('layoutId', $filterBy) !== null) {
            $body .= '
                AND media.mediaId IN (
                    SELECT `lkwidgetmedia`.mediaId
                      FROM`lkwidgetmedia`
                        INNER JOIN `widget`
                        ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                        INNER JOIN `lkregionplaylist`
                        ON `lkregionplaylist`.playlistId = `widget`.playlistId
                        INNER JOIN `region`
                        ON `region`.regionId = `lkregionplaylist`.regionId
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
            $body .= " AND `media`.mediaId IN (
                SELECT `lktagmedia`.mediaId
                  FROM tag
                    INNER JOIN `lktagmedia`
                    ON `lktagmedia`.tagId = tag.tagId
                ";
            $i = 0;
            foreach (explode(',', $this->getSanitizer()->getString('tags', $filterBy)) as $tag) {
                $i++;

                if ($i == 1)
                    $body .= " WHERE tag LIKE :tags$i ";
                else
                    $body .= " OR tag LIKE :tags$i ";

                $params['tags' . $i] =  '%' . $tag . '%';
            }

            $body .= " ) ";
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
                    'duration', 'size', 'released'
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