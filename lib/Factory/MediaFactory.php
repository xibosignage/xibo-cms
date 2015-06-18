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
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class MediaFactory
{
    /**
     * Create New Media
     * @param string $name
     * @param string $fileName
     * @param string $type
     * @param int $ownerId
     * @param int $duration
     * @return Media
     */
    public static function create($name, $fileName, $type, $ownerId, $duration = 0)
    {
        $media = new Media();
        $media->name = $name;
        $media->fileName = $fileName;
        $media->mediaType = $type;
        $media->ownerId = $ownerId;
        $media->duration = $duration;

        return $media;
    }

    /**
     * Create System Media File
     * @param $name
     * @param $file
     * @return Media
     */
    public static function createModuleFile($name, $file = '')
    {
        if ($file == '') {
            $file = $name;
            $name = basename($file);
        }

        try {
            $media = MediaFactory::getByName($name);
        }
        catch (NotFoundException $e) {
            $media = new Media();
            $media->name = $name;
        }

        $media->fileName = $file;
        $media->mediaType = 'module';
        $media->expires = 0;
        $media->storedAs = $name;

        return $media;
    }

    /**
     * Create module files from folder
     * @param string $folder The path to the folder to add.
     * @return array[Media]
     */
    public static function createModuleFileFromFolder($folder)
    {
        $media = [];

        if (!is_dir($folder))
            throw new \InvalidArgumentException(__('Not a folder'));

        foreach (array_diff(scandir($folder), array('..', '.')) as $file) {

            $file = MediaFactory::createModuleFile($file, $folder . DIRECTORY_SEPARATOR . $file);
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
    public static function getById($mediaId)
    {
        $media = MediaFactory::query(null, array('mediaId' => $mediaId));

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
    public static function getByName($name)
    {
        $media = MediaFactory::query(null, array('name' => $name));

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
    public static function getByOwnerId($ownerId)
    {
        return MediaFactory::query(null, array('ownerId' => $ownerId));
    }

    /**
     * Get by Type
     * @param string $type
     * @return array[Media]
     */
    public static function getByMediaType($type)
    {
        return MediaFactory::query(null, array('type' => $type));
    }

    /**
     * Get by Display Group Id
     * @param int $displayGroupId
     * @return array[Media]
     */
    public static function getByDisplayGroupId($displayGroupId)
    {
        return MediaFactory::query(null, array('displayGroupId' => $displayGroupId));
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        $params = array();
        $sql  = '';
        $sql .= "SELECT  media.mediaId, ";
        $sql .= "   media.name, ";
        $sql .= "   media.type AS mediaType, ";
        $sql .= "   media.duration, ";
        $sql .= "   media.userId AS ownerId, ";
        $sql .= "   media.fileSize, ";
        $sql .= "   media.storedAs, ";
        $sql .= "   media.valid, ";
        $sql .= "   media.moduleSystemFile, ";
        $sql .= "   media.expires, ";
        $sql .= "   media.md5, ";
        $sql .= "   media.retired, ";
        $sql .= "   media.isEdited, ";
        $sql .= "   IFNULL((SELECT parentmedia.mediaid FROM media parentmedia WHERE parentmedia.editedmediaid = media.mediaid),0) AS parentId, ";

        if (Sanitize::getInt('showTags', $filterBy) == 1)
            $sql .= " tag.tag AS tags, ";
        else
            $sql .= " (SELECT GROUP_CONCAT(DISTINCT tag) FROM tag INNER JOIN lktagmedia ON lktagmedia.tagId = tag.tagId WHERE lktagmedia.mediaId = media.mediaID GROUP BY lktagmedia.mediaId) AS tags, ";

        $sql .= "        `user`.UserName AS owner, ";
        $sql .= "     (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                              FROM `permission`
                                INNER JOIN `permissionentity`
                                ON `permissionentity`.entityId = permission.entityId
                                INNER JOIN `group`
                                ON `group`.groupId = `permission`.groupId
                             WHERE entity = :entity
                                AND objectId = media.mediaId
                            ) AS groupsWithPermissions, ";
        $params['entity'] = 'Xibo\\Entity\\Media';

        $sql .= "   media.originalFileName AS fileName ";
        $sql .= " FROM media ";
        $sql .= "   LEFT OUTER JOIN media parentmedia ";
        $sql .= "   ON parentmedia.MediaID = media.MediaID ";
        $sql .= "   INNER JOIN `user` ON `user`.userId = `media`.userId ";

        if (Sanitize::getInt('showTags', $filterBy) == 1) {
            $sql .= " LEFT OUTER JOIN lktagmedia ON lktagmedia.mediaId = media.mediaId ";
            $sql .= " LEFT OUTER JOIN tag ON tag.tagId = lktagmedia.tagId";
        }

        if (Sanitize::getInt('displayGroupId', $filterBy) != null) {
            $sql .= '
                INNER JOIN `lkmediadisplaygroup`
                ON lkmediadisplaygroup.mediaid = media.mediaid
                    AND lkmediadisplaygroup.displayGroupId = :displayGroupId
            ';

            $params['displayGroupId'] = Sanitize::getInt('displayGroupId', $filterBy);
        }

        $sql .= " WHERE media.isEdited = 0 ";

        if (Sanitize::getInt('allModules', $filterBy) == 0) {
            $sql .= "AND media.type <> 'module'";
        }

        // Unused only?
        if (Sanitize::getInt('unusedOnly', $filterBy) != null) {
            $sql .= '
                AND media.mediaId NOT IN (SELECT mediaId FROM `lkwidgetmedia`)
                AND media.mediaId NOT IN (SELECT mediaId FROM `lkmediadisplaygroup`)
                AND media.type <> \'module\'
                AND media.type <> \'font\'
            ';
        }

        if (Sanitize::getString('name', $filterBy) != '') {
            // convert into a space delimited array
            $names = explode(' ', Sanitize::getString('name', $filterBy));
            $i = 0;
            foreach($names as $searchName) {
                $i++;
                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $sql .= " AND media.name NOT LIKE :notLike ";
                    $params['notLike'] = '%' . ltrim($searchName, '-') . '%';
                }
                else {
                    $sql .= " AND media.name LIKE :like ";
                    $params['like'] = '%' . $searchName . '%';
                }
            }
        }

        if (Sanitize::getInt('mediaId', -1, $filterBy) != -1) {
            $sql .= " AND media.mediaId = :mediaId ";
            $params['mediaId'] = Sanitize::getInt('mediaId', $filterBy);
        }

        if (Sanitize::getString('type', $filterBy) != '') {
            $sql .= 'AND media.type = :type';
            $params['type'] = Sanitize::getString('type', $filterBy);
        }

        if (Sanitize::getString('storedAs', $filterBy) != '') {
            $sql .= 'AND media.storedAs = :storedAs';
            $params['storedAs'] = Sanitize::getString('storedAs', $filterBy);
        }

        if (Sanitize::getInt('ownerId', $filterBy) != 0) {
            $sql .= " AND media.userid = :ownerId ";
            $params['ownerId'] = Sanitize::getInt('ownerid', $filterBy);
        }

        if (Sanitize::getInt('retired', -1, $filterBy) == 1)
            $sql .= " AND media.retired = 1 ";

        if (Sanitize::getInt('retired', -1, $filterBy) == 0)
            $sql .= " AND media.retired = 0 ";

        // Expired files?
        if (Sanitize::getInt('expires', $filterBy) != 0) {
            $sql .= ' AND media.expires < :expires AND IFNULL(media.expires, 0) <> 0 ';
            $params['expires'] = Sanitize::getInt('expires', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        // Paging
        if (Sanitize::getInt('start') !== null && Sanitize::getInt('length') !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start')) . ', ' . Sanitize::getInt('length', 10);
        }

        Log::sql($sql . $limit, $params);

        foreach (PDOConnect::select($sql . $limit, $params) as $row) {
            $entries[] = (new Media())->hydrate($row);
        }

        return $entries;
    }
}