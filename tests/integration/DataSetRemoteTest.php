<?php
/**
 * Copyright (C) 2022 Xibo Signage Ltd
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
use Xibo\Tests\LocalWebTestCase;

/**
 * Test remote datasets
 */
class DataSetRemoteTest extends LocalWebTestCase
{
    /** @var \Xibo\OAuth2\Client\Entity\XiboDataSet */
    private $dataSet;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        // copy json file to /web folder
        shell_exec('cp -r ' . PROJECT_ROOT . '/tests/resources/RemoteDataSet.json ' . PROJECT_ROOT . '/web');

        $this->dataSet = (new XiboDataSet($this->getEntityProvider()))
            ->create(
                Random::generateString(8, 'phpunit'),
                '',
                'remote',
                1,
                'GET',
                'http://localhost/RemoteDataSet.json',
                '',
                '',
                '',
                '',
                1,
                0,
                null,
                'data'
            );

        // Add columns
        $this->dataSet->createColumn(
            'title',
            null,
            1,
            1,
            3,
            null,
            'title'
        );
        $this->dataSet->createColumn(
            'identifier',
            null,
            2,
            2,
            3,
            null,
            'id'
        );
        $this->dataSet->createColumn(
            'date',
            null,
            3,
            3,
            3,
            null,
            'Date'
        );
    }
    
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Delete the dataset
        $this->dataSet->deleteWData();

        // remove json file from /web folder
        shell_exec('rm -r ' . PROJECT_ROOT . '/web/RemoteDataSet.json');

        parent::tearDown();
    }

    public function testRemoteDataSetData()
    {
        // call the remote dataSet test
        $response = $this->sendRequest('POST', '/dataset/remote/test', [
            'testDataSetId' => $this->dataSet->dataSetId,
            'dataSet' => $this->dataSet->dataSet,
            'code' => 'remote',
            'isRemote' => 1,
            'method' => 'GET',
            'uri' => 'http://localhost/RemoteDataSet.json',
            'dataRoot' => 'data',
            'refreshRate' => 0,
            'clearRate' => 1,
            'sourceId' => 1,
            'limitPolicy' => 'stop'
        ]);

        // HTTP response code
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        // Expect a JSON body
        $object = json_decode($response->getBody());

        // Data and ID parameters
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        // Make sure we have the same dataset back
        $this->assertSame($object->id, $this->dataSet->dataSetId);

        // Make sure we parsed out some entries.
        $this->assertNotEmpty($object->data->entries);
        $this->assertNotEmpty($object->data->processed);

        // The entries should match our sample file.
        $this->assertSame(3, $object->data->number);

        // First record
        $this->assertSame(1, $object->data->processed[0][0]->identifier);
        $this->assertSame('Title 1', $object->data->processed[0][0]->title);
        $this->assertSame('2019-07-29 13:11:00', $object->data->processed[0][0]->date);

        // Second record
        $this->assertSame(2, $object->data->processed[0][1]->identifier);
        $this->assertFalse(property_exists($object->data->processed[0][1], 'title'));
        $this->assertSame('2019-07-30 03:04:00', $object->data->processed[0][1]->date);

        // Third record
        $this->assertSame(3, $object->data->processed[0][2]->identifier);
        $this->assertSame('1', $object->data->processed[0][2]->title);
        $this->assertFalse(property_exists($object->data->processed[0][2], 'date'));
    }
}
