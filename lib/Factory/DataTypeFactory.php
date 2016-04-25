<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumnTypeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DataType;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DataTypeFactory
 * @package Xibo\Factory
 */
class DataTypeFactory extends BaseFactory
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
     * @return DataType
     */
    public function createEmpty()
    {
        return new DataType($this->getStore(), $this->getLog());
    }

    /**
     * @param null $sortOrder
     * @param null $filterBy
     * @return array[DataType]
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = [];

        foreach ($this->getStore()->select('SELECT dataTypeId, dataType FROM `datatype` ', []) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}