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


use Xibo\Entity\WidgetAudio;

/**
 * Class WidgetAudioFactory
 * @package Xibo\Factory
 */
class WidgetAudioFactory extends BaseFactory
{
    /**
     * Create Empty
     * @return WidgetAudio
     */
    public function createEmpty()
    {
        return new WidgetAudio($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Media Linked to Widgets by WidgetId
     * @param int $widgetId
     * @return array[int]
     */
    public function getByWidgetId($widgetId)
    {
        return $this->query(null, array('widgetId' => $widgetId));
    }

    /**
     * Query Media Linked to Widgets
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[int]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $sql = 'SELECT `mediaId`, `widgetId`, `volume`, `loop` FROM `lkwidgetaudio` WHERE widgetId = :widgetId AND mediaId <> 0 ';

        foreach ($this->getStore()->select($sql, ['widgetId' => $sanitizedFilter->getInt('widgetId')]) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['duration']]);
        }

        return $entries;
    }
}