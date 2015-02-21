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

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        $sql = 'SELECT * FROM `widget` WHERE playlistId = :playlistId';

        foreach (\PDOConnect::select($sql, array('playlistId' => \Kit::GetParam('playlistId', $filterBy, _INT))) as $row) {
            $widget = new Widget();
            $widget->widgetId = \Kit::ValidateParam($row['widgetId'], _INT);
            $widget->playlistId = \Kit::ValidateParam($row['playlistId'], _INT);
            $widget->ownerId = \Kit::ValidateParam($row['ownerId'], _INT);
            $widget->type = \Kit::ValidateParam($row['type'], _WORD);
            $widget->duration = \Kit::ValidateParam($row['duration'], _INT);

            $entries[] = $widget;
        }

        return $entries;
    }
}