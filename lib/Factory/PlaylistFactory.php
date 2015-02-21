<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (PlaylistFactory.php) is part of Xibo.
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


use Xibo\Entity\Playlist;

class PlaylistFactory
{
    /**
     * Load Playlists by
     * @param $regionId
     * @return array[Playlist]
     */
    public static function getByRegionId($regionId)
    {
        //TODO fill in playlist factory
        return PlaylistFactory::query(null, array('regionId' => $regionId));
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        $sql = 'SELECT playlist.* FROM `playlist` INNER JOIN `lkregionplaylist` ON lkregionplaylist.playlistId = playlist.playlistId WHERE lkregionplaylist.regionId = :regionId';

        foreach (\PDOConnect::select($sql, array('regionId' => \Kit::GetParam('regionId', $filterBy, _INT))) as $row) {
            $playlist = new Playlist();
            $playlist->name = \Kit::ValidateParam($row['name'], _STRING);
            $playlist->ownerId = \Kit::ValidateParam($row['ownerId'], _INT);
            $playlist->playlistId = \Kit::ValidateParam($row['playlistId'], _INT);

            $entries[] = $playlist;
        }

        return $entries;
    }
}