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

/**
 * Class DataSetColumnTypeFactory
 * @package Xibo\Factory
 */
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
     * @return DataSetColumnType
     */
    public function createEmpty()
    {
        return new DataSetColumnType($this->getStore(), $this->getLog());
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
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}