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

use Xibo\Widget\Definition\DataType;

/**
 * Event raised when a data set widget requests its datatype.
 */
class DataSetDataTypeRequestEvent extends Event
{
    public static $NAME = 'dataset.datatype.request.event';

    /** @var int */
    private $dataSetId;

    /** @var \Xibo\Widget\Definition\DataType */
    private $dataType;

    public function __construct(int $dataSetId)
    {
        $this->dataSetId = $dataSetId;
    }

    /**
     * The data provider should be updated with data for its widget.
     * @return int
     */
    public function getDataSetId(): int
    {
        return $this->dataSetId;
    }

    /**
     * @param \Xibo\Widget\Definition\DataType $dataType
     * @return $this
     */
    public function setDataType(DataType $dataType): DataSetDataTypeRequestEvent
    {
        $this->dataType = $dataType;
        return $this;
    }

    /**
     * Return the data type
     * @return \Xibo\Widget\Definition\DataType
     */
    public function getDataType(): ?DataType
    {
        return $this->dataType;
    }
}
