<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (WidgetFactory.php) is part of Xibo.
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


use Xibo\Entity\Widget;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class WidgetFactory
{
    /**
     * Load widgets by Playlist ID
     * @param int $playlistId
     * @return array[Widget]
     */
    public static function getByPlaylistId($playlistId)
    {
        return WidgetFactory::query(null, array('playlistId' => $playlistId));
    }

    /**
     * Load widgets by MediaId
     * @param int $mediaId
     * @return array[Widget]
     */
    public static function getByMediaId($mediaId)
    {
        return WidgetFactory::query(null, array('mediaId' => $mediaId));
    }

    /**
     * Get widget by widget id
     * @param $widgetId
     * @return Widget
     */
    public static function getById($widgetId)
    {
        $widgets = WidgetFactory::query(null, array('widgetId' => $widgetId));
        return $widgets[0];
    }

    /**
     * Load widget by widget id
     * @param $widgetId
     * @return Widget
     * @throws NotFoundException
     */
    public static function loadByWidgetId($widgetId)
    {
        $widgets = WidgetFactory::query(null, array('widgetId' => $widgetId));

        if (count($widgets) <= 0)
            throw new NotFoundException(__('Widget not found'));

        $widget = $widgets[0];
        /* @var Widget $widget */
        $widget->load();
        return $widget;
    }

    /**
     * Create a new widget
     * @param int $ownerId
     * @param int $playlistId
     * @param string $type
     * @param int $duration
     * @param int $displayOrder
     * @return Widget
     */
    public static function create($ownerId, $playlistId, $type, $duration, $displayOrder = 1)
    {
        $widget = new Widget();
        $widget->ownerId = $ownerId;
        $widget->playlistId = $playlistId;
        $widget->type = $type;
        $widget->duration = $duration;
        $widget->displayOrder = $displayOrder;

        return $widget;
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        if ($sortOrder == null)
            $sortOrder = array('displayOrder');

        $entries = array();

        $params = array();
        $sql = '
          SELECT widget.widgetId,
              widget.playlistId,
              widget.ownerId,
              widget.type,
              widget.duration,
              widget.displayOrder
            FROM `widget`
        ';

        if (Sanitize::getInt('mediaId', $filterBy) != null) {
            $sql .= '
                INNER JOIN `lkwidgetmedia`
                ON `lkwidgetmedia`.widgetId = widget.widgetId
                    AND `lkwidgetmedia`.mediaId = :mediaId
            ';
            $params['mediaId'] = Sanitize::getInt('mediaId', $filterBy);
        }

        $sql .= ' WHERE 1 = 1 ';

        if (Sanitize::getInt('playlistId', $filterBy) != null) {
            $sql .= ' AND playlistId = :playlistId';
            $params['playlistId'] = Sanitize::getInt('playlistId', $filterBy);
        }

        if (Sanitize::getInt('widgetId', $filterBy) != null) {
            $sql .= ' AND widgetId = :widgetId';
            $params['widgetId'] = Sanitize::getInt('widgetId', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= ' ORDER BY ' . implode(',', $sortOrder);


        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Widget())->hydrate($row, ['duration']);
        }

        return $entries;
    }
}