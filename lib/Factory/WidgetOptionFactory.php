<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */


namespace Xibo\Factory;


use Xibo\Entity\WidgetOption;

class WidgetOptionFactory extends BaseFactory
{
    /**
     * Create Empty
     * @return WidgetOption
     */
    public function createEmpty()
    {
        return new WidgetOption($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Create a Widget Option
     * @param int $widgetId
     * @param string $type
     * @param string $option
     * @param mixed $value
     * @return WidgetOption
     */
    public function create($widgetId, $type, $option, $value)
    {
        $widgetOption = $this->createEmpty();
        $widgetOption->widgetId = $widgetId;
        $widgetOption->type = $type;
        $widgetOption->option = $option;
        $widgetOption->value = $value;

        return $widgetOption;
    }

    /**
     * Load by Widget Id
     * @param int $widgetId
     * @return array[WidgetOption]
     */
    public function getByWidgetId($widgetId)
    {
        return $this->query(null, array('widgetId' => $widgetId));
    }

    /**
     * Query Widget options
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[WidgetOption]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);
        $entries = [];

        $sql = 'SELECT * FROM `widgetoption` WHERE widgetId = :widgetId';

        foreach ($this->getStore()->select($sql, [
            'widgetId' => $sanitizedFilter->getInt('widgetId')
        ]) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}