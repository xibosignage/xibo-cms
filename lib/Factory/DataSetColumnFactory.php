<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumnFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DataSetColumn;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class DataSetColumnFactory extends BaseFactory
{
    /**
     * Get by Id
     * @param int $dataSetColumnId
     * @return DataSetColumn
     * @throws NotFoundException
     */
    public static function getById($dataSetColumnId)
    {
        $columns = DataSetColumnFactory::query(null, ['dataSetColumnId' => $dataSetColumnId]);

        if (count($columns) <= 0)
            throw new NotFoundException();

        return $columns[0];
    }

    /**
     * Get by dataSetId
     * @param $dataSetId
     * @return array[DataSetColumn]
     */
    public static function getByDataSetId($dataSetId)
    {
        return DataSetColumnFactory::query(null, ['dataSetId' => $dataSetId]);
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = [];
        $params = [];

        if ($sortOrder == null)
            $sortOrder = ['columnOrder'];

        $select = '
            SELECT dataSetColumnId,
                dataSetId,
                heading,
                datatype.dataTypeId,
                datatype.dataType,
                datasetcolumn.dataSetColumnTypeId,
                datasetcolumntype.dataSetColumnType,
                listContent,
                columnOrder,
                formula
            ';

        $body = '
              FROM `datasetcolumn`
               INNER JOIN `datatype`
               ON datatype.DataTypeID = datasetcolumn.DataTypeID
               INNER JOIN `datasetcolumntype`
               ON datasetcolumntype.DataSetColumnTypeID = datasetcolumn.DataSetColumnTypeID
             WHERE 1 = 1 ';

        if (Sanitize::getInt('dataSetColumnId', $filterBy) !== null) {
            $body .= ' AND dataSetColumnId = :dataSetColumnId ';
            $params['dataSetColumnId'] = Sanitize::getInt('dataSetColumnId', $filterBy);
        }

        if (Sanitize::getInt('dataSetId', $filterBy) !== null) {
            $body .= ' AND DataSetID = :dataSetId ';
            $params['dataSetId'] = Sanitize::getInt('dataSetId', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new DataSetColumn())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}