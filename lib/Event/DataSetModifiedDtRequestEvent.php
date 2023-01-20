<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
namespace Xibo\Event;

use Carbon\Carbon;

/**
 * Event raised when a widget requests data.
 */
class DataSetModifiedDtRequestEvent extends Event
{
    public static $NAME = 'dataset.modifiedDt.request.event';

    /** @var int */
    private $dataSetId;

    /** @var Carbon */
    private $modifiedDt;

    public function __construct(int $dataSetId)
    {
        $this->dataSetId = $dataSetId;
    }

    public function getDataSetId(): int
    {
        return $this->dataSetId;
    }

    public function setModifiedDt(Carbon $modifiedDt): DataSetModifiedDtRequestEvent
    {
        $this->modifiedDt = $modifiedDt;
        return $this;
    }

    public function getModifiedDt(): ?Carbon
    {
        return $this->modifiedDt;
    }
}
