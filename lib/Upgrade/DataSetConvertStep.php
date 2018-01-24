<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetConvertStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Entity\DataSet;
use Xibo\Exception\XiboException;
use Xibo\Factory\DataSetFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DataSetConvertStep
 * @package Xibo\Upgrade
 */
class DataSetConvertStep implements Step
{
    /** @var  StorageServiceInterface */
    private $store;

    /** @var  LogServiceInterface */
    private $log;

    /** @var  ConfigServiceInterface */
    private $config;

    /**
     * DataSetConvertStep constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     */
    public function __construct($store, $log, $config)
    {
        $this->store = $store;
        $this->log = $log;
        $this->config = $config;
    }

    /**
     * @param \Slim\Helper\Set $container
     * @throws \Xibo\Exception\XiboException
     */
    public function doStep($container)
    {
        /** @var DataSetFactory $dataSetFactory */
        $dataSetFactory = $container->get('dataSetFactory');

        // Get all DataSets
        foreach ($dataSetFactory->query() as $dataSet) {
            /* @var \Xibo\Entity\DataSet $dataSet */

            // Rebuild the data table
            $dataSet->rebuild();

            // Load the existing data from datasetdata
            foreach (self::getExistingData($dataSet) as $row) {
                $dataSet->addRow($row);
            }
        }

        // Drop data set data
        $this->store->update('DROP TABLE `datasetdata`;', []);
    }

    /**
     * Data Set Results
     * @param DataSet $dataSet
     * @return array
     * @throws XiboException
     */
    public function getExistingData($dataSet)
    {
        $dbh = $this->store->getConnection();
        $params = array('dataSetId' => $dataSet->dataSetId);

        $selectSQL = '';
        $outerSelect = '';

        foreach ($dataSet->getColumn() as $col) {
            /* @var \Xibo\Entity\DataSetColumn $col */
            if ($col->dataSetColumnTypeId != 1)
                continue;

            $selectSQL .= sprintf("MAX(CASE WHEN DataSetColumnID = %d THEN `Value` ELSE null END) AS '%s', ", $col->dataSetColumnId, $col->heading);
            $outerSelect .= sprintf(' `%s`,', $col->heading);
        }

        $outerSelect = rtrim($outerSelect, ',');

        // We are ready to build the select and from part of the SQL
        $SQL  = "SELECT $outerSelect ";
        $SQL .= "  FROM ( ";
        $SQL .= "   SELECT $outerSelect ,";
        $SQL .= "           RowNumber ";
        $SQL .= "     FROM ( ";
        $SQL .= "      SELECT $selectSQL ";
        $SQL .= "          RowNumber ";
        $SQL .= "        FROM (";
        $SQL .= "          SELECT datasetcolumn.DataSetColumnID, datasetdata.RowNumber, datasetdata.`Value` ";
        $SQL .= "            FROM datasetdata ";
        $SQL .= "              INNER JOIN datasetcolumn ";
        $SQL .= "              ON datasetcolumn.DataSetColumnID = datasetdata.DataSetColumnID ";
        $SQL .= "            WHERE datasetcolumn.DataSetID = :dataSetId ";
        $SQL .= "          ) datasetdatainner ";
        $SQL .= "      GROUP BY RowNumber ";
        $SQL .= "    ) datasetdata ";
        $SQL .= ' ) finalselect ';
        $SQL .= " ORDER BY RowNumber ";

        $sth = $dbh->prepare($SQL);
        $sth->execute($params);

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }
}