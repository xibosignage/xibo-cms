<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumnTypeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DataSetColumnType;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

class DataSetColumnTypeFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * @param null $sortOrder
     * @param null $filterBy
     * @return array[DataSetColumnType]
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = [];

        foreach ($this->getStore()->select('SELECT dataSetColumnTypeId, dataSetColumnType FROM `datasetcolumntype` ', []) as $row) {
            $entries[] = (new DataSetColumnType())->hydrate($row)->setContainer($this->getContainer());
        }

        return $entries;
    }
}