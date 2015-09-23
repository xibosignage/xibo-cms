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
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\WidgetMediaFactory;
use Xibo\Factory\WidgetOptionFactory;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;
use Xibo\Widget\ModuleWidget;

/**
 * Class Widget
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Widget implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Widget ID")
     * @var int
     */
    public $widgetId;

    /**
     * @SWG\Property(description="The ID of the Playlist this Widget belongs to")
     * @var int
     */
    public $playlistId;

    /**
     * @SWG\Property(description="The ID of the User that owns this Widget")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The Module Type Code")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="The duration in seconds this widget should be shown")
     * @var int
     */
    public $duration;

    /**
     * @SWG\Property(description="The display order of this widget")
     * @var int
     */
    public $displayOrder;

    /**
     * @SWG\Property(description="An array of Widget Options")
     * @var WidgetOption[]
     */
    public $widgetOptions = [];

    /**
     * @SWG\Property(description="An array of MediaIds this widget is linked to")
     * @var int[]
     */
    public $mediaIds = [];

    /**
     * @SWG\Property(description="An array of permissions for this widget")
     * @var Permission[]
     */
    public $permissions = [];

    /**
     * @SWG\Property(description="The Module Object for this Widget")
     * @var ModuleWidget $module
     */
    public $module;

    /**
     * Hash Key of Media Assignments
     * @var string
     */
    private $mediaHash = null;

    public function __construct()
    {
        $this->excludeProperty('module');
    }

    public function __clone()
    {
        $this->hash = null;
        $this->widgetId = null;
        $this->widgetOptions = array_map(function ($object) { return clone $object; }, $this->widgetOptions);
        $this->permissions = [];

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

    private function mediaHash()
    {
        return md5(implode(',', $this->mediaIds));
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
            $widgetOption = (($widgetOption->value) === null) ? $default : $widgetOption->value;

            if (is_integer($default))
                $widgetOption = intval($widgetOption);

            return $widgetOption;
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
        $this->load();

        if (!in_array($mediaId, $this->mediaIds))
            $this->mediaIds[] = $mediaId;
    }

    /**
     * Unassign File Media
     * @param int $mediaId
     */
    public function unassignMedia($mediaId)
    {
        $this->load();

        $this->mediaIds = array_diff($this->mediaIds, [$mediaId]);
    }

    /**
     * Count media
     * @return int count of media
     */
    public function countMedia()
    {
        $this->load();
        return count($this->mediaIds);
    }

    /**
     * Clear Media
     */
    public function clearMedia()
    {
        $this->load();
        $this->mediaIds = [];
    }

    /**
     * Have the media assignments changed.
     */
    public function hasMediaChanged()
    {
        return ($this->mediaHash != $this->mediaHash());
    }

    /**
     * Load the Widget
     */
    public function load()
    {
        if ($this->loaded || $this->widgetId == null || $this->widgetId == 0)
            return;

        // Load permissions
        $this->permissions = PermissionFactory::getByObjectId(get_class(), $this->widgetId);

        // Load the widget options
        $this->widgetOptions = WidgetOptionFactory::getByWidgetId($this->widgetId);

        // Load any media assignments for this widget
        $this->mediaIds = WidgetMediaFactory::getByWidgetId($this->widgetId);

        $this->hash = $this->hash();
        $this->mediaHash = $this->mediaHash();
        $this->loaded = true;
    }

    /**
     * Save the widget
     * @param array $options
     */
    public function save($options = [])
    {
        // Default options
        $options = array_merge([
            'saveWidgetOptions' => true,
            'notify' => true
        ], $options);

        // Add/Edit
        if ($this->widgetId == null || $this->widgetId == 0)
            $this->add();
        else if ($this->hash != $this->hash())
            $this->update();

        // Save the widget options
        if ($options['saveWidgetOptions']) {
            foreach ($this->widgetOptions as $widgetOption) {
                /* @var \Xibo\Entity\WidgetOption $widgetOption */

                // Assert the widgetId
                $widgetOption->widgetId = $this->widgetId;
                $widgetOption->save();
            }
        }

        // Manage the assigned media
        $this->linkMedia();
        $this->unlinkMedia();

        if ($options['notify']) {
            // Notify the Layout
            $playlist = PlaylistFactory::getById($this->playlistId);
            $playlist->notifyLayouts();
        }
    }

    public function delete($options = [])
    {
        $options = array_merge([
            'notify' => true
        ], $options);

        // We must ensure everything is loaded before we delete
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
        $this->mediaIds = [];
        $this->unlinkMedia();

        // Delete this
        PDOConnect::update('DELETE FROM `widget` WHERE widgetId = :widgetId', array('widgetId' => $this->widgetId));

        if ($options['notify']) {

            Log::debug('Notifying upstream playlist');

            // Notify the Layout
            $playlist = PlaylistFactory::getById($this->playlistId);
            $playlist->notifyLayouts();
        }

        Log::debug('Delete Widget Complete');
    }

    private function add()
    {
        Log::debug('Adding Widget ' . $this->type . ' to PlaylistId ' . $this->playlistId);

        $sql = 'INSERT INTO `widget` (`playlistId`, `ownerId`, `type`, `duration`, `displayOrder`) VALUES (:playlistId, :ownerId, :type, :duration, :displayOrder)';
        $this->widgetId = PDOConnect::insert($sql, array(
            'playlistId' => $this->playlistId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'duration' => $this->duration,
            'displayOrder' => $this->displayOrder
        ));
    }

    private function update()
    {
        Log::debug('Saving Widget ' . $this->type . ' on PlaylistId ' . $this->playlistId . ' WidgetId: ' . $this->widgetId);

        $sql = 'UPDATE `widget` SET `playlistId` = :playlistId, `ownerId` = :ownerId, `type` = :type, `duration` = :duration, `displayOrder` = :displayOrder WHERE `widgetId` = :widgetId';
        PDOConnect::update($sql, array(
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

            Log::debug('Inserting %d', $mediaId);

            PDOConnect::insert($sql, array(
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
        // Unlink any media that isn't in the collection
        if (count($this->mediaIds) <= 0)
            $this->mediaIds = [0];

        $params = ['widgetId' => $this->widgetId];

        $sql = 'DELETE FROM `lkwidgetmedia` WHERE widgetId = :widgetId AND mediaId NOT IN (0';

        $i = 0;
        foreach ($this->mediaIds as $mediaId) {
            $i++;
            $sql .= ',:mediaId' . $i;
            $params['mediaId' . $i] = $mediaId;
        }

        $sql .= ')';

        Log::sql($sql, $params);

        PDOConnect::update($sql, $params);
    }
}