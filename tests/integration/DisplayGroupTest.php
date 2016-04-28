<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroupTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class DisplayGroupTest
 * @package Xibo\Tests\Integration
 */
class DisplayGroupTest extends LocalWebTestCase
{
    protected $startDisplayGroups;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();
        $this->startDisplayGroups = (new XiboDisplayGroup($this->getEntityProvider()))->get();
    }

    /**
     * assertPreConditions
     */
    public function assertPreConditions()
    {
        $this->assertEquals($this->startDisplayGroups, array(), 'There are ' . count($this->startDisplayGroups) . ' in $this->startDisplayGroups. Content: ' . json_encode($this->startDisplayGroups));
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        $finalDisplayGroups = (new XiboDisplayGroup($this->getEntityProvider()))->get(['start' => 0, 'length' => 1000]);

        # Loop over any remaining display groups and nuke them
        foreach ($finalDisplayGroups as $displayGroup) {
            /** @var XiboDisplayGroup $displayGroup */
            try {
                $displayGroup->delete();
            } catch (\Exception $e) {
                fwrite(STDERR, 'Unable to delete ' . $displayGroup->displayGroupId . '. E:' . $e->getMessage());
            }
        }
        parent::tearDown();
    }

    /**
     *  List all display groups known empty
     *  @group minimal
     */
    public function testListEmpty()
    {
        $this->client->get('/displaygroup');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        # There should be no DisplayGroups in the system
        $this->assertEquals(0, $object->data->recordsTotal);
    }

    /**
     * testAddSuccess - test adding various Display Groups that should be valid
     * @dataProvider provideSuccessCases
     * @group minimal
     * @depends testListEmpty
     */
    public function testAddSuccess($groupName, $groupDescription, $isDynamic, $expectedDynamic, $dynamicCriteria, $expectedDynamicCriteria)
    {
        $response = $this->client->post('/displaygroup', [
            'displayGroup' => $groupName,
            'description' => $groupDescription,
            'isDynamic' => $isDynamic,
            'dynamicCriteria' => $dynamicCriteria
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($groupName, $object->data->displayGroup);
        $this->assertSame($groupDescription, $object->data->description);
        $this->assertSame($expectedDynamic, $object->data->isDynamic);
        $this->assertSame($expectedDynamicCriteria, $object->data->dynamicCriteria);

        # Check that the group was really added
        $displayGroups = (new XiboDisplayGroup($this->getEntityProvider()))->get();
        $this->assertEquals(1, count($displayGroups));

        # Check that the group was added correctly
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->getById($object->id);

        $this->assertSame($groupName, $displayGroup->displayGroup);
        $this->assertSame($groupDescription, $displayGroup->description);
        $this->assertSame(strval($expectedDynamic), $displayGroup->isDynamic);
        $this->assertSame($expectedDynamicCriteria, $displayGroup->dynamicCriteria);

        # Clean up the DisplayGroup as we no longer need it
        $this->assertTrue($displayGroup->delete(), 'Unable to delete ' . $displayGroup->displayGroupId);
    }

    /**
     * testAddFailure - test adding various Display Groups that should be invalid
     * @dataProvider provideFailureCases
     * @group minimal
     * @depends testListEmpty
     */
    public function testAddFailure($groupName, $groupDescription, $isDynamic, $dynamicCriteria)
    {
        try {
            $response = $this->client->post('/displaygroup', [
                'displayGroup' => $groupName,
                'description' => $groupDescription,
                'isDynamic' => $isDynamic,
                'dynamicCriteria' => $dynamicCriteria
            ]);
        }
        catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
            ob_get_clean();
            ob_get_clean();
            return;
        }
        
        $this->fail('InvalidArgumentException not raised');
    }
    
    /**
     *  List all display groups known set
     *  @group minimal
     *  @depends testAddSuccess
     */
    public function testListKnown()
    {
        # Load in a known set of display groups
        # We can assume this works since we depend upon the test which
        # has previously added and removed these without issue:
        $cases =  $this->provideSuccessCases();
        
        foreach ($cases as $case) {
            (new XiboDisplayGroup($this->getEntityProvider()))->create($case[0],$case[1],$case[2],$case[3]);
        }
    
        $this->client->get('/displaygroup');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        # There should be count($cases) DisplayGroups in the system
        $this->assertEquals(count($cases), $object->data->recordsTotal);
        
        # Rely on tearDown() to clean up after us
    }

    /**
     * List specific display groups
     * @group minimal
     * @depends testListKnown
     * @dataProvider provideSuccessCases
     */
    public function testListFilter($groupName, $groupDescription, $isDynamic, $expectedDynamic, $dynamicCriteria, $expectedDynamicCriteria)
    {
        # Load in a known set of display groups
        # We can assume this works since we depend upon the test which
        # has previously added and removed these without issue:
        $cases =  $this->provideSuccessCases();
        
        foreach ($cases as $case) {
            (new XiboDisplayGroup($this->getEntityProvider()))->create($case[0], $case[1], $case[2], $case[3]);
        }
        
        $this->client->get('/displaygroup', [
                           'displayGroup' => $groupName
                           ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        # There should be count($cases) DisplayGroups in the system
        $this->assertGreaterThanOrEqual(1, $object->data->recordsTotal);

        $flag = false;
        # Check that for the records returned, $groupName is in the groups names
        foreach ($object->data->data as $group) {
            if (strpos($groupName, $group->displayGroup) == 0) {
                $flag = true;
            }
        }
        
        $this->assertTrue($flag, 'Search term not found');        
        # Rely on tearDown() to clean up after us
    }


    /**
     * Each array is a test run
     *  Format
     *  (Group Name, Group Description, isDynamic, Returned isDynamic (0 or 1),
     *   Criteria for Dynamic group, Returned Criteria for Dynamic group)
     *  For example, if you set isDynamic to 0 and send criteria, it will come back
     *  with criteria = null
     *  These are reused in other tests so please ensure Group Name is unique
     *  through the dataset
     * @return array
     */
    public function provideSuccessCases()
    {

        return [
            // Multi-language non-dynamic groups
            ['phpunit test group', 'Api', 0, 0, '', null],
            ['Test de Français 1', 'Bienvenue à la suite de tests Xibo', 0, 0, null, null],
            ['Deutsch Prüfung 1', 'Weiß mit schwarzem Text', 0, 0, null, null],
            // Multi-language dynamic groups
            ['phpunit test dynamic group', 'Api', 1, 1, 'test', 'test'],
            ['Test de Français 2', 'Bienvenue à la suite de tests Xibo', 1, 1, 'test', 'test'],
            ['Deutsch Prüfung 2', 'Weiß mit schwarzem Text', 1, 1, 'test', 'test'],
            // Tests for the various allowed values for isDynamic = 1
            ['phpunit group dynamic is on', 'Api', 'on', 1, 'test', 'test'],
            ['phpunit group dynamic is true', 'Api', 'true', 1, 'test', 'test'],
            // Invalid isDynamic flag (the CMS sanitises these for us to false)
            ['Invalid isDynamic flag 1', 'Invalid isDynamic flag', 7, 0, null, null],
            ['Invalid isDynamic flag 2 ', 'Invalid isDynamic flag', 7, 0, 'criteria', 'criteria'],
            ['Invalid isDynamic flag alpha 1', 'Invalid isDynamic flag alpha', 'invalid', 0, null, null],
            ['Invalid isDynamic flag alpha 2', 'Invalid isDynamic flag alpha', 'invalid', 0, 'criteria', 'criteria']
        ];
    }

    /**
     *  Each array is a test run
     *  Format
     *  (Group Name, Group Description, isDynamic, Criteria for Dynamic group)
     * @return array
     */
    public function provideFailureCases()
    {

        return [
            // Description is limited to 255 characters
            ['Too long description', Random::generateString(255), 0, null],
            // If isDynamic = 1 then criteria must be set
            ['No dynamic criteria', 'No dynamic criteria', 1, null],
            // Group name is empty
            ['', 'Group name is empty', 0, null],
            // Group name is null
            [null, 'Group name is null', 0, null]
        ];
    }
    
    /**
     *  Try and add two display groups with the same name
     *  @group minimal
     *  @depends testAddSuccess
     */
    public function testAddDuplicate()
    {
        # Load in a known display group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit displaygroup', 'phpunit displaygroup', 0, '');
    
        try {
            $response = $this->client->post('/displaygroup', [
                'displayGroup' => 'phpunit displaygroup',
                'description' => 'phpunit displaygroup',
                'isDynamic' => 0,
                'dynamicCriteria' => ''
            ]);
        }
        catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
            ob_get_clean();
            ob_get_clean();
            $displayGroup->delete();
            return;
        }
        
        $this->fail('InvalidArgumentException not thown as expected');
    
    }
    
   /**
    * Edit an existing display group
    * @depends testAddSuccess
    * @group minimal
    */
    public function testEdit()
    {
    
        # Load in a known layout
        /** @var XiboDisplayGroup $displayGroup */
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit displaygroup', 'phpunit displaygroup', 0, '');

        # Change the group name and description
        # Change it to a dynamic group with a fixed criteria
        $name = Random::generateString(8, 'phpunit');
        $description = Random::generateString(8, 'description');
        $criteria = 'test';

        $this->client->put('/displaygroup/' . $displayGroup->displayGroupId, [
            'displayGroup' => $name,
            'description' => $description,
            'isDynamic' => 1,
            'dynamicCriteria' => $criteria
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());

        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->displayGroup);
        $this->assertSame($description, $object->data->description);
        $this->assertSame(1, $object->data->isDynamic);
        $this->assertSame($criteria, $object->data->dynamicCriteria);

        # Check that the group was actually renamed
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->getById($object->id);
        
        $this->assertSame($name, $displayGroup->displayGroup);
        $this->assertSame($description, $displayGroup->description);
        $this->assertSame('1', $displayGroup->isDynamic);
        $this->assertSame($criteria, $displayGroup->dynamicCriteria);
        
        # Clean up the DisplayGroup as we no longer need it
        $displayGroup->delete();

    }

    /**
     * Test delete
     * @param int $displayGroupId
     * @depends testAddSuccess
     * @group minimal
     */
    public function testDelete($displayGroupId)
    {
        # Load in a couple of known display groups
        $displayGroup1 = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit test', 'phpunit description', 0, '');
        $displayGroup2 = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit test 2', 'phpunit description 2', 0, '');
        
        # Delete the one we created last
        $this->client->delete('/displaygroup/' . $displayGroup2->displayGroupId);

        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        
        # Check only one remains
        $groups = (new XiboDisplayGroup($this->getEntityProvider()))->get();
        $this->assertEquals(count($groups), 1);
        $this->assertSame($groups[0]->displayGroupId, $displayGroup1->displayGroupId);
        
        $displayGroup1->delete();
    }

    /**
     *Assign new displays Test
     * @group broken
     */
    public function testAssign()
    {

		$this->client->post('/displaygroup/' . 7 . '/display/assign', [
        'displayId' => [7]
        ]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
    }

    /**
     * Unassign displays Test
     * @group broken
     */
    public function testUnassign()
    {

        $this->client->post('/displaygroup/' . 7 . '/display/unassign', [
        'displayId' => [7]
        ]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
    }

    /**
     * Assign new display group Test
     * @group broken
     */
    public function testAssignGroup()
    {

		$this->client->post('/displaygroup/' . 7 . '/displayGroup/assign', [
        'displayGroupId' => [29]
        ]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
     * Unassign displays group Test
     * @group broken
     */
    public function testUnassignGroup()
    {

		$this->client->post('/displaygroup/' . 7 . '/displayGroup/unassign', [
        	'displayGroupId' => [29]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
     * Assign new media file to a group Test
     * @group broken
     */
    public function testAssignMedia()
    {

		$this->client->post('/displaygroup/' . 7 . '/media/assign', [
        	'mediaId' => [13, 17],
        	'unassignMediaId' => [13]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
     * Unassign media files from a group Test
     * @group broken
     */
    public function testUnassignMedia()
    {

        $this->client->post('/displaygroup/' . 7 . '/media/unassign', [
        	'mediaId' => [17]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
     * Assign new layouts to a group Test
     * @group broken
     */
    public function testAssignLayout()
    {

        $this->client->post('/displaygroup/' . 7 . '/layout/assign', [
        	'layoutId' => [51, 63],
        	'unassignLayoutsId' => [51]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
     * Unassign layouts from a group Test     
     *  does not work, method name differences between /routes and controller/displayGroup
     * @group broken
     */
    public function testUnassignLayout()
    {

		$this->client->post('/displaygroup/' . 7 . '/layout/unassign', [
        	'layoutId' => [63]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
     * Assign apk version to a group Test
     * @group broken
     */
    public function testVersion()
    {

        $this->client->post('/displaygroup/' . 7 . '/version', [
        	'mediaId' => 18
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
     * Collect now action test
     * @group broken
     */
    public function testCollect()
    {

        $this->client->post('/displaygroup/' . 7 . '/action/collectNow');

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

    }

    /**
     * Change Layout action test
     * @group broken
     */
    public function testChange()
    {

        $this->client->post('/displaygroup/' . 7 . '/action/changeLayout', [
		'layoutId' => 3,
		'duration' => 900,  
		'downloadRequired' => 1,
		'changeMode' => 'queue'
    	]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

    }

    /**
     * Revert to Schedule action test
     * @group broken
     */
    public function testRevert()
    {

        $this->client->post('/displaygroup/' . 7 . '/action/revertToSchedule');

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

    }
    
    /**
     * Send command action test
     * @group broken
     */
    public function testCommand()
    {

        $this->client->post('/displaygroup/' . 7 . '/action/command' , [
		'commandId' => 5
        	]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

    }
}
