<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDataSet;
use Xibo\OAuth2\Client\Entity\XiboDataSetColumn;
use Xibo\OAuth2\Client\Entity\XiboDataSetRow;
use Xibo\Tests\LocalWebTestCase;

class DataSetTest extends LocalWebTestCase
{
    protected $startDataSets;
    
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startDataSets = (new XiboDataSet($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all datasets that weren't there initially
        $finalDataSets = (new XiboDataSet($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);

        $difference = array_udiff($finalDataSets, $this->startDataSets, function ($a, $b) {
            /** @var XiboDataSet $a */
            /** @var XiboDataSet $b */
            return $a->dataSetId - $b->dataSetId;
        });

        # Loop over any remaining datasets and nuke them
        foreach ($difference as $dataSet) {
            /** @var XiboDataSet $dataSet */
            try {
                $dataSet->deleteWData();
            } catch (\Exception $e) {
                fwrite(STDERR, 'Unable to delete ' . $dataSet->dataSetId . '. E: ' . $e->getMessage() . PHP_EOL);
            }
        }
        parent::tearDown();
    }

    /*
    * List all datasets
    */
    public function testListAll()
    {
        $response = $this->sendRequest('GET','/dataset');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
    }

    /**
     * @group add
     */
    public function testAdd()
    {
        # Generate random name
        $name = Random::generateString(8, 'phpunit');
        # Add dataset
        $response = $this->sendRequest('POST','/dataset', [
            'dataSet' => $name,
            'description' => 'PHP Unit Test'
        ]);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Check if dataset has the correct name
        $this->assertSame($name, $object->data->dataSet);
    }


    /**
     * Test edit
     * @depends testAdd
     */
    public function testEdit()
    {
        # Create a new dataset
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create('phpunit dataset', 'phpunit description');
        # Generate new name and description
        $name = Random::generateString(8, 'phpunit');
        $description = 'New description';
        # Edit the name and description
        $response = $this->sendRequest('PUT','/dataset/' . $dataSet->dataSetId, [
            'dataSet' => $name,
            'description' => $description
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        # Check if name and description were correctly changed
        $this->assertSame($name, $object->data->dataSet);
        $this->assertSame($description, $object->data->description);
        # Deeper check by querying for dataset again
        $dataSetCheck = (new XiboDataSet($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $dataSetCheck->dataSet);
        $this->assertSame($description, $dataSetCheck->description);
        # Clean up the dataset as we no longer need it
        $dataSet->delete();
    }

    /**
     * @depends testEdit
     */
    public function testDelete()
    {
        # Generate new random names
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known dataSets
        $data1 = (new XiboDataSet($this->getEntityProvider()))->create($name1, 'phpunit description');
        $data2 = (new XiboDataSet($this->getEntityProvider()))->create($name2, 'phpunit description');
        # Delete the one we created last
        $response = $this->sendRequest('DELETE','/dataset/' . $data2->dataSetId);
        # This should return 204 for success
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Check only one remains
        $dataSets = (new XiboDataSet($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startDataSets) + 1, count($dataSets));
        $flag = false;
        foreach ($dataSets as $dataSet) {
            if ($dataSet->dataSetId == $data1->dataSetId) {
                $flag = true;
            }
        }
        $this->assertTrue($flag, 'dataSet ID ' . $data1->dataSetId . ' was not found after deleting a different dataset');
    }

    # TO DO /dataset/import/

    /**
     * @dataProvider provideSuccessCases
     */
    public function testAddColumnSuccess($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
    {
        # Create radom name and description
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column add';
        # Create new dataset
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Create new columns with arguments from provideSuccessCases
        $response = $this->sendRequest('POST','/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $columnName,
            'listContent' => $columnListContent,
            'columnOrder' => $columnOrd,
            'dataTypeId' => $columnDataTypeId,
            'dataSetColumnTypeId' => $columnDataSetColumnTypeId,
            'formula' => $columnFormula
        ]);
        # Check that call was successful
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        # Check that columns have correct parameters
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($columnName, $object->data->heading);
        $this->assertSame($columnListContent, $object->data->listContent);
        $this->assertSame($columnOrd, $object->data->columnOrder);
        $this->assertSame($columnDataTypeId, $object->data->dataTypeId);
        $this->assertSame($columnDataSetColumnTypeId, $object->data->dataSetColumnTypeId);
        $this->assertSame($columnFormula, $object->data->formula);
        # Check that column was correctly added
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->getById($dataSet->dataSetId, $object->id);
        $this->assertSame($columnName, $column->heading);
        # Clean up the dataset as we no longer need it
        $this->assertTrue($dataSet->delete(), 'Unable to delete ' . $dataSet->dataSetId);
    }

    /**
     * Each array is a test run
     * Format ($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
     * @return array
     */

    public function provideSuccessCases()
    {
        # Cases we provide to testAddColumnSucess, you can extend it by simply adding new case here
        return [
            # Value
            'Value String' => ['Test Column Value String', NULL, 2, 1, 1, NULL],
            'List Content' => ['Test Column list content', 'one,two,three', 2, 1, 1, NULL],
            'Value Number' => ['Test Column Value Number', NULL, 2, 2, 1, NULL],
            'Value Date' => ['Test Column Value Date', NULL, 2, 3, 1, NULL],
            'External Image' => ['Test Column Value External Image', NULL, 2, 4, 1, NULL],
            'Library Image' => ['Test Column Value Internal Image', NULL, 2, 5, 1, NULL],
            # Formula
            'Formula' => ['Test Column Formula', NULL, 2, 5, 1, 'Where Name = Dan'],
        ];
    }

    /**
     * @dataProvider provideFailureCases
     */
    public function testAddColumnFailure($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
    {
        # Create random name and description
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column add failure';
        # Create new columns that we expect to fail with arguments from provideFailureCases
        /** @var XiboDataSet $dataSet */
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        $response = $this->sendRequest('POST','/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $columnName,
            'listContent' => $columnListContent,
            'columnOrder' => $columnOrd,
            'dataTypeId' => $columnDataTypeId,
            'dataSetColumnTypeId' => $columnDataSetColumnTypeId,
            'formula' => $columnFormula
        ]);
        # Check if cases are failing as expected
        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode());
    }

    /**
     * Each array is a test run
     * Format ($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
     * @return array
     */

    public function provideFailureCases()
    {
        # Cases we provide to testAddColumnFailure, you can extend it by simply adding new case here
        return [
            // Value
            'Incorrect dataType' => ['incorrect data type', NULL, 2, 12, 1, NULL],     
            'Incorrect columnType' => ['incorrect column type', NULL, 2, 19, 1, NULL],   
            'Empty Name' => [NULL, NULL, 2, 3, 1, NULL],
            'Symbol Name' => ['a.b.c', NULL, 2, 3, 1, NULL],
            'Symbol Name 2' => ['$£"', NULL, 2, 3, 1, NULL]
        ];
    }

    /**
     * Search columns for DataSet
     */
    public function testListAllColumns()
    {
        # Create new dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column list';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Add a new column to our dataset
        $nameCol = Random::generateString(8, 'phpunit');
        $dataSet->createColumn($nameCol,'', 2, 1, 1, '');
        # Search for columns
        $response = $this->sendRequest('GET','/dataset/' . $dataSet->dataSetId . '/column');
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        # Clean up as we no longer need it
        $dataSet->delete();
    }

    /**
     * Test edit column
     */
    public function testColumnEdit()
    {
        # Create dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column edit';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Add new column to our dataset
        $nameCol = Random::generateString(8, 'phpunit');
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        # Generate new random name
        $nameNew = Random::generateString(8, 'phpunit');
        # Edit our column and change the name
        $response = $this->sendRequest('PUT','/dataset/' . $dataSet->dataSetId . '/column/' . $column->dataSetColumnId, [
            'heading' => $nameNew,
            'listContent' => '',
            'columnOrder' => $column->columnOrder,
            'dataTypeId' => $column->dataTypeId,
            'dataSetColumnTypeId' => $column->dataSetColumnTypeId,
            'formula' => ''
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Check if our column has updated name
        $this->assertSame($nameNew, $object->data->heading);
        # Clean up as we no longer need it
        $dataSet->delete();
    }

    /**
     * @param $dataSetId
     * @depends testAddColumnSuccess
     */
    public function testDeleteColumn()
    {
        # Create dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column delete';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Add new column to our dataset
        $nameCol = Random::generateString(8, 'phpunit');
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        # delete column
        $response = $this->sendRequest('DELETE','/dataset/' . $dataSet->dataSetId . '/column/' . $column->dataSetColumnId);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
    }

    /*
    * GET data
    */

    public function testGetData()
    {
        # Create dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Call get data
        $response = $this->sendRequest('GET','/dataset/data/' . $dataSet->dataSetId);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        # Clean up
        $dataSet->delete();
    }
    
    /**
     * Test add row
     */
    public function testRowAdd()
    {
        # Create a new dataset to use
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit row add';
        /** @var XiboDataSet $dataSet */
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Create column and add it to our dataset
        $nameCol = Random::generateString(8, 'phpunit');
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        # Add new row to our dataset and column
        $response = $this->sendRequest('POST','/dataset/data/' . $dataSet->dataSetId, [
            'dataSetColumnId_' . $column->dataSetColumnId => 'test',
            ]);
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Get the row id
        $row = $dataSet->getData();
        $this->getLogger()->debug(json_encode($row));
        # Check if data was correctly added to the row
        $this->assertArrayHasKey($nameCol, $row[0]);
        $this->assertSame($row[0][$nameCol], 'test');
        # Clean up as we no longer need it, deleteWData will delete dataset even if it has data assigned to it
        $dataSet->deleteWData();
    }
    /**
     * Test edit row
     * @dataProvider provideSuccessCasesRow
     */
    public function testRowEdit($data)
    {
        # Create a new dataset to use
        /** @var XiboDataSet $dataSet */
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit row edit';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Generate a new name for the new column
        $nameCol = Random::generateString(8, 'phpunit');
        # Create new column and add it to our dataset
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        # Add new row with data to our dataset
        $rowD = 'test';
        $row = (new XiboDataSetRow($this->getEntityProvider()))->create($dataSet->dataSetId, $column->dataSetColumnId, $rowD);
        # Edit row data
        $response = $this->sendRequest('PUT','/dataset/data/' . $dataSet->dataSetId . '/' . $row['id'], [
            'dataSetColumnId_' . $column->dataSetColumnId => $data
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # get the row id
        $rowCheck = $dataSet->getData();
        # Check if data was correctly added to the row
        $this->assertArrayHasKey($nameCol, $rowCheck[0]);
        if ($data == Null){
            $this->assertSame($rowCheck[0][$nameCol], $rowD);
        }
        else {
         $this->assertSame($rowCheck[0][$nameCol], $data);
        }

        # Clean up as we no longer need it, deleteWData will delete dataset even if it has data assigned to it
        $dataSet->deleteWData();
    }

    /**
     * Each array is a test run
     * Format ($data)
     * @return array
     */

    public function provideSuccessCasesRow()
    {
        # Cases we provide to testRowEdit, you can extend it by simply adding new case here
        return [
            # Value
            'String' => ['API EDITED ROW'],
            'Null' => [NULL],
            'number as string' => ['1212']
        ];
    }

    /*
    * delete row data
    */
    public function testRowDelete()
    {
        # Create a new dataset to use
        /** @var XiboDataSet $dataSet */
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit row delete';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Generate a new name for the new column
        $nameCol = Random::generateString(8, 'phpunit');
        # Create new column and add it to our dataset
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        # Add new row data
        $row = (new XiboDataSetRow($this->getEntityProvider()))->create($dataSet->dataSetId, $column->dataSetColumnId, 'Row Data');
        # Delete row
        $response = $this->sendRequest('DELETE','/dataset/data/' . $dataSet->dataSetId . '/' . $row['id']);
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up as we no longer need it, deleteWData will delete dataset even if it has data assigned to it
        $dataSet->deleteWData();
    }

    public function testAddRemoteDataSet()
    {
        $name = Random::generateString(8, 'phpunit');
        # Add dataset
        $response = $this->sendRequest('POST','/dataset', [
            'dataSet' => $name,
            'code' => 'remote',
            'isRemote' => 1,
            'method' => 'GET',
            'uri' => 'http://localhost/resources/RemoteDataSet.json',
            'dataRoot' => 'data',
            'refreshRate' => 0,
            'clearRate' => 1,
            'sourceId' => 1,
            'limitPolicy' => 'stop'
        ]);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        # Check dataSet object
        $this->assertSame($name, $object->data->dataSet);
        $this->assertSame(1, $object->data->isRemote);
        $this->assertSame('http://localhost/resources/RemoteDataSet.json', $object->data->uri);
        $this->assertSame(1, $object->data->clearRate);
        $this->assertSame(0, $object->data->refreshRate);
        $this->assertSame(0, $object->data->lastClear);
        $this->assertSame(1, $object->data->sourceId);
    }

    public function testEditRemoteDataSet()
    {
        $name = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');

        // add DataSet with wrapper
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, '', 'remote', 1, 'GET', 'http://localhost/resources/RemoteDataSet.json', '', '', '', '', 1, 0, null, 'data');

        // Edit DataSet
        $response = $this->sendRequest('PUT','/dataset/' . $dataSet->dataSetId, [
            'dataSet' => $name2,
            'code' => 'remote',
            'isRemote' => 1,
            'method' => 'GET',
            'uri' => 'http://localhost/resources/RemoteDataSet.json',
            'dataRoot' => 'data',
            'clearRate' => 3600,
            'refreshRate' => 1,
            'sourceId' => 1,
            'limitPolicy' => 'stop'
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        # Check dataSet object
        $this->assertSame($name2, $object->data->dataSet);
        $this->assertSame(1, $object->data->isRemote);
        $this->assertSame('http://localhost/resources/RemoteDataSet.json', $object->data->uri);
        $this->assertSame(3600, $object->data->clearRate);
        $this->assertSame(1, $object->data->refreshRate);
        $this->assertSame(1, $object->data->sourceId);
    }
}
