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

class MediaFactory extends BaseFactory
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
     * Create System Module File
     * @param $name
     * @param string $file
     * @return Media
     */
    public static function createModuleSystemFile($name, $file = '')
    {
        $media = self::createModuleFile($name, $file, 1);
        $media->moduleSystemFile = 1;
        return $media;
    }

    /**
     * Create Module File
     * @param $name
     * @param $file
     * @return Media
     */
    public static function createModuleFile($name, $file = '', $systemFile = 0)
    {
        if ($file == '') {
            $file = $name;
            $name = basename($file);
        }

        try {
            $media = MediaFactory::getByName($name);

            if ($media->mediaType != 'module')
                throw new NotFoundException();
        }
        catch (NotFoundException $e) {
            $media = new Media();
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
    public static function createModuleFileFromFolder($folder)
    {
        $media = [];

        if (!is_dir($folder))
            throw new \InvalidArgumentException(__('Not a folder'));

        foreach (array_diff(scandir($folder), array('..', '.')) as $file) {

            $file = MediaFactory::createModuleSystemFile($file, $folder . DIRECTORY_SEPARATOR . $file);
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
        $media = MediaFactory::query(null, array('disableUserCheck' => 1, 'mediaId' => $mediaId, 'allModules' => 1));

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
    public static function getParentById($mediaId)
    {
        $media = MediaFactory::query(null, array('disableUserCheck' => 1, 'parentMediaId' => $mediaId, 'allModules' => 1));

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
        $media = MediaFactory::query(null, array('disableUserCheck' => 1, 'name' => $name, 'allModules' => 1));

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
        return MediaFactory::query(null, array('disableUserCheck' => 1, 'ownerId' => $ownerId));
    }

    /**
     * Get by Type
     * @param string $type
     * @return array[Media]
     */
    public static function getByMediaType($type)
    {
        return MediaFactory::query(null, array('disableUserCheck' => 1, 'type' => $type, 'allModules' => 1));
    }

    /**
     * Get by Display Group Id
     * @param int $displayGroupId
     * @return array[Media]
     */
    public static function getByDisplayGroupId($displayGroupId)
    {
        return MediaFactory::query(null, array('disableUserCheck' => 1, 'displayGroupId' => $displayGroupId));
    }

    /**
     * Get Media by LayoutId
     * @param int $layoutId
     * @return array[Media]
     */
    public static function getByLayoutId($layoutId)
    {
        return MediaFactory::query(null, ['disableUserCheck' => 1, 'layoutId' => $layoutId]);
    }

    public static function query($sortOrder = null, $filterBy = null)
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

        if (Sanitize::getInt('displayGroupId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkmediadisplaygroup`
                ON lkmediadisplaygroup.mediaid = media.mediaid
                    AND lkmediadisplaygroup.displayGroupId = :displayGroupId
            ';

            $params['displayGroupId'] = Sanitize::getInt('displayGroupId', $filterBy);
        }

        $body .= " WHERE 1 = 1 ";

        // View Permissions
        self::viewPermissionSql('Xibo\Entity\Media', $body, $params, '`media`.mediaId', '`media`.userId', $filterBy);

        if (Sanitize::getInt('allModules', $filterBy) == 0) {
            $body .= ' AND media.type <> \'module\' ';
        }

        // Unused only?
        if (Sanitize::getInt('unusedOnly', $filterBy) !== null) {
            $body .= '
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
                    $body .= " AND media.name NOT LIKE :notLike ";
                    $params['notLike'] = '%' . ltrim($searchName, '-') . '%';
                }
                else {
                    $body .= " AND media.name LIKE :like ";
                    $params['like'] = '%' . $searchName . '%';
                }
            }
        }

        if (Sanitize::getInt('mediaId', -1, $filterBy) != -1) {
            $body .= " AND media.mediaId = :mediaId ";
            $params['mediaId'] = Sanitize::getInt('mediaId', $filterBy);
        } else if (Sanitize::getInt('parentMediaId', $filterBy) !== null) {
            $body .= ' AND media.editedMediaId = :mediaId ';
            $params['mediaId'] = Sanitize::getInt('parentMediaId', $filterBy);
        } else {
            $body .= ' AND media.isEdited = 0 ';
        }

        if (Sanitize::getString('type', $filterBy) != '') {
            $body .= 'AND media.type = :type ';
            $params['type'] = Sanitize::getString('type', $filterBy);
        }

        if (Sanitize::getString('storedAs', $filterBy) != '') {
            $body .= 'AND media.storedAs = :storedAs ';
            $params['storedAs'] = Sanitize::getString('storedAs', $filterBy);
        }

        if (Sanitize::getInt('ownerId', $filterBy) !== null) {
            $body .= " AND media.userid = :ownerId ";
            $params['ownerId'] = Sanitize::getInt('ownerId', $filterBy);
        }

        if (Sanitize::getInt('retired', -1, $filterBy) == 1)
            $body .= " AND media.retired = 1 ";

        if (Sanitize::getInt('retired', -1, $filterBy) == 0)
            $body .= " AND media.retired = 0 ";

        // Expired files?
        if (Sanitize::getInt('expires', $filterBy) != 0) {
            $body .= ' AND media.expires < :expires AND IFNULL(media.expires, 0) <> 0 ';
            $params['expires'] = Sanitize::getInt('expires', $filterBy);
        }

        if (Sanitize::getInt('layoutId', $filterBy) !== null) {
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
                    WHERE region.layoutId = :layoutId
                )
                AND media.type <> \'module\'
            ';
            $params['layoutId'] = Sanitize::getInt('layoutId', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Media())->hydrate($row, [
                'intProperties' => [
                    'duration', 'size'
                ]
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['entity']);
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}