<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (WidgetOptionFactory.php) is part of Xibo.
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


use Xibo\Entity\WidgetOption;

class WidgetOptionFactory
{
    /**
     * Load by Widget Id
     * @param int $widgetId
     * @return array[WidgetOption]
     */
    public static function getByWidgetId($widgetId)
    {
        return WidgetOptionFactory::query(null, array('widgetId' => $widgetId));
    }

    /**
     * Query Widget options
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[WidgetOption]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        $sql = 'SELECT * FROM `widgetoption` WHERE widgetId = :widgetId';

        foreach (\PDOConnect::select($sql, array('widgetId' => \Kit::GetParam('widgetId', $filterBy, _INT))) as $row) {
            $widget = new WidgetOption();
            $widget->widgetId = \Kit::ValidateParam($row['widgetId'], _INT);
            $widget->type = \Kit::ValidateParam($row['type'], _WORD);
            $widget->option = \Kit::ValidateParam($row['option'], _STRING);
            $widget->value = \Kit::ValidateParam($row['value'], _HTMLSTRING);

            $entries[] = $widget;
        }

        return $entries;
    }
}