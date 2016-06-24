<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDataSet;
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
        $this->client->get('/dataset');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     * @group add
     * @return int
     */
    public function testAdd()
    {
        $name = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/dataset', [
            'dataSet' => $name,
            'description' => 'PHP Unit Test'
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->dataSet);
    }


    /**
     * Test edit
     * @depends testAdd
     */
    public function testEdit()
    {
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create('phpunit dataset', 'phpunit description');
        $name = Random::generateString(8, 'phpunit');
        $description = 'New description';

        $this->client->put('/dataset/' . $dataSet->dataSetId, [
            'dataSet' => $name,
            'description' => $description
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame($name, $object->data->dataSet);
        $this->assertSame($description, $object->data->description);
        // Deeper check by querying for dataset again
        $dataSetCheck = (new XiboDataSet($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $dataSetCheck->dataSet);
        $this->assertSame($description, $dataSetCheck->description);
        $dataSet->delete();
    }

    /**
     * @depends testEdit
     */
    public function testDelete()
    {
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known dataSets
        $data1 = (new XiboDataSet($this->getEntityProvider()))->create($name1, 'phpunit description');
        $data2 = (new XiboDataSet($this->getEntityProvider()))->create($name2, 'phpunit description');
        # Delete the one we created last
        $this->client->delete('/dataset/' . $data2->dataSetId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
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
        $data1->delete();
    }

    # TO DO /dataset/import/

    /**
     * @dataProvider provideSuccessCases
     */
    public function testAddColumnSuccess($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
    {

        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column add';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        
        $response = $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $columnName,
            'listContent' => $columnListContent,
            'columnOrder' => $columnOrd,
            'dataTypeId' => $columnDataTypeId,
            'dataSetColumnTypeId' => $columnDataSetColumnTypeId,
            'formula' => $columnFormula
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($columnName, $object->data->heading);
        $this->assertSame($columnListContent, $object->data->listContent);
        $this->assertSame($columnOrd, $object->data->columnOrder);
        $this->assertSame($columnDataTypeId, $object->data->dataTypeId);
        $this->assertSame($columnDataSetColumnTypeId, $object->data->dataSetColumnTypeId);
        $this->assertSame($columnFormula, $object->data->formula);
        # Check that column was correctly added
        $dataSetCheck = $dataSet->getByColumnId($object->id);
        $this->assertSame($columnName, $dataSetCheck->heading);

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

        return [
            // Value
            'Value String' => ['Test Column Value String', NULL, 2, 1, 1, NULL],
            'List Content' => ['Test Column list content', 'one,two,three', 2, 1, 1, NULL],
            'Value Number' => ['Test Column Value Number', NULL, 2, 2, 1, NULL],
            'Value Date' => ['Test Column Value Date', NULL, 2, 3, 1, NULL],
            'External Image' => ['Test Column Value External Image', NULL, 2, 4, 1, NULL],
            'Library Image' => ['Test Column Value Internal Image', NULL, 2, 5, 1, NULL],
            // Formula
            'Formula' => ['Test Column Formula', NULL, 2, 5, 1, 'Where Name = Dan'],
        ];
    }

    /**
     * @dataProvider provideFailureCases
     */
    public function testAddColumnFailure($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
    {

        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column add failure';

        /** @var XiboDataSet $dataSet */
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $columnName,
            'listContent' => $columnListContent,
            'columnOrder' => $columnOrd,
            'dataTypeId' => $columnDataTypeId,
            'dataSetColumnTypeId' => $columnDataSetColumnTypeId,
            'formula' => $columnFormula
        ]);

        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
    }

    /**
     * Each array is a test run
     * Format ($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
     * @return array
     */

    public function provideFailureCases()
    {
        return [
            // Value
            'Incorrect dataType' => ['incorrect data type', NULL, 2, 12, 1, NULL],     
            'Incorrect columnType' => ['incorrect column type', NULL, 2, 19, 1, NULL],   
            'Empty Name' => [NULL, NULL, 2, 3, 1, NULL]
        ];
    }

    /**
     * Search columns for DataSet
     * @depends testAddColumnSuccess
     */
    public function testListAllColumns()
    {
        // create new dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column list';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        // add a new column
        $nameCol = Random::generateString(8, 'phpunit');
        $dataSet->createColumn($nameCol,'', 2, 1, 1, '');
        // search for columns
        $this->client->get('/dataset/' . $dataSet->dataSetId . '/column');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $dataSet->delete();
    }

    /**
     * Test edit column
     */
    public function testColumnEdit()
    {
        // create dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column edit';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        // add new column
        $nameCol = Random::generateString(8, 'phpunit');
        $column = $dataSet->createColumn($nameCol,'', 2, 1, 1, '');
        $dataSetCheck = $dataSet->getByColumnId($column->dataSetColumnId);
        // edit column
        $nameNew = Random::generateString(8, 'phpunit');
        $response = $this->client->put('/dataset/' . $dataSet->dataSetId . '/column/' . $dataSetCheck->dataSetColumnId, [
            'heading' => $nameNew,
            'listContent' => '',
            'columnOrder' => $dataSetCheck->columnOrder,
            'dataTypeId' => $dataSetCheck->dataTypeId,
            'dataSetColumnTypeId' => $dataSetCheck->dataSetColumnTypeId,
            'formula' => ''
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($nameNew, $object->data->heading);
        $dataSet->delete();
    }

    /**
     * @param $dataSetId
     * @depends testAddColumnSuccess
     */
    public function testDeleteColumn()
    {
         // create dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column delete';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        // add new column
        $nameCol = Random::generateString(8, 'phpunit');
        $column = $dataSet->createColumn($nameCol,'', 2, 1, 1, '');
        $dataSetCheck = $dataSet->getByColumnId($column->dataSetColumnId);
        // delete column
        $response = $this->client->delete('/dataset/' . $dataSet->dataSetId . '/column/' . $dataSetCheck->dataSetColumnId);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /*
    * GET data
    */

    public function testGetData()
    {
        // create dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);

        $this->client->get('/dataset/data/' . $dataSet->dataSetId);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $dataSet->delete();
    }
    
    /**
     * Test add row
     */
    public function testRowAdd()
    {
        // Create a new dataset to use
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit row add';
        /** @var XiboDataSet $dataSet */
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        // create column
        $nameCol = Random::generateString(8, 'phpunit');
        $column = $dataSet->createColumn($nameCol,'', 2, 1, 1, '');
        //$column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        // Populate the properties for the dataset column
        // TODO: it would be better to have separate wrappers for DataSet and DataSetColumn, with a single wrapper
        // you can only ever operate on 1 column at a time.
        $dataSet->getByColumnId($column->dataSetColumnId);
        // add new row
        $response = $this->client->post('/dataset/data/' . $dataSet->dataSetId, [
            'dataSetColumnId_' . $dataSet->dataSetColumnId => 'test',
            ]);
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        // TODO: This test should verify that the data was added
        // $this->assertSame('test', $object->data->dataSetColumnId_ . $dataSet->dataSetColumnId);
        // Delete the dataSet we used
        $dataSet->deleteWData();
    }
    /**
     * Test edit row
     * @group broken
     */
    public function testRowEdit()
    {
        // Create a new dataset to use
        /** @var XiboDataSet $dataSet */
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit row edit';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        // Generate a new name for the new column
        $nameCol = Random::generateString(8, 'phpunit');
        $column = $dataSet->createColumn($nameCol,'', 2, 1, 1, '');
        
        $dataSet->getByColumnId($column->dataSetColumnId);
        // Add new row data
        $row = $dataSet->createRow($colId, 'test');
        $rowCheck = $dataSet->getByRowId($row->rowId);
        //edit row data
        $response = $this->client->put('/dataset/data/' . $dataSet->dataSetId . $rowCheck->rowId, [
            'dataSetColumnId_' . $dataSet->dataSetColumnId =>  'API EDITED'
            ]);
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $dataSet -> deleteWData();
    }
    /*
    * delete row data
    * @group broken
    */
    public function RowDelete()
    {
        // Create a new dataset to use
        /** @var XiboDataSet $dataSet */
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit row delete';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        // Generate a new name for the new column
        $nameCol = Random::generateString(8, 'phpunit');
        $column = $dataSet->createColumn($nameCol,'', 2, 1, 1, '');
        
        $dataSetCheck = $dataSet->getByColumnId($column->dataSetColumnId);
        $colId = $dataSetCheck->dataSetColumnId;
        // Add new row data
        $row = $dataSet->createRow($colId, 'test');
        $rowCheck = $dataSet->getByRowId($row->rowId);
        // delete row
        $this->client->delete('/dataset/data/' . $dataSet->dataSetId . $rowCheck->rowId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());

        $dataSet -> deleteWData();
    }
}
