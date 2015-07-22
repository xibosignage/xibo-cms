<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumnTypeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DataType;
use Xibo\Storage\PDOConnect;

class DataTypeFactory extends BaseFactory
{
    /**
     * @param null $sortOrder
     * @param null $filterBy
     * @return array[DataType]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = [];

        foreach (PDOConnect::select('SELECT dataTypeId, dataType FROM `datatype` ', []) as $row) {
            $entries[] = (new DataType())->hydrate($row);
        }

        return $entries;
    }
}