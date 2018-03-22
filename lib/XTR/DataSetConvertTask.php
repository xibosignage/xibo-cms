<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2018 Spring Signage Ltd
 * (DataSetConvertTask.php)
 */


namespace Xibo\XTR;
use Xibo\Entity\DataSet;
use Xibo\Exception\XiboException;
use Xibo\Factory\DataSetFactory;

/**
 * Class DataSetConvertTask
 * @package Xibo\XTR
 */
class DataSetConvertTask implements TaskInterface
{
    use TaskTrait;

    /** @var DataSetFactory */
    private $dataSetFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->dataSetFactory = $container->get('dataSetFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        // Protect against us having run before
        if ($this->store->exists('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :name', [
            'schema' => $_SERVER['MYSQL_DATABASE'],
            'name' => 'datasetdata'
        ])) {

            // Get all DataSets
            foreach ($this->dataSetFactory->query() as $dataSet) {
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

        // Disable the task
        $this->getTask()->isActive = 0;

        $this->appendRunMessage('Conversion Completed');
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