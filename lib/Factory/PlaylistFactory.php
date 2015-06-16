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
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class PlaylistFactory
{
    /**
     * Load Playlists by
     * @param $regionId
     * @return array[Playlist]
     * @throws NotFoundException
     */
    public static function getByRegionId($regionId)
    {
        return PlaylistFactory::query(null, array('regionId' => $regionId));
    }

    /**
     * Get by Id
     * @param int $playlistId
     * @return Playlist
     * @throws NotFoundException
     */
    public static function getById($playlistId)
    {
        $playlists = PlaylistFactory::query(null, array('playlistId' => $playlistId));

        if (count($playlists) <= 0)
            throw new NotFoundException(__('Cannot find playlist'));

        return $playlists[0];
    }

    /**
     * Create a Playlist
     * @param string $name
     * @param int $ownerId
     * @return Playlist
     */
    public static function create($name, $ownerId)
    {
        $playlist = new Playlist();
        $playlist->name = $name;
        $playlist->ownerId = $ownerId;

        return $playlist;
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        $params = array();
        $sql = 'SELECT playlist.* ';

        if (Sanitize::int('regionId', $filterBy) != null) {
            $sql .= ' , lkregionplaylist.displayOrder ';
        }

        $sql .= '  FROM `playlist` ';

        if (Sanitize::int('regionId', $filterBy) != null) {
            $sql .= '
                INNER JOIN `lkregionplaylist`
                ON lkregionplaylist.playlistId = playlist.playlistId
                    AND lkregionplaylist.regionId = :regionId
            ';
            $params['regionId'] = Sanitize::int('regionId', $filterBy);
        }

        $sql .= ' WHERE 1 = 1 ';

        if (Sanitize::int('playlistId', $filterBy) != 0) {
            $sql .= ' AND playlistId = :playlistId ';
            $params['playlistId'] = Sanitize::int('playlistId', $filterBy);
        }

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Playlist())->hydrate($row);
        }

        return $entries;
    }
}