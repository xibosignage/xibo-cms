<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumnTypeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DataType;

class DataTypeFactory extends BaseFactory
{
    /**
     * @param null $sortOrder
     * @param null $filterBy
     * @return array[DataType]
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = [];

        foreach ($this->getStore()->select('SELECT dataTypeId, dataType FROM `datatype` ', []) as $row) {
            $entries[] = (new DataType())->hydrate($row)->setContainer($this->getContainer());
        }

        return $entries;
    }
}