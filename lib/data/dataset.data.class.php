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

class DataSet extends Data
{
    public function hasData($dataSetId)
    {
        try {
            $dbh = PDOConnect::init();
        
            // First check to see if we have any data
            $sth = $dbh->prepare('SELECT * FROM `datasetdata` INNER JOIN `datasetcolumn` ON datasetcolumn.DataSetColumnID = datasetdata.DataSetColumnID WHERE datasetcolumn.DataSetID = :datasetid');
            $sth->execute(array(
                    'datasetid' => $dataSetId
                ));
    
            return ($sth->fetch());
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Add a data set
     * @param <type> $dataSet
     * @param <type> $description
     * @param <type> $userId
     * @return <type>
     */
    public function Add($dataSet, $description, $userId)
    {
        try {
            $dbh = PDOConnect::init();

            // Validation
            if (strlen($dataSet) > 50 || strlen($dataSet) < 1)
                return $this->SetError(25001, __("Name must be between 1 and 50 characters"));

            if (strlen($description) > 254)
                return $this->SetError(25002, __("Description can not be longer than 254 characters"));


            // Ensure there are no layouts with the same name
            $sth = $dbh->prepare('SELECT DataSet FROM dataset WHERE DataSet = :dataset');
            $sth->execute(array(
                    'dataset' => $dataSet
                ));

            if ($row = $sth->fetch())
                return $this->SetError(25004, sprintf(__("There is already dataset called '%s'. Please choose another name."), $dataSet));

            // End Validation

            $SQL = "INSERT INTO dataset (DataSet, Description, UserID) ";
            $SQL .= " VALUES (:dataset, :description, :userid) ";

            // Insert the data set
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'dataset' => $dataSet,
                    'description' => $description,
                    'userid' => $userId
                ));

            $id = $dbh->lastInsertId();

            Debug::LogEntry('audit', 'Complete', 'DataSet', 'Add');

            return $id;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, __('Could not add DataSet'));
        }
    }

    /**
     * Edit a DataSet
     * @param <type> $dataSetId
     * @param <type> $dataSet
     * @param <type> $description
     */
    public function Edit($dataSetId, $dataSet, $description)
    {
        try {
            $dbh = PDOConnect::init();

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
            $sth = $dbh->prepare('SELECT DataSet FROM dataset WHERE DataSet = :dataset AND DataSetID <> :datasetid');
            $sth->execute(array(
                    'dataset' => $dataSet,
                    'datasetid' => $dataSetId
                ));

            if ($row = $sth->fetch())
                return $this->SetError(25004, sprintf(__("There is already dataset called '%s'. Please choose another name."), $dataSet));

            // End Validation
             
            // Update the data set
            $sth = $dbh->prepare('UPDATE dataset SET DataSet = :dataset, Description = :description WHERE DataSetID = :datasetid');
            $sth->execute(array(
                    'dataset' => $dataSet,
                    'description' => $description,
                    'datasetid' => $dataSetId
                ));

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, sprintf(__('Cannot edit dataset %s'), $dataSet));
        }
    }

    /**
     * Delete DataSet
     * @param <type> $dataSetId
     */
    public function Delete($dataSetId)
    {
        try {
            $dbh = PDOConnect::init();

            // Delete the Data
            $data = new DataSetData();
            $data->DeleteAll($dataSetId);

            // Delete security
            $security = new DataSetGroupSecurity($this->db);
            $security->UnlinkAll($dataSetId);

            // Delete columns
            $dataSetObject = new DataSetColumn($this->db);
            if (!$dataSetObject->DeleteAll($dataSetId))
                return $this->SetError(25005, __('Cannot delete dataset, columns could not be deleted.'));

            // Delete data set
            $sth = $dbh->prepare('DELETE FROM dataset WHERE DataSetID = :datasetid');
            $sth->execute(array(
                    'datasetid' => $dataSetId
                ));

            return true;
        }
        catch (Exception $e) {

            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25005, sprintf(__('Cannot edit dataset %s'), $dataSet));

            return false;
        }
    }

    public function LinkLayout($dataSetId, $layoutId, $regionId, $mediaId) {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('INSERT INTO `lkdatasetlayout` (DataSetID, LayoutID, RegionID, MediaID) VALUES (:datasetid, :layoutid, :regionid, :mediaid)');
            $sth->execute(array(
                    'datasetid' => $dataSetId, 
                    'layoutid' => $layoutId, 
                    'regionid' => $regionId, 
                    'mediaid' => $mediaId
                ));
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function UnlinkLayout($dataSetId, $layoutId, $regionId, $mediaId) {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM `lkdatasetlayout` WHERE DataSetID = :datasetid AND LayoutID = :layoutid AND RegionID = :regionid AND MediaID = :mediaid');
            $sth->execute(array(
                    'datasetid' => $dataSetId, 
                    'layoutid' => $layoutId, 
                    'regionid' => $regionId, 
                    'mediaid' => $mediaId
                ));
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function GetDataSetFromLayout($layoutId, $regionId, $mediaId) {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT `dataset`.* FROM `lkdatasetlayout` INNER JOIN `dataset` ON lkdatasetlayout.DataSetId = dataset.DataSetID WHERE LayoutID = :layoutid AND RegionID = :regionid AND MediaID = :mediaid');
            $sth->execute(array(
                    'layoutid' => $layoutId, 
                    'regionid' => $regionId, 
                    'mediaid' => $mediaId
                ));

            return $sth->fetchAll();
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function GetCampaignsForDataSet($dataSetId) {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT DISTINCT `lkcampaignlayout`.CampaignID FROM `lkdatasetlayout` INNER JOIN `lkcampaignlayout` ON `lkcampaignlayout`.LayoutID = `lkdatasetlayout`.LayoutID WHERE DataSetID = :datasetid');
            $sth->execute(array(
                    'datasetid' => $dataSetId
                ));

            $ids = array();

            foreach ($sth->fetchAll() as $id)
                $ids[] = $id['CampaignID'];

            return $ids;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function GetLastDataEditTime($dataSetId) {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT LastDataEdit FROM `dataset` WHERE DataSetID = :dataset_id');
            $sth->execute(array(
                    'dataset_id' => $dataSetId
                ));

            $updateDate = $sth->fetchColumn(0);
          
            Debug::LogEntry('audit', sprintf('Returning update date %s for DataSetId %d', $updateDate, $dataSetId), 'dataset', 'GetLastDataEditTime');

            return $updateDate;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Data Set Results
     * @param <type> $dataSetId
     * @param <type> $columnIds
     * @param <type> $filter
     * @param <type> $ordering
     * @param <type> $lowerLimit
     * @param <type> $upperLimit
     * @return <type>
     */
    public function DataSetResults($dataSetId, $columnIds, $filter = '', $ordering = '', $lowerLimit = 0, $upperLimit = 0, $displayId = 0)
    {
        $blackList = array(';', 'INSERT', 'UPDATE', 'SELECT', 'DELETE', 'TRUNCATE', 'TABLE', 'FROM', 'WHERE');

        try {
            $dbh = PDOConnect::init();
            $params = array('dataSetId' => $dataSetId);
            
            $selectSQL = '';
            $outserSelect = '';
            $finalSelect = '';
            $results = array();
            $headings = array();
            $allowedOrderCols = array();
            $filter = str_replace($blackList, '', $filter);
            $filter = str_replace('[DisplayId]', $displayId, $filter);
            
            $columns = explode(',', $columnIds);
    
            // Get the Latitude and Longitude ( might be used in a formula )
            if ($displayId == 0) {
                $defaultLat = Config::GetSetting('DEFAULT_LAT');
                $defaultLong = Config::GetSetting('DEFAULT_LONG');
                $displayGeoLocation = "GEOMFROMTEXT('POINT(" . $defaultLat . " " . $defaultLong . ")')";
            }
            else
                $displayGeoLocation = sprintf("(SELECT GeoLocation FROM `display` WHERE DisplayID = %d)", $displayId);
    
            // Get all columns for the cross tab
            $sth = $dbh->prepare('SELECT DataSetColumnID, Heading, DataSetColumnTypeID, Formula, DataTypeID FROM datasetcolumn WHERE DataSetID = :dataSetId');
            $sth->execute(array('dataSetId' => $dataSetId));
            $allColumns = $sth->fetchAll();
    
            foreach($allColumns as $col)
            {
                $heading = $col;
                $heading['Text'] = $heading['Heading'];
                $allowedOrderCols[] = $heading['Heading'];

                $formula = str_replace($blackList, '', htmlspecialchars_decode($col['Formula'], ENT_QUOTES));
                
                // Is this column a formula column or a value column?
                if ($col['DataSetColumnTypeID'] == 2) {
                    // Formula
                    $formula = str_replace('[DisplayGeoLocation]', $displayGeoLocation, $formula);
                    $formula = str_replace('[DisplayId]', $displayId, $formula);

                    $heading['Heading'] = $formula . ' AS \'' . $heading['Heading'] . '\'';
                }
                else {
                    // Value
                    $selectSQL .= sprintf("MAX(CASE WHEN DataSetColumnID = %d THEN `Value` ELSE null END) AS '%s', ", $col['DataSetColumnID'], $heading['Heading']);
                }
    
                $headings[] = $heading;
            }
    
            // Build our select statement including formulas
            foreach($headings as $heading)
            {
                if ($heading['DataSetColumnTypeID'] == 2)
                    // This is a formula, so the heading has been morphed into some SQL to run
                    $outserSelect .= ' ' . $heading['Heading'] . ',';
                else
                    $outserSelect .= sprintf(' `%s`,', $heading['Heading']);
            }
            $outserSelect = rtrim($outserSelect, ',');
    
            // For each heading, put it in the correct order (according to $columns)
            foreach($columns as $visibleColumn)
            {
                foreach($headings as $heading)
                {
                    if ($heading['DataSetColumnID'] == $visibleColumn)
                    {
                        $finalSelect .= sprintf(' `%s`,', $heading['Text']);
                        
                        $results['Columns'][] = $heading;
                    }
                }
            }
            $finalSelect = rtrim($finalSelect, ',');
    
            // We are ready to build the select and from part of the SQL
            $SQL  = "SELECT $finalSelect ";
            $SQL .= "  FROM ( ";
            $SQL .= "   SELECT $outserSelect ,";
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
            if ($filter != '')
            {
                $SQL .= ' WHERE ' . $filter;
            }
            $SQL .= ' ) finalselect ';
    
            if ($ordering != '')
            {
                $order = ' ORDER BY ';
    
                $ordering = explode(',', $ordering);
    
                foreach ($ordering as $orderPair)
                {
                    // Sanitize the clause
                    $sanitized = str_replace(' ASC', '', str_replace(' DESC', '', $orderPair));

                    // Check allowable
                    if (!in_array($sanitized, $allowedOrderCols)) {
                        Debug::Info('Disallowed column: ' . $sanitized);
                        continue;
                    }

                    // Substitute
                    if (strripos($orderPair, ' DESC')) {
                        $order .= sprintf(' `%s`  DESC,', $sanitized);
                    }
                    else if (strripos($orderPair, ' ASC')) {
                        $order .= sprintf(' `%s`  ASC,', $sanitized);
                    }
                    else {
                        $order .= sprintf(' `%s`,', $sanitized);
                    }
                }
    
                $SQL .= trim($order, ',');
            }
            else
            {
                $SQL .= " ORDER BY RowNumber ";
            }
    
            if ($lowerLimit != 0 || $upperLimit != 0)
            {
                // Lower limit should be 0 based
                if ($lowerLimit != 0)
                    $lowerLimit = $lowerLimit - 1;
    
                // Upper limit should be the distance between upper and lower
                $upperLimit = $upperLimit - $lowerLimit;
    
                // Substitute in
                $SQL .= sprintf(' LIMIT %d, %d ', $lowerLimit, $upperLimit);
            }
    
            Debug::Audit($SQL . ' ' . var_export($params, true));
            $sth = $dbh->prepare($SQL);
            //$sth->debugDumpParams();
            $sth->execute($params);
        
            $results['Rows'] = $sth->fetchAll();
    
            return $results;  
        }
        catch (Exception $e) {
            
            Debug::Error($e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function GetDataTypes() {
        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('SELECT datatypeid, datatype FROM datatype');
            $sth->execute();
          
            return $sth->fetchAll();
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));

            return false;
        }
    }

    public function GetDataSetColumnTypes() {
        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('SELECT datasetcolumntypeid, datasetcolumntype FROM datasetcolumntype');
            $sth->execute();
          
            return $sth->fetchAll();
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
