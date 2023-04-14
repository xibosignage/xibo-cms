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

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'summary' => $this->summary,
            'description' => $this->description,
            'location' => $this->location,
            'startDate' => $this->startDate->format('c'),
            'endDate' => $this->endDate->format('c'),
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
            ->addField('endDate', __('End Date'), 'datetime');
        return $dataType;
    }
}
