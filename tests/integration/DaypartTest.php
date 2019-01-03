<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DaypartTest.php)
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
                    fwrite(STDERR, 'Unable to delete Daypart ID ' . $daypart->dayPartId . '. E:' . $e->getMessage());
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
        $response = $this->client->post('/daypart', [
			'name' => $name,
			'description' => $description,
			'startTime' => $startTime,
			'endTime' => $endTime,
			'exceptionDays' => $exceptionDays,
			'exceptionStartTimes' => $exceptionStartTimes,
			'exceptionEndTimes' => $exceptionEndTimes
        ]);
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
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
    }
    
    /**
     * testAddFailure - test adding various daypart that should be invalid
     * @dataProvider provideFailureCases
     */
    public function testAddFailure($name, $description, $startTime, $endTime, $exceptionDays,  $exceptionStartTimes, $exceptionEndTimes)
    {
        # Create daypart with arguments from provideFailureCases
        $response = $this->client->post('/daypart', [
			'name' => $name,
			'description' => $description,
			'startTime' => $startTime,
			'endTime' => $endTime,
			'exceptionDays' => $exceptionDays,
			'exceptionStartTimes' => $exceptionStartTimes,
			'exceptionEndTimes' => $exceptionEndTimes
        ]);
        # check if they fail as expected
        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
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
        return [
            'Empty title' => [NULL, 'should be invalid', '07:00', '10:00', NULL, NULL, NULL],
           // 'Description over 254 characters' => ['Too long description', Random::generateString(255), '07:00', '10:00', NULL, NULL, NULL],
           // 'Wrong time data type' => ['Time as integer','should be incorrect', 21, 22, NULL, NULL, NULL],
           // 'Wrong day name' => ['phpunit daypart exception', NULL, '02:00', '06:00', ['Cabbage'], ['00:01'], ['23:59']]
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
        $this->client->put('/daypart/' . $daypart->dayPartId, [
            'name' => $name,
            'description' => $description,
            'startTime' => '02:00',
            'endTime' => '06:00',
			'exceptionDays' => $daypart->exceptionDays,
			'exceptionStartTimes' => $daypart->exceptionStartTimes,
			'exceptionEndTimes' => $daypart->exceptionEndTimes
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame($description, $object->data->description);
        # Check that the daypart was actually renamed
        $daypart = (new XiboDaypart($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $daypart->name);
        $this->assertSame($description, $daypart->description);
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
        $this->client->delete('/daypart/' . $daypart2->dayPartId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
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
    }
}
