<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-13 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class DataSetColumn extends Data
{
    public function Add($dataSetId, $heading, $dataTypeId, $listContent, $columnOrder = 0, $dataSetColumnTypeId = 1, $formula = '')
    {
        Debug::LogEntry('audit', sprintf('IN - DataSetID = %d', $dataSetId), 'DataSetColumn', 'Add');

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));
        
        if ($dataTypeId == 0 || $dataTypeId == '')
            return $this->SetError(25001, __('Missing dataTypeId'));
        
        if ($dataSetColumnTypeId == 0 || $dataSetColumnTypeId == '')
            return $this->SetError(25001, __('Missing dataSetColumnTypeId'));

        if ($heading == '')
            return $this->SetError(25001, __('Please provide a column heading.'));

        try {
            $dbh = PDOConnect::init();

            // Is the column order provided?
            if ($columnOrder == 0)
            {
                $SQL  = "";
                $SQL .= "SELECT IFNULL(MAX(ColumnOrder), 1) AS ColumnOrder ";
                $SQL .= "  FROM datasetcolumn ";
                $SQL .= "WHERE datasetID = :datasetid ";

                $sth = $dbh->prepare($SQL);
                $sth->execute(array(
                        'datasetid' => $dataSetId
                    ));

                if (!$row = $sth->fetch())
                    return $this->SetError(25005, __('Could not determine the Column Order'));
                
                $columnOrder = Kit::ValidateParam($row['ColumnOrder'], _INT);
            }

            // Insert the data set column
            $SQL  = "INSERT INTO datasetcolumn (DataSetID, Heading, DataTypeID, ListContent, ColumnOrder, DataSetColumnTypeID, Formula) ";
            $SQL .= "    VALUES (:datasetid, :heading, :datatypeid, :listcontent, :columnorder, :datasetcolumntypeid, :formula) ";
            
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'datasetid' => $dataSetId,
                    'heading' => $heading,
                    'datatypeid' => $dataTypeId,
                    'listcontent' => $listContent,
                    'columnorder' => $columnOrder,
                    'datasetcolumntypeid' => $dataSetColumnTypeId,
                    'formula' => $formula
               ));

            $id = $dbh->lastInsertId();

            Debug::LogEntry('audit', 'Complete', 'DataSetColumn', 'Add');

            return $id;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, __('Could not add DataSet Column'));
        }
    }

    public function Edit($dataSetColumnId, $heading, $dataTypeId, $listContent, $columnOrder, $dataSetColumnTypeId, $formula = '')
    {
        if ($dataSetColumnId == 0 || $dataSetColumnId == '')
            return $this->SetError(25001, __('Missing dataSetColumnId'));

        if ($dataTypeId == 0 || $dataTypeId == '')
            return $this->SetError(25001, __('Missing dataTypeId'));
        
        if ($dataSetColumnTypeId == 0 || $dataSetColumnTypeId == '')
            return $this->SetError(25001, __('Missing dataSetColumnTypeId'));

        if ($heading == '')
            return $this->SetError(25001, __('Please provide a column heading.'));

        try {
            $dbh = PDOConnect::init();

            // Validation
            if ($listContent != '')
            {
                $list = explode(',', $listContent);

                // We can check this is valid by building up a NOT IN sql statement, if we get results.. we know its not good
                $select = '';

                for ($i=0; $i < count($list); $i++)
                {
                    $list_val = $dbh->quote($list[$i]);
                    $select .= $list_val . ',';
                }

                $select = rtrim($select, ',');

                // $select has been quoted in the for loop
                $SQL = "SELECT DataSetDataID FROM datasetdata WHERE DataSetColumnID = :datasetcolumnid AND Value NOT IN (" . $select . ")";

                $sth = $dbh->prepare($SQL);
                $sth->execute(array(
                        'datasetcolumnid' => $dataSetColumnId
                    ));

                if ($row = $sth->fetch())
                    return $this->SetError(25005, __('New list content value is invalid as it doesnt include values for existing data'));
            }

            $SQL  = "UPDATE datasetcolumn SET Heading = :heading, ListContent = :listcontent, ColumnOrder = :columnorder, DataTypeID = :datatypeid, DataSetColumnTypeID = :datasetcolumntypeid, Formula = :formula ";
            $SQL .= " WHERE DataSetColumnID = :datasetcolumnid";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'datasetcolumnid' => $dataSetColumnId,
                    'heading' => $heading,
                    'datatypeid' => $dataTypeId,
                    'listcontent' => $listContent,
                    'columnorder' => $columnOrder,
                    'datasetcolumntypeid' => $dataSetColumnTypeId,
                    'formula' => $formula
               ));

            Debug::LogEntry('audit', 'Complete for ' . $heading, 'DataSetColumn', 'Edit');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, __('Could not edit DataSet Column'));
        }
    }

    public function Delete($dataSetColumnId)
    {
        if ($dataSetColumnId == 0 || $dataSetColumnId == '')
            return $this->SetError(25001, __('Missing dataSetColumnId'));

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM datasetcolumn WHERE DataSetColumnID = :datasetcolumnid');
            $sth->execute(array(
                    'datasetcolumnid' => $dataSetColumnId
                ));

            Debug::LogEntry('audit', 'Complete', 'DataSetColumn', 'Delete');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, __('Could not delete DataSet Column'));
        }
    }

    // Delete All Data Set columns
    public function DeleteAll($dataSetId)
    {
        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM datasetcolumn WHERE DataSetId = :datasetid');
            $sth->execute(array(
                    'datasetid' => $dataSetId
                ));

            Debug::LogEntry('audit', 'Complete', 'DataSetColumn', 'DeleteAll');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, __('Could not delete DataSet Column'));
        }
    }

    public function GetColumns($dataSetId) {

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('SELECT DataSetColumnID, Heading, datatype.DataType, datasetcolumntype.DataSetColumnType, ListContent, ColumnOrder 
                  FROM datasetcolumn 
                   INNER JOIN `datatype` 
                   ON datatype.DataTypeID = datasetcolumn.DataTypeID 
                   INNER JOIN `datasetcolumntype` 
                   ON datasetcolumntype.DataSetColumnTypeID = datasetcolumn.DataSetColumnTypeID 
                 WHERE DataSetID = :datasetid
                ORDER BY ColumnOrder ');

            $sth->execute(array(
                    'datasetid' => $dataSetId
                ));

            $results = $sth->fetchAll();

            // Check there are some columns returned
            if (count($results) <= 0)
                $this->ThrowError(__('No columns'));

            $rows = array();

            foreach($results as $row) {

                $col['datasetcolumnid'] = Kit::ValidateParam($row['DataSetColumnID'], _INT);
                $col['heading'] = Kit::ValidateParam($row['Heading'], _STRING);
                $col['listcontent'] = Kit::ValidateParam($row['ListContent'], _STRING);
                $col['columnorder'] = Kit::ValidateParam($row['ColumnOrder'], _INT);
                $col['datatype'] = Kit::ValidateParam($row['DataType'], _STRING);
                $col['datasetcolumntype'] = Kit::ValidateParam($row['DataSetColumnType'], _STRING);

                $rows[] = $col;
            }

            Debug::LogEntry('audit', sprintf('Returning %d columns.', count($rows)), 'DataSetColumn', 'GetColumns');
          
            return $rows;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));

            return false;
        }
    }
}
?>
