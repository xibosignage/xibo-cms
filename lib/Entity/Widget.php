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

use Xibo\Exception\NotFoundException;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\WidgetMediaFactory;
use Xibo\Factory\WidgetOptionFactory;

class Widget
{
    use EntityTrait;
    public $widgetId;
    public $playlistId;
    public $ownerId;

    public $type;
    public $duration;
    public $displayOrder;

    public $widgetOptions;

    // A widget might be linked to file based media
    public $mediaIds;
    public $permissions;

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

    public function __toString()
    {
        return sprintf('Widget. %s on playlist %d in position %d. WidgetId = %d', $this->type, $this->playlistId, $this->displayOrder, $this->widgetId);
    }

    private function hash()
    {
        return md5($this->widgetId . $this->playlistId . $this->ownerId . $this->type . $this->duration . $this->displayOrder);
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->widgetId;
    }

    /**
     * Get the OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Set the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    /**
     * Get Option
     * @param string $option
     * @return WidgetOption
     * @throws NotFoundException
     */
    public function getOption($option)
    {
        foreach ($this->widgetOptions as $widgetOption) {
            /* @var WidgetOption $widgetOption */
            if ($widgetOption->option == $option)
                return $widgetOption;
        }

        throw new NotFoundException('Widget Option not found');
    }

    /**
     * Get Widget Option Value
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function getOptionValue($option, $default)
    {
        try {
            $widgetOption = $this->getOption($option);
            return (($widgetOption->value) == '') ? $default : $widgetOption->value;
        }
        catch (NotFoundException $e) {
            return $default;
        }
    }

    /**
     * Set Widget Option Value
     * @param string $option
     * @param string $type
     * @param mixed $value
     */
    public function setOptionValue($option, $type, $value)
    {
        try {
            $widgetOption = $this->getOption($option);
            $widgetOption->type = $type;
            $widgetOption->value = $value;
        }
        catch (NotFoundException $e) {
            $this->widgetOptions[] = WidgetOptionFactory::create($this->widgetId, $type, $option, $value);
        }
    }

    /**
     * Assign File Media
     * @param int $mediaId
     */
    public function assignMedia($mediaId)
    {
        if (!in_array($mediaId, $this->mediaIds))
            $this->mediaIds[] = $mediaId;
    }

    /**
     * Unassign File Media
     * @param int $mediaId
     */
    public function unassignMedia($mediaId)
    {
        unset($this->mediaIds[$mediaId]);
    }

    /**
     * Load the Widget
     */
    public function load()
    {
        // Load permissions
        $this->permissions = PermissionFactory::getByObjectId(get_class(), $this->widgetId);

        // Load the widget options
        $this->widgetOptions = WidgetOptionFactory::getByWidgetId($this->widgetId);

        // Load any media assignments for this widget
        $this->mediaIds = WidgetMediaFactory::getByWidgetId($this->widgetId);

        $this->hash = $this->hash();
    }

    /**
     * Save the widget
     */
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

        // TODO: Notify the Layout?
    }

    public function delete()
    {
        // We must ensure everything is loaded before we delete
        if ($this->hash == null)
            $this->load();

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Delete all Options
        foreach ($this->widgetOptions as $widgetOption) {
            /* @var \Xibo\Entity\WidgetOption $widgetOption */

            // Assert the widgetId
            $widgetOption->widgetId = $this->widgetId;
            $widgetOption->delete();
        }

        // Unlink Media
        $this->unlinkMedia();

        // Delete this
        \Xibo\Storage\PDOConnect::update('DELETE FROM `widget` WHERE widgetId = :widgetId', array('widgetId' => $this->widgetId));
    }

    private function add()
    {
        \Xibo\Helper\Log::debug('Adding Widget ' . $this->type . ' to PlaylistId ' . $this->playlistId);

        $sql = 'INSERT INTO `widget` (`playlistId`, `ownerId`, `type`, `duration`, `displayOrder`) VALUES (:playlistId, :ownerId, :type, :duration, :displayOrder)';
        $this->widgetId = \Xibo\Storage\PDOConnect::insert($sql, array(
            'playlistId' => $this->playlistId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'duration' => $this->duration,
            'displayOrder' => $this->displayOrder
        ));
    }

    private function update()
    {
        \Xibo\Helper\Log::debug('Saving Widget ' . $this->type . ' on PlaylistId ' . $this->playlistId . ' WidgetId: ' . $this->widgetId);

        $sql = 'UPDATE `widget` SET `playlistId` = :playlistId, `ownerId` = :ownerId, `type` = :type, `duration` = :duration, `displayOrder` = :displayOrder WHERE `widgetId` = :widgetId';
        \Xibo\Storage\PDOConnect::update($sql, array(
            'playlistId' => $this->playlistId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'duration' => $this->duration,
            'widgetId' => $this->widgetId,
            'displayOrder' => $this->displayOrder
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

            \Xibo\Storage\PDOConnect::insert($sql, array(
                'widgetId' => $this->widgetId,
                'mediaId' => $mediaId,
                'mediaId2' => $mediaId
            ));
        }
    }

    /**
     * Unlink Media
     */
    private function unlinkMedia()
    {
        foreach ($this->mediaIds as $mediaId) {
            \Xibo\Storage\PDOConnect::update('DELETE FROM `lkwidgetmedia` WHERE widgetId = :widgetId AND mediaId = :mediaId', array(
                'widgetId' => $this->widgetId,
                'mediaId' => $mediaId
            ));
        }
    }
}