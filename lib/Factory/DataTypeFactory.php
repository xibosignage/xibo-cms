<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumnTypeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DataType;
use Xibo\Exception\NotFoundException;
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
     * Get By Id
     * @param int $id
     * @return DataType
     * @throws NotFoundException
     */
    public function getById($id)
    {
        $results = $this->query(null, ['dataTypeId' => $id]);

        if (count($results) <= 0)
            throw new NotFoundException();

        return $results[0];
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return array[DataType]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];

        $params = [];
        $sql = 'SELECT dataTypeId, dataType FROM `datatype` WHERE 1 = 1 ';

        if ($this->getSanitizer()->getInt('dataTypeId') !== null) {
            $sql .= ' AND `datatype`.dataTypeId = :dataTypeId ';
            $params['dataTypeId'] = $this->getSanitizer()->getInt('dataTypeId');
        }

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}