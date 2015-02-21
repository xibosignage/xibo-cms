<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Widget.php) is part of Xibo.
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

use Xibo\Factory\WidgetMediaFactory;
use Xibo\Factory\WidgetOptionFactory;

class Widget
{
    private $hash;
    public $widgetId;
    public $playlistId;
    public $ownerId;

    public $type;
    public $duration;

    public $widgetOptions;

    // A widget might be linked to file based media
    public $mediaIds;

    public function __construct()
    {
        $this->hash = null;
        $this->widgetOptions = array();
        $this->mediaIds = array();
    }

    public function __clone()
    {
        $this->hash = null;
        $this->widgetId = null;
        $this->widgetOptions = array_map(function ($object) { return clone $object; }, $this->widgetOptions);

        // No need to clone the media
    }

    private function hash()
    {
        return md5($this->widgetId . $this->playlistId . $this->ownerId . $this->type . $this->duration);
    }

    /**
     * Set the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    public function load()
    {
        // Load the widget options
        $this->widgetOptions = WidgetOptionFactory::getByWidgetId($this->widgetId);

        // Load any media assignments for this widget
        $this->mediaIds = WidgetMediaFactory::getByWidgetId($this->widgetId);

        $this->hash = $this->hash();
    }

    public function save()
    {
        if ($this->widgetId == null || $this->widgetId == 0)
            $this->add();
        else if ($this->hash != $this->hash())
            $this->update();

        foreach ($this->widgetOptions as $widgetOption) {
            /* @var \Xibo\Entity\WidgetOption $widgetOption */

            // Assert the widgetId
            $widgetOption->widgetId = $this->widgetId;
            $widgetOption->save();
        }

        // Manage the assigned media
        $this->linkMedia();
    }

    public function delete()
    {

    }

    private function add()
    {
        \Debug::Audit('Adding Widget ' . $this->type . ' to PlaylistId ' . $this->playlistId);

        $sql = 'INSERT INTO `widget` (`playlistId`, `ownerId`, `type`, `duration`) VALUES (:playlistId, :ownerId, :type, :duration)';
        $this->widgetId = \PDOConnect::insert($sql, array(
            'playlistId' => $this->playlistId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'duration' => $this->duration
        ));
    }

    private function update()
    {
        \Debug::Audit('Saving Widget ' . $this->type . ' on PlaylistId ' . $this->playlistId . ' WidgetId: ' . $this->widgetId);

        $sql = 'UPDATE `widget` SET `playlistId` = :playlistId, `ownerId` = :ownerId, `type` = :type, `duration` = :duration WHERE `widgetId` = :widgetId';
        \PDOConnect::update($sql, array(
            'playlistId' => $this->playlistId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'duration' => $this->duration,
            'widgetId' => $this->widgetId
        ));
    }

    /**
     * Link Media
     */
    private function linkMedia()
    {
        // TODO: Make this more efficient by storing the prepared SQL statement
        $sql = 'INSERT INTO `lkwidgetmedia` (widgetId, mediaId) VALUES (:widgetId, :mediaId) ON DUPLICATE KEY UPDATE mediaId = :mediaId2';

        foreach ($this->mediaIds as $mediaId) {

            //\Debug::Audit('Linking MediaId ' . $mediaId . ' to Widget ' . $this->widgetId);

            \PDOConnect::insert($sql, array(
                'widgetId' => $this->widgetId,
                'mediaId' => $mediaId,
                'mediaId2' => $mediaId
            ));
        }
    }
}