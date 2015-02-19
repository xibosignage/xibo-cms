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


use Xibo\Factory\WidgetFactory;
use Xibo\Factory\WidgetOptionFactory;

class Widget
{
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
        $this->widgetOptions = array();
        $this->mediaIds = array();
    }

    public function __clone()
    {
        $this->widgetId = null;
        $this->widgetOptions = array_map(function ($object) { return clone $object; }, $this->widgetOptions);

        // No need to clone the media
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
        $this->widgetOptions = WidgetOptionFactory::loadByWidgetId($this->widgetId);

        // TODO: Load any media assignments for this widget

    }

    public function save()
    {
        if ($this->widgetId == null || $this->widgetId == 0)
            $this->add();
        else
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
        $sql = 'UPDATE `widget` SET `playlistId` = :playlistId, `ownerId` = :ownerId, `type` = :type, `duration` = :duration WHERE `widgetId` = :widgetId';
        \PDOConnect::update($sql, array(
            'playlistId' => $this->playlistId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'duration' => $this->duration,
            'widgetId' => $this->widgetId
        ));
    }

    private function linkMedia()
    {
        // TODO: Implement linkMedia (lkwidgetmedia table)
    }
}