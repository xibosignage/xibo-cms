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

        if (\Kit::GetParam('showTags', $filterBy, _INT) == 1)
            $sql .= " tag.tag AS tags, ";
        else
            $sql .= " (SELECT GROUP_CONCAT(DISTINCT tag) FROM tag INNER JOIN lktagmedia ON lktagmedia.tagId = tag.tagId WHERE lktagmedia.mediaId = media.mediaID GROUP BY lktagmedia.mediaId) AS tags, ";

        $sql .= "   media.originalFileName ";
        $sql .= " FROM media ";
        $sql .= "   LEFT OUTER JOIN media parentmedia ";
        $sql .= "   ON parentmedia.MediaID = media.MediaID ";

        if (\Kit::GetParam('showTags', $filterBy, _INT) == 1) {
            $sql .= " LEFT OUTER JOIN lktagmedia ON lktagmedia.mediaId = media.mediaId ";
            $sql .= " LEFT OUTER JOIN tag ON tag.tagId = lktagmedia.tagId";
        }

        $sql .= " WHERE media.isEdited = 0 ";

        if (\Kit::GetParam('allModules', $filterBy, _INT) == 0) {
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

        if (\Kit::GetParam('mediaId', $filterBy, _INT, -1) != -1) {
            $sql .= " AND media.mediaId = :mediaId ";
            $params['mediaId'] = \Kit::GetParam('mediaId', $filterBy, _INT);
        }

        if (\Kit::GetParam('type', $filterBy, _STRING) != '') {
            $sql .= 'AND media.type = :type';
            $params['type'] = \Kit::GetParam('type', $filterBy, _STRING);
        }

        if (\Kit::GetParam('storedAs', $filterBy, _STRING) != '') {
            $sql .= 'AND media.storedAs = :storedAs';
            $params['storedAs'] = \Kit::GetParam('storedAs', $filterBy, _STRING);
        }

        if (\Kit::GetParam('ownerId', $filterBy, _INT) != 0) {
            $sql .= " AND media.userid = :ownerId ";
            $params['ownerId'] = \Kit::GetParam('ownerid', $filterBy, _INT);
        }

        if (\Kit::GetParam('retired', $filterBy, _INT, -1) == 1)
            $sql .= " AND media.retired = 1 ";

        if (\Kit::GetParam('retired', $filterBy, _INT, -1) == 0)
            $sql .= " AND media.retired = 0 ";

        // Expired files?
        if (\Kit::GetParam('expires', $filterBy, _INT) != 0) {
            $sql .= ' AND media.expires < :expires AND IFNULL(media.expires, 0) <> 0 ';
            $params['expires'] = \Kit::GetParam('expires', $filterBy, _INT);
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        \Debug::sql($sql, $params);

        foreach (\PDOConnect::select($sql, $params) as $row) {
            $media = new Media();
            $media->mediaId = \Kit::ValidateParam($row['mediaID'], _INT);
            $media->name = \Kit::ValidateParam($row['name'], _STRING);
            $media->mediaType = \Kit::ValidateParam($row['type'], _WORD);
            $media->duration = \Kit::ValidateParam($row['duration'], _DOUBLE);
            $media->ownerId = \Kit::ValidateParam($row['userID'], _INT);
            $media->fileSize = \Kit::ValidateParam($row['FileSize'], _INT);
            $media->parentId = \Kit::ValidateParam($row['ParentID'], _INT);
            $media->fileName = \Kit::ValidateParam($row['originalFileName'], _STRING);
            $media->tags = \Kit::ValidateParam($row['tags'], _STRING);
            $media->storedAs = \Kit::ValidateParam($row['storedAs'], _STRING);
            $media->valid = \Kit::ValidateParam($row['valid'], _INT);
            $media->moduleSystemFile = \Kit::ValidateParam($row['moduleSystemFile'], _INT);
            $media->expires = \Kit::ValidateParam($row['expires'], _INT);

            $entries[] = $media;
        }

        return $entries;
    }
}