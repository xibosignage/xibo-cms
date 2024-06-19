<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use Carbon\Carbon;
use Xibo\Entity\WidgetData;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Factory for returning Widget Data
 */
class WidgetDataFactory extends BaseFactory
{
    public function create(
        int $widgetId,
        array $data,
        int $displayOrder
    ): WidgetData {
        $widgetData = $this->createEmpty();
        $widgetData->widgetId = $widgetId;
        $widgetData->data = $data;
        $widgetData->displayOrder = $displayOrder;
        return $widgetData;
    }

    private function createEmpty(): WidgetData
    {
        return new WidgetData($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Get Widget Data by its ID
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function getById(int $id): WidgetData
    {
        if (empty($id)) {
            throw new InvalidArgumentException(__('Missing ID'), 'id');
        }

        $sql = 'SELECT * FROM `widgetdata` WHERE `id` = :id';
        foreach ($this->getStore()->select($sql, ['id' => $id]) as $row) {
            return $this->hydrate($row);
        };

        throw new NotFoundException();
    }

    /**
     * Get Widget Data for a Widget
     * @param int $widgetId
     * @return WidgetData[]
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function getByWidgetId(int $widgetId): array
    {
        if (empty($widgetId)) {
            throw new InvalidArgumentException(__('Missing Widget ID'), 'widgetId');
        }

        $entries = [];
        $sql = 'SELECT * FROM `widgetdata` WHERE `widgetId` = :widgetId';
        foreach ($this->getStore()->select($sql, ['widgetId' => $widgetId]) as $row) {
            $entries[] = $this->hydrate($row);
        };

        return $entries;
    }

    /**
     * Get modified date for Widget Data for a Widget
     * @param int $widgetId
     * @return ?\Carbon\Carbon
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function getModifiedDtForWidget(int $widgetId): ?Carbon
    {
        if (empty($widgetId)) {
            throw new InvalidArgumentException(__('Missing Widget ID'), 'widgetId');
        }

        $entries = [];
        $sql = 'SELECT MAX(`modifiedDt`) AS modifiedDt FROM `widgetdata` WHERE `widgetId` = :widgetId';
        $result = $this->getStore()->select($sql, ['widgetId' => $widgetId]);
        return Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $result[0]['modifiedDt']);
    }

    /**
     * Helper function for
     * @param array $row
     * @return \Xibo\Entity\WidgetData
     */
    private function hydrate(array $row): WidgetData
    {
        if (!empty($row['data'])) {
            $row['data'] = json_decode($row['data'], true);
        } else {
            $row['data'] = [];
        }
        return $this->createEmpty()->hydrate($row);
    }
}
