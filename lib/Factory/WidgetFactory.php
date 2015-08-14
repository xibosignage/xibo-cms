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

class WidgetFactory extends BaseFactory
{
    /**
     * Load widgets by Playlist ID
     * @param int $playlistId
     * @return array[Widget]
     */
    public static function getByPlaylistId($playlistId)
    {
        return WidgetFactory::query(null, array('disableUserCheck' => 1, 'playlistId' => $playlistId));
    }

    /**
     * Load widgets by MediaId
     * @param int $mediaId
     * @return array[Widget]
     */
    public static function getByMediaId($mediaId)
    {
        return WidgetFactory::query(null, array('disableUserCheck' => 1, 'mediaId' => $mediaId));
    }

    /**
     * Get widget by widget id
     * @param $widgetId
     * @return Widget
     */
    public static function getById($widgetId)
    {
        $widgets = WidgetFactory::query(null, array('disableUserCheck' => 1, 'widgetId' => $widgetId));
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
        $widgets = WidgetFactory::query(null, array('disableUserCheck' => 1, 'widgetId' => $widgetId));

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
     * @return Widget
     */
    public static function create($ownerId, $playlistId, $type, $duration)
    {
        $widget = new Widget();
        $widget->ownerId = $ownerId;
        $widget->playlistId = $playlistId;
        $widget->type = $type;
        $widget->duration = $duration;
        $widget->displayOrder = 1;

        return $widget;
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        if ($sortOrder == null)
            $sortOrder = array('displayOrder');

        $entries = array();

        $params = array();
        $select = '
          SELECT widget.widgetId,
              widget.playlistId,
              widget.ownerId,
              widget.type,
              widget.duration,
              widget.displayOrder
        ';

        $body = '
          FROM `widget`
        ';

        if (Sanitize::getInt('mediaId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkwidgetmedia`
                ON `lkwidgetmedia`.widgetId = widget.widgetId
                    AND `lkwidgetmedia`.mediaId = :mediaId
            ';
            $params['mediaId'] = Sanitize::getInt('mediaId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';

        // Permissions
        self::viewPermissionSql('Xibo\Entity\Widget', $body, $params, 'widget.widgetId', 'widget.ownerId', $filterBy);

        if (Sanitize::getInt('playlistId', $filterBy) !== null) {
            $body .= ' AND playlistId = :playlistId';
            $params['playlistId'] = Sanitize::getInt('playlistId', $filterBy);
        }

        if (Sanitize::getInt('widgetId', $filterBy) !== null) {
            $body .= ' AND widgetId = :widgetId';
            $params['widgetId'] = Sanitize::getInt('widgetId', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= ' ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Widget())->hydrate($row, ['intProperties' => ['duration']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}