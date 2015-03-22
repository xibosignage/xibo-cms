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
        $playlists = PlaylistFactory::query(null, array('regionId' => $regionId));

        if (count($playlists) <= 0)
            throw new NotFoundException(__('Cannot find playlist'));

        return $playlists;
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
        $sql = 'SELECT playlist.* FROM `playlist` ';

        if (\Xibo\Helper\Sanitize::int('regionId', $filterBy) != 0) {
            $sql .= 'INNER JOIN `lkregionplaylist` ON lkregionplaylist.playlistId = playlist.playlistId AND lkregionplaylist.regionId = :regionId ';
            $params['regionId'] = \Xibo\Helper\Sanitize::int('regionId', $filterBy);
        }

        if (\Xibo\Helper\Sanitize::int('playlistId', $filterBy) != 0) {
            $sql .= ' WHERE playlistId = :playlistId ';
            $params['playlistId'] = \Xibo\Helper\Sanitize::int('playlistId', $filterBy);
        }

        \Xibo\Helper\Log::sql($sql, $params);

        foreach (\PDOConnect::select($sql, $params) as $row) {
            $playlist = new Playlist();
            $playlist->name = \Xibo\Helper\Sanitize::string($row['name']);
            $playlist->ownerId = \Xibo\Helper\Sanitize::int($row['ownerId']);
            $playlist->playlistId = \Xibo\Helper\Sanitize::int($row['playlistId']);

            $entries[] = $playlist;
        }

        return $entries;
    }
}