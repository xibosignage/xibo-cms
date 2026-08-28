<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

use Xibo\Entity\DisplayScreenshot;
use Xibo\Support\Exception\NotFoundException;

/**
 * @package Xibo\Factory
 */
class DisplayScreenshotFactory extends BaseFactory
{
    /** How many screenshots a display keeps. The oldest is dropped once this is exceeded. */
    public const HISTORY_LIMIT = 10;

    public function createEmpty(): DisplayScreenshot
    {
        return new DisplayScreenshot($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * A display's screenshots, newest first.
     *
     * @return DisplayScreenshot[]
     */
    public function getByDisplayId(int $displayId, ?int $limit = null, int $offset = 0): array
    {
        $sql = '
            SELECT displayScreenshotId, displayId, createdDt, storedAs, statusJson
              FROM `displayscreenshot`
             WHERE displayId = :displayId
             ORDER BY createdDt DESC, displayScreenshotId DESC
        ';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . intval($limit);
        } elseif ($offset > 0) {
            // MySQL requires a LIMIT to use OFFSET; this is its documented way to mean "no cap".
            $sql .= ' LIMIT 18446744073709551615';
        }

        if ($offset > 0) {
            $sql .= ' OFFSET ' . $offset;
        }

        $entries = [];
        foreach ($this->getStore()->select($sql, ['displayId' => $displayId]) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['createdDt']]);
        }

        return $entries;
    }

    /**
     * @throws NotFoundException
     */
    public function getById(int $displayScreenshotId): DisplayScreenshot
    {
        $rows = $this->getStore()->select('
            SELECT displayScreenshotId, displayId, createdDt, storedAs, statusJson
              FROM `displayscreenshot`
             WHERE displayScreenshotId = :displayScreenshotId
        ', ['displayScreenshotId' => $displayScreenshotId]);

        if (count($rows) <= 0) {
            throw new NotFoundException(__('Screenshot not found'));
        }

        return $this->createEmpty()->hydrate($rows[0], ['intProperties' => ['createdDt']]);
    }

    /**
     * Everything past the newest HISTORY_LIMIT for this display, so the caller can remove both the
     * rows and the files they point at.
     *
     * @return DisplayScreenshot[]
     */
    public function getExpiredByDisplayId(int $displayId): array
    {
        return $this->getByDisplayId($displayId, null, self::HISTORY_LIMIT);
    }
}
