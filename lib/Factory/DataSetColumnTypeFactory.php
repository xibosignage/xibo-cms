<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumnTypeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DataSetColumnType;
use Xibo\Exception\NotFoundException;
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
        $sql = 'SELECT dataSetColumnTypeId, dataSetColumnType FROM `datasetcolumntype` WHERE 1 = 1 ';

        if ($this->getSanitizer()->getInt('dataSetColumnTypeId') !== null) {
            $sql .= ' AND `datasetcolumntype`.dataSetColumnTypeId = :dataSetColumnTypeId ';
            $params['dataSetColumnTypeId'] = $this->getSanitizer()->getInt('dataSetColumnTypeId');
        }

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}