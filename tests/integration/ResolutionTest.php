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
use Xibo\OAuth2\Client\Entity\XiboResolution;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ResolutionTest
 * @package Xibo\Tests\Integration
 */
class ResolutionTest extends LocalWebTestCase
{

    protected $startResolutions;
    
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startResolutions = (new XiboResolution($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all resolutions that weren't there initially
        $finalResolutions = (new XiboResolution($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining resolutions and nuke them
        foreach ($finalResolutions as $resolution) {
            /** @var XiboResolution $resolution */
            $flag = true;
            foreach ($this->startResolutions as $startRes) {
               if ($startRes->resolutionId == $resolution->resolutionId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $resolution->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $resolution->resolutionId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    public function testListAll()
    {
        $response = $this->sendRequest('GET','/resolution');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
    }

    /**
     * testAddSuccess - test adding various Resolutions that should be valid
     * @dataProvider provideSuccessCases
     * @group minimal
     */
    public function testAddSuccess($resolutionName, $resolutionWidth, $resolutionHeight)
    {

        # Loop through any pre-existing resolutions to make sure we're not
        # going to get a clash
        foreach ($this->startResolutions as $tmpRes) {
            if ($tmpRes->resolution == $resolutionName) {
                $this->skipTest("There is a pre-existing resolution with this name");
                return;
            }
        }
        # Create new resolutions with data from provideSuccessCases
        $response = $this->sendRequest('POST','/resolution', [
            'resolution' => $resolutionName,
            'width' => $resolutionWidth,
            'height' => $resolutionHeight
        ]);
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($resolutionName, $object->data->resolution);
        $this->assertSame($resolutionWidth, $object->data->width);
        $this->assertSame($resolutionHeight, $object->data->height);
        # Check that the resolution was added correctly
        $resolution = (new XiboResolution($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($resolutionName, $resolution->resolution);
        $this->assertSame($resolutionWidth, $resolution->width);
        $this->assertSame($resolutionHeight, $resolution->height);
        # Clean up the Resolutions as we no longer need it
        $this->assertTrue($resolution->delete(), 'Unable to delete ' . $resolution->resolutionId);
    }

    /**
     * Each array is a test run
     * Format (resolution name, width, height)
     * @return array
     */

        public function provideSuccessCases()
    {
        # Sets of correct data, which should be successfully added
        return [
            'resolution 1' => ['test resolution', 800, 200],
            'resolution 2' => ['different test resolution', 1069, 1699]
        ];
    }

    /**
     * testAddFailure - test adding various resolutions that should be invalid
     * @dataProvider provideFailureCases
     */
    public function testAddFailure($resolutionName, $resolutionWidth, $resolutionHeight)
    {
        # create new resolution with data from provideFailureCases
        $response = $this->sendRequest('POST','/resolution', [
            'resolution' => $resolutionName,
            'width' => $resolutionWidth,
            'height' => $resolutionHeight
        ]);
        # Check if it fails as expected
        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode());
    }

    /**
     * Each array is a test run
     * Format (resolution name, width, height)
     * @return array
     */
    public function provideFailureCases()
    {
        # Sets of incorrect data, which should lead to a failure
        return [
            'incorrect width and height' => ['wrong parameters', 'abc', NULL],
            'incorrect width' => [12, 'width', 1699]
        ];
    }

    /**
     * Edit an existing resolution
     * @group minimal
     */
    public function testEdit()
    {
        # Load in a known resolution
        /** @var XiboResolution $resolution */
        $resolution = (new XiboResolution($this->getEntityProvider()))->create('phpunit resolution', 1200, 860);
        $newWidth = 2400;
        # Change the resolution name, width and enable flag
        $name = Random::generateString(8, 'phpunit');

        $response = $this->sendRequest('PUT','/resolution/' . $resolution->resolutionId, [
            'resolution' => $name,
            'width' => $newWidth,
            'height' => $resolution->height,
            'enabled' => 0
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
       
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
        $object = json_decode($response->getBody());
        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->resolution);
        $this->assertSame($newWidth, $object->data->width);
        $this->assertSame(0, $object->data->enabled);
        # Check that the resolution was actually renamed
        $resolution = (new XiboResolution($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $resolution->resolution);
        $this->assertSame($newWidth, $resolution->width);
        # Clean up the resolution as we no longer need it
        $resolution->delete();
    }

    /**
     * Test delete
     * @group minimal
     */
    public function testDelete()
    {
        # Generate two random names
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known resolutions
        $res1 = (new XiboResolution($this->getEntityProvider()))->create($name1, 1000, 500);
        $res2 = (new XiboResolution($this->getEntityProvider()))->create($name2, 2000, 760);
        # Delete the one we created last
        $response = $this->sendRequest('DELETE','/resolution/' . $res2->resolutionId);
        # This should return 204 for success
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Check only one remains
        $resolutions = (new XiboResolution($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startResolutions) + 1, count($resolutions));
        $flag = false;
        foreach ($resolutions as $res) {
            if ($res->resolutionId == $res1->resolutionId) {
                $flag = true;
            }
        }
        $this->assertTrue($flag, 'Resolution ID ' . $res1->resolutionId . ' was not found after deleting a different Resolution');
        # Clean up
        $res1->delete();
    }
}
