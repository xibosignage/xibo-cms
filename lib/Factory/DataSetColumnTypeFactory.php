<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumnTypeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DataSetColumnType;
use Xibo\Storage\PDOConnect;

class DataSetColumnTypeFactory extends BaseFactory
{
    /**
     * @param null $sortOrder
     * @param null $filterBy
     * @return array[DataSetColumnType]
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = [];

        foreach (PDOConnect::select('SELECT dataSetColumnTypeId, dataSetColumnType FROM `datasetcolumntype` ', []) as $row) {
            $entries[] = (new DataSetColumnType())->hydrate($row)->setApp($this->getApp())->setApp($this->getApp());
        }

        return $entries;
    }
}