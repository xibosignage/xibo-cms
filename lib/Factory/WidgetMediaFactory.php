<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
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

use Xibo\Support\Exception\NotFoundException;

class WidgetMediaFactory extends BaseFactory
{
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
     * @param int $mediaId
     * @return int
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getDurationForMediaId(int $mediaId): int
    {
        $results = $this->getStore()->select('SELECT `duration` FROM `media` WHERE `mediaId` = :mediaId', [
            'mediaId' => $mediaId
        ]);

        if (count($results) <= 0) {
            throw new NotFoundException();
        }

        return intval($results[0]['duration'] ?? 0);
    }

    /**
     * @return string
     */
    public function getLibraryTempPath(): string
    {
        return $this->getConfig()->getSetting('LIBRARY_LOCATION') . '/temp';
    }

    /**
     * @param int $mediaId
     * @return string
     * @throws NotFoundException
     */
    public function getPathForMediaId(int $mediaId): string
    {
        $results = $this->getStore()->select('SELECT `storedAs` FROM `media` WHERE `mediaId` = :mediaId', [
            'mediaId' => $mediaId
        ]);

        if (count($results) <= 0) {
            throw new NotFoundException();
        }

        return $this->getConfig()->getSetting('LIBRARY_LOCATION') . $results[0]['storedAs'];
    }

    /**
     * Query Media Linked to Widgets
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[int]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);

        if ($sanitizedFilter->getInt('moduleOnly') === 1) {
            $sql = '
                SELECT lkwidgetmedia.mediaId 
                  FROM `lkwidgetmedia` 
                    INNER JOIN `media` 
                    ON `media`.mediaId = `lkwidgetmedia`.mediaId 
                   WHERE widgetId = :widgetId 
                    AND `lkwidgetmedia`.mediaId <> 0 
                    AND `media`.type = \'module\'
                ';
        } else {
            $sql = 'SELECT mediaId FROM `lkwidgetmedia` WHERE widgetId = :widgetId AND mediaId <> 0 ';
        }

        return array_map(function($element) { return $element['mediaId']; }, $this->getStore()->select($sql, array('widgetId' => $sanitizedFilter->getInt('widgetId'))));
    }
}
