<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011 Daniel Garner
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
        $db =& $this->db;

        // Is the column order provided?
        if ($columnOrder == 0)
        {
            $SQL  = "";
            $SQL .= "SELECT IFNULL(MAX(ColumnOrder), 1) AS ColumnOrder ";
            $SQL .= "  FROM datasetcolumn ";
            $SQL .= sprintf("WHERE datasetID = %d ", $dataSetId);

            if (!$columnOrder = $db->GetSingleValue($SQL, 'ColumnOrder', _INT))
            {
                trigger_error($db->error());
                return $this->SetError(25005, __('Could not determine the Column Order'));
            }
        }

        $SQL  = "INSERT INTO datasetcolumn (DataSetID, Heading, DataTypeID, ListContent, ColumnOrder, DataSetColumnTypeID, Formula) ";
        $SQL .= "    VALUES (%d, '%s', %d, '%s', %d, %d, '%s') ";
        $SQL = sprintf($SQL, $dataSetId, $db->escape_string($heading), $dataTypeId, $db->escape_string($listContent), $columnOrder, $dataSetColumnTypeId, $db->escape_string($formula));

        if (!$id = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25005, __('Could not add DataSet Column'));
        }

        Debug::LogEntry($db, 'audit', 'Complete', 'DataSetColumn', 'Add');

        return $id;
    }

    public function Edit($dataSetColumnId, $heading, $dataTypeId, $listContent, $columnOrder, $dataSetColumnTypeId, $formula = '')
    {
        $db =& $this->db;

        // Validation
        if ($listContent != '')
        {
            $list = explode(',', $listContent);

            // We can check this is valid by building up a NOT IN sql statement, if we get results.. we know its not good
            $select = '';

            for ($i=0; $i < count($list); $i++)
            {
                $list_val = $list[$i];
                $select .= "'$list_val',";
            }

            $select = rtrim($select, ',');

            $SQL = sprintf("SELECT DataSetDataID FROM datasetdata WHERE DataSetColumnID = %d AND Value NOT IN (%s)", $dataSetColumnId, $select);

            if (!$results = $db->query($SQL))
            {
                trigger_error($db->error());
                return $this->SetError(25005, __('Could not edit DataSet Column'));
            }

            if ($db->num_rows($results) > 0)
                return $this->SetError(25005, __('New list content value is invalid as it doesnt include values for existing data'));
        }

        $SQL  = "UPDATE datasetcolumn SET Heading = '%s', ListContent = '%s', ColumnOrder = %d, DataTypeID = %d, DataSetColumnTypeID = %d, Formula = '%s' ";
        $SQL .= " WHERE DataSetColumnID = %d";

        $SQL = sprintf($SQL, $heading, $db->escape_string($listContent), $db->escape_string($columnOrder), $dataTypeId, $dataSetColumnTypeId, $db->escape_string($formula), $dataSetColumnId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25005, __('Could not edit DataSet Column'));
        }

        Debug::LogEntry($db, 'audit', 'Complete', 'DataSetColumn', 'Edit');

        return true;
    }

    public function Delete($dataSetColumnId)
    {
        $db =& $this->db;

        $SQL  = "DELETE FROM datasetcolumn ";
        $SQL .= " WHERE DataSetColumnID = %d";

        $SQL = sprintf($SQL, $dataSetColumnId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25005, __('Could not delete DataSet Column'));
        }

        Debug::LogEntry($db, 'audit', 'Complete', 'DataSetColumn', 'Delete');

        return true;
    }


    // Delete All Data Set columns
    public function DeleteAll($dataSetId)
    {
        $db =& $this->db;

        $SQL  = "DELETE FROM datasetcolumn ";
        $SQL .= " WHERE DataSetId = %d";

        $SQL = sprintf($SQL, $dataSetId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25005, __('Could not delete DataSet Columns'));
        }

        Debug::LogEntry($db, 'audit', 'Complete', 'DataSetColumn', 'Delete');

        return true;
    }
}
?>