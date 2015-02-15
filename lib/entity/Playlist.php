<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Playlist.php) is part of Xibo.
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


namespace Xibo\Entity;


class Playlist
{
    public $playlistId;
    public $ownerId;

    public $playlist;

    public $tags;
    public $widgets;

    public function __clone()
    {
        $this->playlistId = null;

        $this->widgets = array_map(function ($object) { return clone $object; }, $this->widgets);
    }

    public function setOwner($ownerId)
    {

    }

    public function save()
    {

        if ($this->widgets != null) {
            foreach ($this->widgets as $widget) {
                /* @var Widget $widget */
                $widget->save();
            }
        }
    }

    private function add()
    {
        $sql = 'INSERT INTO `playlist` (`playlist`, `ownerId`) VALUES (:playlist, :ownerId)';
        $this->playlistId = \PDOConnect::insert($sql, array(
            'playlist' => $this->playlist,
            'ownerId' => $this->ownerId
        ));
    }

    private function update()
    {
        $sql = 'UPDATE `playlist` SET `playlist` = :playlist WHERE `playlistId` = :playlistId';
        \PDOConnect::execute($sql, array(
            'playlistId' => $this->playlistId,
            'playlist' => $this->playlist
        ));
    }
}