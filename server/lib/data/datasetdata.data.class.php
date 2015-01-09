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

class DataSetData extends Data
{
    private $updateWatermark;

    public function __construct() {

        $this->updateWatermark = true;

        parent::__construct();
    }

    /**
     * List all data for this dataset
     * @param int $dataSetId The DataSet ID
     */
    public function GetData($dataSetId) {

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT datasetdata.DataSetColumnID, datasetdata.RowNumber, datasetdata.Value 
                  FROM datasetdata
                    INNER JOIN datasetcolumn
                    ON datasetcolumn.DataSetColumnID = datasetdata.DataSetColumnID
                 WHERE datasetcolumn.DataSetID = :dataset_id');

            $sth->execute(array('dataset_id' => $dataSetId));

            $results = $sth->fetchAll();

            // Check there are some columns returned
            if (count($results) <= 0)
                $this->ThrowError(__('No data'));

            $rows = array();

            foreach($results as $row) {

                $col['datasetcolumnid'] = Kit::ValidateParam($row['DataSetColumnID'], _INT);
                $col['rownumber'] = Kit::ValidateParam($row['RowNumber'], _INT);
                $col['value'] = Kit::ValidateParam($row['Value'], _STRING);

                $rows[] = $col;
            }

            Debug::LogEntry('audit', sprintf('Returning %d columns.', count($rows)), 'DataSetColumn', 'GetData');
          
            return $rows;          
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function Add($dataSetColumnId, $rowNumber, $value)
    {
        if ($dataSetColumnId == 0 || $dataSetColumnId == '')
            return $this->SetError(25001, __('Missing dataSetColumnId'));

        if ($rowNumber == 0 || $rowNumber == '')
            return $this->SetError(25001, __('Missing rowNumber'));

        try {
            $dbh = PDOConnect::init();

            $SQL  = "INSERT INTO datasetdata (DataSetColumnID, RowNumber, Value) ";
            $SQL .= "    VALUES (:datasetcolumnid, :rownumber, :value) ";
            
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'datasetcolumnid' => $dataSetColumnId,
                    'rownumber' => $rowNumber,
                    'value' => $value
               ));

            $id = $dbh->lastInsertId();

            // Update the Water Mark
            $this->UpdateWatermarkWithColumnId($dataSetColumnId);

            Debug::LogEntry('audit', 'Complete', 'DataSetData', 'Add');
            
            return $id;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, __('Could not add DataSet Data'));
        }
    }

    public function Edit($dataSetColumnId, $rowNumber, $value)
    {
        if ($dataSetColumnId == 0 || $dataSetColumnId == '')
            return $this->SetError(25001, __('Missing dataSetColumnId'));

        if ($rowNumber == 0 || $rowNumber == '')
            return $this->SetError(25001, __('Missing rowNumber'));

        try {
            $dbh = PDOConnect::init();

            $SQL  = "UPDATE datasetdata SET Value = :value ";
            $SQL .= " WHERE DataSetColumnID = :datasetcolumnid AND RowNumber = :rownumber";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'datasetcolumnid' => $dataSetColumnId,
                    'rownumber' => $rowNumber,
                    'value' => $value
               ));

            $this->UpdateWatermarkWithColumnId($dataSetColumnId);

            Debug::LogEntry('audit', 'Complete', 'DataSetData', 'Edit');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, __('Could not edit DataSet Data'));
        }
    }

    public function Delete($dataSetColumnId, $rowNumber)
    {
        try {
            $dbh = PDOConnect::init();

            $SQL  = "DELETE FROM datasetdata ";
            $SQL .= " WHERE DataSetColumnID = :datasetcolumnid AND RowNumber = :rownumber";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'datasetcolumnid' => $dataSetColumnId,
                    'rownumber' => $rowNumber
               ));

            $this->UpdateWatermarkWithColumnId($dataSetColumnId);

            Debug::LogEntry('audit', 'Complete', 'DataSetData', 'Delete');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, __('Could not delete Data for Column/Row'));
        }
    }

    public function DeleteAll($dataSetId) {

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));

        try {
            $dbh = PDOConnect::init();

            $SQL  = "";
            $SQL .= "DELETE FROM datasetdata WHERE DataSetColumnId IN ( ";
            $SQL .= "   SELECT DataSetColumnID FROM datasetcolumn WHERE DataSetID = :datasetid ";
            $SQL .= "   )";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'datasetid' => $dataSetId
               ));

            $this->UpdateWatermark($dataSetId);

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, __('Could not delete Data for entire DataSet'));
        }
    }

    /**
     * Update the Water Mark to indicate the last data edit
     * @param int $dataSetColumnId The Data Set Column ID
     */
    private function UpdateWatermarkWithColumnId($dataSetColumnId) {

        if (!$this->updateWatermark)
            return;

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT DataSetID FROM `datasetcolumn` WHERE DataSetColumnID = :dataset_column_id');
            $sth->execute(array(
                    'dataset_column_id' => $dataSetColumnId
                ));
          
            $this->UpdateWatermark($sth->fetchColumn(0));
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Update the Water Mark to indicate the last data edit
     * @param int $dataSetId The Data Set ID to Update
     */
    private function UpdateWatermark($dataSetId) {

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));
        
        if (!$this->updateWatermark)
            return;

        Debug::LogEntry('audit', sprintf('Updating water mark on DataSetId: %d', $dataSetId), 'DataSetData', 'UpdateWatermark');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('UPDATE `dataset` SET LastDataEdit = :last_data_edit WHERE DataSetID = :dataset_id');
            $sth->execute(array(
                    'last_data_edit' => time(),
                    'dataset_id' => $dataSetId
                ));

            // Get affected Campaigns
            Kit::ClassLoader('dataset');
            $dataSet = new DataSet($this->db);
            $campaigns = $dataSet->GetCampaignsForDataSet($dataSetId);

            Kit::ClassLoader('display');
            $display = new Display($this->db);

            foreach ($campaigns as $campaignId) {
                // Assess all displays  
                $campaigns = $display->NotifyDisplays($campaignId);
            }
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function ImportCsv($dataSetId, $csvFile, $spreadSheetMapping, $overwrite = false, $ignoreFirstRow = true) {

        if ($dataSetId == 0 || $dataSetId == '')
            return $this->SetError(25001, __('Missing dataSetId'));

        if (!file_exists($csvFile))
            return $this->SetError(25001, __('CSV File does not exist'));

        if (!is_array($spreadSheetMapping) || count($spreadSheetMapping) <= 0)
            return $this->SetError(25001, __('Missing spreadSheetMapping'));

        Debug::LogEntry('audit', 'spreadSheetMapping: ' . json_encode($spreadSheetMapping), 'DataSetData', 'ImportCsv');
        
        $this->updateWatermark = false;

        try {
            $dbh = PDOConnect::init();

            // Are we overwriting or appending?
            if ($overwrite) {
                // We need to delete all the old data and start from row 1
                if (!$this->DeleteAll($dataSetId))
                    return false;
                
                $rowNumber = 1;
            }
            else {
                // We need to get the MAX row number that currently exists in the data set
                $sth = $dbh->prepare('SELECT IFNULL(MAX(RowNumber), 0) AS RowNumber FROM datasetdata INNER JOIN datasetcolumn ON datasetcolumn.dataSetColumnId = datasetdata.DataSetColumnID WHERE datasetcolumn.DataSetID = :datasetid');
                $sth->execute(array(
                        'datasetid' => $dataSetId
                    ));

                if (!$row = $sth->fetch())
                    return $this->SetError(25005, __('Could not determine the Max row number'));

                $rowNumber = Kit::ValidateParam($row['RowNumber'], _INT);
                $rowNumber++;
            }

            // Match the file content with the column mappings

            // Load the file
            ini_set('auto_detect_line_endings', true);

            $firstRow = true;

            $handle = fopen($csvFile, 'r');
            while (($data = fgetcsv($handle)) !== FALSE ) {

                // The CSV file might have headings, so ignore the first row.
                if ($firstRow) {
                    $firstRow = false;

                    if ($ignoreFirstRow)
                        continue;
                }
                
                for ($cell = 0; $cell < count($data); $cell++) {
                    
                    // Insert the data into the correct column
                    if (isset($spreadSheetMapping[$cell])) {

                        if (!$this->Add($spreadSheetMapping[$cell], $rowNumber, $data[$cell]))
                            return false;
                    }
                }

                // Move on to the next row
                $rowNumber++;
            }

            // Close the file
            fclose($handle);

            // Change the auto detect setting back
            ini_set('auto_detect_line_endings', false);

            // Delete the temporary file
            @unlink($csvFile);

            // TODO: Update list content definitions

            $this->UpdateWatermark($dataSetId);

            return true;
        }
        catch (Exception $e) {

            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25005, __('Unable to Import'));

            return false;
        }
    }
}
?>
