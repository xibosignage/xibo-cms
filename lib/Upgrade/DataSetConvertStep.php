<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetConvertStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Entity\DataSet;
use Xibo\Factory\DataSetFactory;
use Xibo\Storage\PDOConnect;

class DataSetConvertStep implements Step
{
    public static function doStep()
    {
        // Get all DataSets
        foreach ((new DataSetFactory($this->getApp()))->query() as $dataSet) {
            /* @var \Xibo\Entity\DataSet $dataSet */
            $dataSet->load();

            // Rebuild the data table
            $dataSet->rebuild();

            // Load the existing data from datasetdata
            foreach (self::getExistingData($dataSet) as $row) {
                $dataSet->addRow($row);
            }
        }

        // Drop data set data
        PDOConnect::update('DROP TABLE `datasetdata`;', []);
    }

    /**
     * Data Set Results
     * @param DataSet $dataSet
     * @return array
     */
    public static function getExistingData($dataSet)
    {
        $dbh = PDOConnect::init();
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