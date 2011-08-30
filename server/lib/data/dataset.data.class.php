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

class DataSet extends Data
{
    /**
     * Add a data set
     * @param <type> $dataSet
     * @param <type> $description
     * @param <type> $userId
     * @return <type>
     */
    public function Add($dataSet, $description, $userId)
    {
        $db =& $this->db;

        // Validation
        if (strlen($dataSet) > 50 || strlen($dataSet) < 1)
        {
            $this->SetError(25001, __("Name must be between 1 and 50 characters"));
            return false;
        }

        if (strlen($description) > 254)
        {
            $this->SetError(25002, __("Description can not be longer than 254 characters"));
            return false;
        }

        // Ensure there are no layouts with the same name
        $SQL = sprintf("SELECT DataSet FROM dataset WHERE DataSet = '%s' ", $dataSet);

        if ($db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25004, sprintf(__("There is already dataset called '%s'. Please choose another name."), $dataSet));
            return false;
        }
        // End Validation

        $SQL = "INSERT INTO dataset (DataSet, Description, UserID) ";
        $SQL .= " VALUES ('%s', '%s', %d) ";

        if (!$id = $db->insert_query(sprintf($SQL, $dataSet, $description, $userId)))
        {
            trigger_error($db->error());
            $this->SetError(25005, __('Could not add DataSet'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'Complete', 'DataSet', 'Add');

        return $id;
    }

    /**
     * Edit a DataSet
     * @param <type> $dataSetId
     * @param <type> $dataSet
     * @param <type> $description
     */
    public function Edit($dataSetId, $dataSet, $description)
    {
        $db =& $this->db;

        // Validation
        if (strlen($dataSet) > 50 || strlen($dataSet) < 1)
        {
            $this->SetError(25001, __("Name must be between 1 and 50 characters"));
            return false;
        }

        if (strlen($description) > 254)
        {
            $this->SetError(25002, __("Description can not be longer than 254 characters"));
            return false;
        }

        // Ensure there are no layouts with the same name
        $SQL = sprintf("SELECT DataSet FROM dataset WHERE DataSet = '%s' AND DataSetID <> %d ", $dataSet, $dataSetId);

        if ($db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25004, sprintf(__("There is already a dataset called '%s'. Please choose another name."), $dataSet));
            return false;
        }
        // End Validation

        $SQL = "UPDATE dataset SET DataSet = '%s', Description = '%s' WHERE DataSetID = %d ";
        $SQL = sprintf($SQL, $dataSet, $description, $dataSetId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25005, sprintf(__('Cannot edit dataset %s'), $dataSet));
            return false;
        }

        return true;
    }

    /**
     * Delete DataSet
     * @param <type> $dataSetId
     */
    public function Delete($dataSetId)
    {
        $db =& $this->db;

        Kit::ClassLoader('datasetgroupsecurity');
        $security = new DataSetGroupSecurity($db);
        $security->UnlinkAll($dataSetId);

        $SQL = "DELETE FROM dataset WHERE DataSetID = %d";
        $SQL = sprintf($SQL, $dataSetId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25005, __('Cannot delete dataset'));
            return false;
        }
        
        return true;
    }

    public function DataSetResults($dataSetId, $columnIds, $filter = "", $lowerLimit = 0, $upperLimit = 0)
    {
        $db =& $this->db;

        $selectSQL = '';
        $results = array();
        $headings = array();
        
        $columns = explode(',', $columnIds);

        foreach($columns as $col)
        {
            $heading = $db->GetSingleValue(sprintf('SELECT Heading FROM datasetcolumn WHERE DataSetColumnID = %d', $col), 'Heading', _STRING);
            $headings[] = $heading;
            $selectSQL .= sprintf("MAX(CASE WHEN DataSetColumnID = %d THEN `Value` ELSE null END) AS '%s', ", $col, $heading);
        }

        $results['Columns'] = $headings;

        $SQL  = "SELECT * ";
        $SQL .= "  FROM ( ";
        $SQL .= "   SELECT $selectSQL ";
        $SQL .= "       RowNumber ";
        $SQL .= "     FROM (";
        $SQL .= "       SELECT datasetcolumn.DataSetColumnID, datasetdata.RowNumber, datasetdata.`Value` ";
        $SQL .= "         FROM datasetdata ";
        $SQL .= "           INNER JOIN datasetcolumn ";
        $SQL .= "           ON datasetcolumn.DataSetColumnID = datasetdata.DataSetColumnID ";
        $SQL .= sprintf("       WHERE datasetcolumn.DataSetID = %d ", $dataSetId);
        $SQL .= "       ) datasetdatainner ";
        $SQL .= "   GROUP BY RowNumber ";
        $SQL .= " ) datasetdata ";
        if ($filter != '')
        {
            $where = ' WHERE 1=1 ';

            $filter = explode(',', $filter);

            foreach ($filter as $filterPair)
            {
                $filterPair = explode('=', $filterPair);
                $where .= sprintf(" AND %s = '%s' ", $filterPair[0], $db->escape_string($filterPair[1]));
            }

            $SQL .= $where . ' ';
        }
        $SQL .= "ORDER BY RowNumber ";

        if ($lowerLimit != 0)
        {
            $upperLimit = $upperLimit - $lowerLimit + 1;
            $SQL .= sprintf('LIMIT %d, %d ', $lowerLimit, $upperLimit);
        }

        Debug::LogEntry($db, 'audit', $SQL);
        
        $results['Rows'] = $db->GetArray($SQL, false);

        return $results;
    }
}
?>