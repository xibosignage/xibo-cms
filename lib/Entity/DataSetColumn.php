<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumn.php)
 */


namespace Xibo\Entity;


use Xibo\Storage\PDOConnect;

class DataSetColumn
{
    use EntityTrait;

    public $dataSetColumnId;
    public $dataSetId;
    public $heading;
    public $dataTypeId;
    public $dataSetColumnTypeId;
    public $listContent;
    public $columnOrder;
    public $formula;

    public function validate()
    {
        if ($this->dataSetId == 0 || $this->dataSetId == '')
            throw new \InvalidArgumentException(__('Missing dataSetId'));

        if ($this->dataTypeId == 0 || $this->dataTypeId == '')
            throw new \InvalidArgumentException(__('Missing dataTypeId'));

        if ($this->dataSetColumnTypeId == 0 || $this->dataSetColumnTypeId == '')
            throw new \InvalidArgumentException(__('Missing dataSetColumnTypeId'));

        if ($this->heading == '')
            throw new \InvalidArgumentException(__('Please provide a column heading.'));

        // Validation
        if ($this->dataSetColumnId != 0 && $this->listContent != '') {
            $list = explode(',', $this->listContent);

            // We can check this is valid by building up a NOT IN sql statement, if we get results.. we know its not good
            $select = '';

            $dbh = PDOConnect::init();

            for ($i=0; $i < count($list); $i++) {
                $list_val = $dbh->quote($list[$i]);
                $select .= $list_val . ',';
            }

            $select = rtrim($select, ',');

            // $select has been quoted in the for loop
            $SQL = "SELECT DataSetDataID FROM `datasetdata` WHERE DataSetColumnID = :datasetcolumnid AND Value NOT IN (" . $select . ")";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                'datasetcolumnid' => $this->dataSetColumnId
            ));

            if ($row = $sth->fetch())
                throw new \InvalidArgumentException(__('New list content value is invalid as it doesnt include values for existing data'));
        }
    }

    public function save($options = [])
    {
        $options = array_merge(['validate' => true], $options);

        if ($options['validate'])
            $this->validate();

        if ($this->dataSetColumnId == 0)
            $this->add();
        else
            $this->edit();
    }

    public function delete()
    {
        PDOConnect::update('DELETE FROM `datasetcolumn` WHERE DataSetColumnID = :dataSetColumnId', ['dataSetColumnId' => $this->dataSetColumnId]);
    }

    private function add()
    {
        $this->dataSetColumnId = PDOConnect::insert('
        INSERT INTO `datasetcolumn` (DataSetID, Heading, DataTypeID, ListContent, ColumnOrder, DataSetColumnTypeID, Formula)
          VALUES (:dataSetId, :heading, :dataTypeId, :listContent, :columnOrder, :dataSetColumnTypeId, :formula)
        ', [
            'dataSetId' => $this->dataSetId,
            'heading' => $this->heading,
            'dataTypeId' => $this->dataTypeId,
            'listContent' => $this->listContent,
            'columnOrder' => $this->columnOrder,
            'dataSetColumnTypeId' => $this->dataSetColumnTypeId,
            'formula' => $this->formula
        ]);
    }

    private function edit()
    {
        PDOConnect::update('
          UPDATE `datasetcolumn` SET
            dataSetId = :dataSetId,
            Heading = :heading,
            ListContent = :listContent,
            ColumnOrder = :columnOrder,
            DataTypeID = :dataTypeId,
            DataSetColumnTypeID = :dataSetColumnTypeId,
            Formula = :formula
          WHERE dataSetColumnId = :dataSetColumnId
        ', [
            'dataSetId' => $this->dataSetId,
            'heading' => $this->heading,
            'dataTypeId' => $this->dataTypeId,
            'listContent' => $this->listContent,
            'columnOrder' => $this->columnOrder,
            'dataSetColumnTypeId' => $this->dataSetColumnTypeId,
            'formula' => $this->formula,
            'dataSetColumnId' => $this->dataSetColumnId
        ]);
    }
}