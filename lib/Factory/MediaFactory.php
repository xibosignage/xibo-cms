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

class MediaFactory
{
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
     * Get by Owner Id
     * @param int $ownerId
     * @return array[Media]
     * @throws NotFoundException
     */
    public static function getByOwnerId($ownerId)
    {
        //TODO add filtering
        return MediaFactory::query(null, array('ownerId' => $ownerId));
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        $params = array();
        $sql  = '';
        $sql .= "SELECT  media.mediaID, ";
        $sql .= "   media.name, ";
        $sql .= "   media.type, ";
        $sql .= "   media.duration, ";
        $sql .= "   media.userID, ";
        $sql .= "   media.FileSize, ";
        $sql .= "   media.storedAs, ";
        $sql .= "   media.valid, ";
        $sql .= "   media.moduleSystemFile, ";
        $sql .= "   media.expires, ";
        $sql .= "   IFNULL((SELECT parentmedia.mediaid FROM media parentmedia WHERE parentmedia.editedmediaid = media.mediaid),0) AS ParentID, ";

        if (\Xibo\Helper\Sanitize::int('showTags', $filterBy) == 1)
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

        $sql .= "   media.originalFileName ";
        $sql .= " FROM media ";
        $sql .= "   LEFT OUTER JOIN media parentmedia ";
        $sql .= "   ON parentmedia.MediaID = media.MediaID ";
        $sql .= "   INNER JOIN `user` ON `user`.userId = `media`.userId ";

        if (\Xibo\Helper\Sanitize::int('showTags', $filterBy) == 1) {
            $sql .= " LEFT OUTER JOIN lktagmedia ON lktagmedia.mediaId = media.mediaId ";
            $sql .= " LEFT OUTER JOIN tag ON tag.tagId = lktagmedia.tagId";
        }

        $sql .= " WHERE media.isEdited = 0 ";

        if (\Xibo\Helper\Sanitize::int('allModules', $filterBy) == 0) {
            $sql .= "AND media.type <> 'module'";
        }

        if (\Kit::GetParam('name', $filterBy, _STRING) != '') {
            // convert into a space delimited array
            $names = explode(' ', \Kit::GetParam('name', $filterBy, _STRING));
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

        if (\Xibo\Helper\Sanitize::int('mediaId', -1, $filterBy) != -1) {
            $sql .= " AND media.mediaId = :mediaId ";
            $params['mediaId'] = \Xibo\Helper\Sanitize::int('mediaId', $filterBy);
        }

        if (\Kit::GetParam('type', $filterBy, _STRING) != '') {
            $sql .= 'AND media.type = :type';
            $params['type'] = \Kit::GetParam('type', $filterBy, _STRING);
        }

        if (\Kit::GetParam('storedAs', $filterBy, _STRING) != '') {
            $sql .= 'AND media.storedAs = :storedAs';
            $params['storedAs'] = \Kit::GetParam('storedAs', $filterBy, _STRING);
        }

        if (\Xibo\Helper\Sanitize::int('ownerId', $filterBy) != 0) {
            $sql .= " AND media.userid = :ownerId ";
            $params['ownerId'] = \Xibo\Helper\Sanitize::int('ownerid', $filterBy);
        }

        if (\Xibo\Helper\Sanitize::int('retired', -1, $filterBy) == 1)
            $sql .= " AND media.retired = 1 ";

        if (\Xibo\Helper\Sanitize::int('retired', -1, $filterBy) == 0)
            $sql .= " AND media.retired = 0 ";

        // Expired files?
        if (\Xibo\Helper\Sanitize::int('expires', $filterBy) != 0) {
            $sql .= ' AND media.expires < :expires AND IFNULL(media.expires, 0) <> 0 ';
            $params['expires'] = \Xibo\Helper\Sanitize::int('expires', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        \Xibo\Helper\Log::sql($sql, $params);

        foreach (\Xibo\Storage\PDOConnect::select($sql, $params) as $row) {
            $media = new Media();
            $media->mediaId = \Xibo\Helper\Sanitize::int($row['mediaID']);
            $media->name = \Xibo\Helper\Sanitize::string($row['name']);
            $media->mediaType = \Kit::ValidateParam($row['type'], _WORD);
            $media->duration = \Xibo\Helper\Sanitize::double($row['duration']);
            $media->ownerId = \Xibo\Helper\Sanitize::int($row['userID']);
            $media->fileSize = \Xibo\Helper\Sanitize::int($row['FileSize']);
            $media->parentId = \Xibo\Helper\Sanitize::int($row['ParentID']);
            $media->fileName = \Xibo\Helper\Sanitize::string($row['originalFileName']);
            $media->tags = \Xibo\Helper\Sanitize::string($row['tags']);
            $media->storedAs = \Xibo\Helper\Sanitize::string($row['storedAs']);
            $media->valid = \Xibo\Helper\Sanitize::int($row['valid']);
            $media->moduleSystemFile = \Xibo\Helper\Sanitize::int($row['moduleSystemFile']);
            $media->expires = \Xibo\Helper\Sanitize::int($row['expires']);
            $media->owner = \Xibo\Helper\Sanitize::string($row['owner']);
            $media->groupsWithPermissions = \Xibo\Helper\Sanitize::string($row['groupsWithPermissions']);

            $entries[] = $media;
        }

        return $entries;
    }
}