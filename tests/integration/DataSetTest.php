<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetTest.php)
 */


namespace integration;


use Xibo\Entity\DataSet;
use Xibo\Factory\DataSetFactory;
use Xibo\Helper\Random;
use Xibo\Tests\LocalWebTestCase;

class DataSetTest extends LocalWebTestCase
{
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
        return $object->id;
    }

    /**
     * Test edit
     * @param int $dataSetId
     * @return int the id
     * @depends testAdd
     */
    public function testEdit($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        $name = Random::generateString(8, 'phpunit');

        $this->client->put('/dataset/' . $dataSetId, [
            'dataSet' => $name,
            'description' => $dataSet->description
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);

        // Deeper check by querying for resolution again
        $object = DataSetFactory::getById($dataSetId);

        $this->assertSame($name, $object->dataSet);

        return $dataSetId;
    }

    /**
     * @param $dataSetId
     * @depends testEdit
     */
    public function testDelete($dataSetId)
    {
        $this->client->delete('/dataset/' . $dataSetId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /**
     * Test adding a column
     */
    public function testColumnAdd()
    {
        // Create a new dataset to use
        $dataSet = new DataSet();
        $dataSet->dataSet = Random::generateString(8, 'phpunit');
        $dataSet->description = 'PHP Unit column assign';
        $dataSet->userId = 1;
        $dataSet->save();

        // Generate a new for the new column
        $name = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $name,
            'listContent' => '',
            'columnOrder' => 2,
            'dataTypeId' => 1,
            'dataSetColumnTypeId' => 1,
            'formula' => ''
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->heading);
        return $object->id;
    }
}