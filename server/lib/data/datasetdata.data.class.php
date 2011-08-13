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

class DataSetData extends Data
{
    public function Add($dataSetColumnId, $rowNumber, $value)
    {
        $db =& $this->db;

        $SQL  = "INSERT INTO datasetdata (DataSetColumnID, RowNumber, Value) ";
        $SQL .= "    VALUES (%d, %d, '%s') ";
        $SQL = sprintf($SQL, $dataSetColumnId, $rowNumber, $value);

        if (!$id = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25005, __('Could not add DataSet Data'));
        }

        Debug::LogEntry($db, 'audit', 'Complete', 'DataSetData', 'Add');

        return $id;
    }

    public function Edit($dataSetColumnId, $rowNumber, $value)
    {
        $db =& $this->db;

        $SQL  = "UPDATE datasetdata SET Value = '%s' ";
        $SQL .= " WHERE DataSetColumnID = %d AND RowNumber = %d";

        $SQL = sprintf($SQL, $value, $dataSetColumnId, $rowNumber);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25005, __('Could not edit DataSet Data'));
        }

        Debug::LogEntry($db, 'audit', 'Complete', 'DataSetData', 'Edit');

        return true;
    }

    public function Delete($dataSetColumnId, $rowNumber)
    {
        $db =& $this->db;

        $SQL  = "DELETE FROM datasetdata ";
        $SQL .= " WHERE DataSetColumnID = %d AND RowNumber = %d";

        $SQL = sprintf($SQL, $dataSetColumnId, $rowNumber);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25005, __('Could not delete Data for Column/Row'));
        }

        Debug::LogEntry($db, 'audit', 'Complete', 'DataSetData', 'Delete');

        return true;
    }
}
?>