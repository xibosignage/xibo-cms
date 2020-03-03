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
use Xibo\OAuth2\Client\Entity\XiboDaypart;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class DaypartTest
 * @package Xibo\Tests\Integration
 */

class DaypartTest extends LocalWebTestCase
{
    /** @var XiboDaypart[] */
	protected $startDayparts;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();
        $this->startDayparts = (new XiboDaypart($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);

        $this->getLogger()->debug('There are ' . count($this->startDayparts) . ' dayparts at the start of the test');
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all dayparts that weren't there initially
        $finalDayparts = (new XiboDaypart($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining dayparts and nuke them
        foreach ($finalDayparts as $daypart) {
            /** @var XiboDaypart $daypart */
            $flag = true;
            foreach ($this->startDayparts as $startDaypart) {
               if ($startDaypart->dayPartId == $daypart->dayPartId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $daypart->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $daypart->dayPartId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

	/**
     * testAddSuccess - test adding various daypart that should be valid
     * @dataProvider provideSuccessCases
     */
    public function testAddSuccess($name, $description, $startTime, $endTime, $exceptionDays,  $exceptionStartTimes, $exceptionEndTimes)
    {
        # Create daypart with arguments from provideSuccessCases
        $response = $this->sendRequest('POST','/daypart', [
			'name' => $name,
			'description' => $description,
			'startTime' => $startTime,
			'endTime' => $endTime,
			'exceptionDays' => $exceptionDays,
			'exceptionStartTimes' => $exceptionStartTimes,
			'exceptionEndTimes' => $exceptionEndTimes
        ]);
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame($description, $object->data->description);
        # Check that the daypart was really added
        $dayparts = (new XiboDaypart($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->assertEquals(count($this->startDayparts) + 1, count($dayparts));
        # Check that the daypart was added correctly
        $daypart = (new XiboDaypart($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $daypart->name);
        $this->assertSame($description, $daypart->description);
        # Clean up the daypart as we no longer need it
        $this->assertTrue($daypart->delete(), 'Unable to delete ' . $daypart->dayPartId);
    }
    
    /**
     * testAddFailure - test adding various daypart that should be invalid
     * @dataProvider provideFailureCases
     */
    public function testAddFailure($name, $description, $startTime, $endTime, $exceptionDays,  $exceptionStartTimes, $exceptionEndTimes)
    {
        # Create daypart with arguments from provideFailureCases
        $response = $this->sendRequest('POST','/daypart', [
			'name' => $name,
			'description' => $description,
			'startTime' => $startTime,
			'endTime' => $endTime,
			'exceptionDays' => $exceptionDays,
			'exceptionStartTimes' => $exceptionStartTimes,
			'exceptionEndTimes' => $exceptionEndTimes
        ]);
        # check if they fail as expected
        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode());
    }

    /**
     * Each array is a test run
     * Format ($name, $description, $startTime, $endTime, $exceptionDays,  $exceptionStartTimes, $exceptionEndTimes)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Data for testAddSuccess, easily expandable - just add another set of data below
        return [
            'No exceptions' => ['phpunit daypart', 'API', '02:00', '06:00', NULL, NULL, NULL],
            'Except Monday' => ['phpunit daypart exception', NULL, '02:00', '06:00', ['Monday'], ['00:01'], ['23:59']]
        ];
    }
    /**
     * Each array is a test run
     * Format ($name, $description, $startTime, $endTime, $exceptionDays,  $exceptionStartTimes, $exceptionEndTimes)
     * @return array
     */
    public function provideFailureCases()
    {
        # Data for testAddfailure, easily expandable - just add another set of data below
        // TODO we should probably validate description and day names in daypart Controller.
        return [
            'Empty title' => [NULL, 'should be invalid', '07:00', '10:00', NULL, NULL, NULL],
            //'Description over 254 characters' => ['Too long description', Random::generateString(258), '07:00', '10:00', NULL, NULL, NULL],
            'Wrong time data type' => ['Time as integer','should be incorrect', 21, 22, NULL, NULL, NULL],
            //'Wrong day name' => ['phpunit daypart exception', NULL, '02:00', '06:00', ['Cabbage'], ['00:01'], ['23:59']]
        ];
    }

    /**
    * Edit an existing daypart
    */
    public function testEdit()
    {
        #Create new daypart
        $daypart = (new XiboDaypart($this->getEntityProvider()))->create('phpunit daypart', 'API', '02:00', '06:00', NULL, NULL, NULL);
        # Change the daypart name and description
        $name = Random::generateString(8, 'phpunit');
        $description = Random::generateString(8, 'description');
        $response = $this->sendRequest('PUT','/daypart/' . $daypart->dayPartId, [
            'name' => $name,
            'description' => $description,
            'startTime' => '02:00',
            'endTime' => '06:00',
			'exceptionDays' => $daypart->exceptionDays,
			'exceptionStartTimes' => $daypart->exceptionStartTimes,
			'exceptionEndTimes' => $daypart->exceptionEndTimes
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
        $object = json_decode($response->getBody());
        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame($description, $object->data->description);
        # Check that the daypart was actually renamed
        $daypart = (new XiboDaypart($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $daypart->name);
        $this->assertSame($description, $daypart->description);
        # Clean up the Daypart as we no longer need it
        $daypart->delete();
    }

     /**
     * Test delete
     * @group minimal
     */
    public function testDelete()
    {
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known Dayparts
        $daypart1 = (new XiboDaypart($this->getEntityProvider()))->create($name1, 'API', '02:00', '06:00', NULL, NULL, NULL);
        $daypart2 = (new XiboDaypart($this->getEntityProvider()))->create($name2, 'API', '12:00', '16:00', NULL, NULL, NULL);
        # Delete the one we created last
        $response = $this->sendRequest('DELETE','/daypart/' . $daypart2->dayPartId);
        # This should return 204 for success
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Check only one remains
        $dayparts = (new XiboDaypart($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->assertEquals(count($this->startDayparts) + 1, count($dayparts));
        $flag = false;
        foreach ($dayparts as $daypart) {
            if ($daypart->dayPartId == $daypart1->dayPartId) {
                $flag = true;
            }
        }
        $this->assertTrue($flag, 'Daypart ID ' . $daypart1->dayPartId . ' was not found after deleting a different daypart');
        $daypart1->delete();
    }
}
