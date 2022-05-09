<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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


namespace Xibo\Factory;


use Xibo\Entity\DataSetColumnType;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DataSetColumnTypeFactory
 * @package Xibo\Factory
 */
class DataSetColumnTypeFactory extends BaseFactory
{
    /**
     * @return DataSetColumnType
     */
    public function createEmpty()
    {
        return new DataSetColumnType($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Get By Id
     * @param int $id
     * @return DataSetColumnType
     * @throws NotFoundException
     */
    public function getById($id)
    {
        $results = $this->query(null, ['dataSetColumnTypeId' => $id]);

        if (count($results) <= 0)
            throw new NotFoundException();

        return $results[0];
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return array[DataSetColumnType]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $params = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);
        
        $sql = 'SELECT dataSetColumnTypeId, dataSetColumnType FROM `datasetcolumntype` WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('dataSetColumnTypeId') !== null) {
            $sql .= ' AND `datasetcolumntype`.dataSetColumnTypeId = :dataSetColumnTypeId ';
            $params['dataSetColumnTypeId'] = $sanitizedFilter->getInt('dataSetColumnTypeId');
        }

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}