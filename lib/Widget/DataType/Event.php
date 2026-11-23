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

namespace Xibo\Widget\DataType;

use Xibo\Widget\Definition\DataType;

/**
 * Event data type
 */
class Event implements \JsonSerializable, DataTypeInterface
{
    public static $NAME = 'event';
    public $summary;
    public $description;
    public $location;

    /** @var \Carbon\Carbon */
    public $startDate;

    /** @var \Carbon\Carbon */
    public $endDate;

    /** @var bool */
    public $isAllDay = false;

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'summary' => $this->summary,
            'description' => $this->description,
            'location' => $this->location,
            'startDate' => $this->startDate->format('c'),
            'endDate' => $this->endDate->format('c'),
            'isAllDay' => $this->isAllDay,
        ];
    }

    public function getDefinition(): DataType
    {
        $dataType = new DataType();
        $dataType->id = self::$NAME;
        $dataType->name = __('Event');
        $dataType
            ->addField('summary', __('Summary'), 'text')
            ->addField('description', __('Description'), 'text')
            ->addField('location', __('Location'), 'text')
            ->addField('startDate', __('Start Date'), 'datetime')
            ->addField('endDate', __('End Date'), 'datetime')
            ->addField('isAllDay', __('All Day Event'), 'boolean');
        return $dataType;
    }
}
