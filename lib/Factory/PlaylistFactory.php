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

class PlaylistFactory extends BaseFactory
{
    /**
     * Load Playlists by
     * @param $regionId
     * @return array[Playlist]
     * @throws NotFoundException
     */
    public static function getByRegionId($regionId)
    {
        return PlaylistFactory::query(null, array('disableUserCheck' => 1, 'regionId' => $regionId));
    }

    /**
     * Get by Id
     * @param int $playlistId
     * @return Playlist
     * @throws NotFoundException
     */
    public static function getById($playlistId)
    {
        $playlists = PlaylistFactory::query(null, array('disableUserCheck' => 1, 'playlistId' => $playlistId));

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
        $select = 'SELECT playlist.* ';

        if (Sanitize::getInt('regionId', $filterBy) !== null) {
            $select .= ' , lkregionplaylist.displayOrder ';
        }

        $body = '  FROM `playlist` ';

        if (Sanitize::getInt('regionId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkregionplaylist`
                ON lkregionplaylist.playlistId = playlist.playlistId
                    AND lkregionplaylist.regionId = :regionId
            ';
            $params['regionId'] = Sanitize::getInt('regionId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';

        if (Sanitize::getInt('playlistId', $filterBy) != 0) {
            $body .= ' AND playlistId = :playlistId ';
            $params['playlistId'] = Sanitize::getInt('playlistId', $filterBy);
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
            $entries[] = (new Playlist())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}