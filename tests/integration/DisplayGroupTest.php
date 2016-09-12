<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroupTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboCommand;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class DisplayGroupTest
 * @package Xibo\Tests\Integration
 */
class DisplayGroupTest extends LocalWebTestCase
{
    protected $startDisplayGroups;
    protected $startDisplays;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startDisplayGroups = (new XiboDisplayGroup($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startDisplays = (new XiboDisplay($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startCommands = (new XiboCommand($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all display groups that weren't there initially
        $finalDisplayGroups = (new XiboDisplayGroup($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);

        # Loop over any remaining display groups and nuke them
        foreach ($finalDisplayGroups as $displayGroup) {
            /** @var XiboDisplayGroup $displayGroup */

            $flag = true;

            foreach ($this->startDisplayGroups as $startGroup) {
               if ($startGroup->displayGroupId == $displayGroup->displayGroupId) {
                   $flag = false;
               }
            }

            if ($flag) {
                try {
                    $displayGroup->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $displayGroup->displayGroupId . '. E:' . $e->getMessage());
                }
            }
        }

        // Tear down any displays that weren't there before
        $finalDisplays = (new XiboDisplay($this->getEntityProvider()))->get();
        
        # Loop over any remaining displays and nuke them
        foreach ($finalDisplays as $display) {
            /** @var XiboDisplay $display */

            $flag = true;

            foreach ($this->startDisplays as $startDisplay) {
               if ($startDisplay->displayId == $display->displayId) {
                   $flag = false;
               }
            }

            if ($flag) {
                try {
                    $display->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $display->displayId . '. E:' . $e->getMessage());
                }
            }
        }

        // tearDown all layouts that weren't there initially
        $finalLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining layouts and nuke them
        foreach ($finalLayouts as $layout) {
            /** @var XiboLayout $layout */
            $flag = true;
            foreach ($this->startLayouts as $startLayout) {
               if ($startLayout->layoutId == $layout->layoutId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $layout->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
                }
            }
        }

        // tearDown all commands that weren't there initially
        $finalCommands = (new XiboCommand($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining commands and nuke them
        foreach ($finalCommands as $command) {
            /** @var XiboCommand $command */
            $flag = true;
            foreach ($this->startCommands as $startCom) {
               if ($startCom->commandId == $command->commandId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $command->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $command->commandId . '. E:' . $e->getMessage());
                }
            }
        }

        parent::tearDown();
    }

    /**
     *  List all display groups known empty
     *  @group minimal
     *  @group destructive
     */
    public function testListEmpty()
    {
        if (count($this->startDisplayGroups) > 0) {
            $this->skipTest("There are pre-existing DisplayGroups");
            return;
        }
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
     */
    public function testAddSuccess($groupName, $groupDescription, $isDynamic, $expectedDynamic, $dynamicCriteria, $expectedDynamicCriteria)
    {
        // Loop through any pre-existing DisplayGroups to make sure we're not
        // going to get a clash

        foreach ($this->startDisplayGroups as $tmpGroup) {
            if ($tmpGroup->displayGroup == $groupName) {
                $this->skipTest("There is a pre-existing DisplayGroup with this name");
                return;
            }
        }

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
        $this->assertEquals(count($this->startDisplayGroups) + 1, count($displayGroups));
        # Check that the group was added correctly
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($groupName, $displayGroup->displayGroup);
        $this->assertSame($groupDescription, $displayGroup->description);
        $this->assertSame($expectedDynamic, $displayGroup->isDynamic);
        $this->assertSame($expectedDynamicCriteria, $displayGroup->dynamicCriteria);
        # Clean up the DisplayGroup as we no longer need it
        $this->assertTrue($displayGroup->delete(), 'Unable to delete ' . $displayGroup->displayGroupId);
    }

    /**
     * testAddFailure - test adding various Display Groups that should be invalid
     * @dataProvider provideFailureCases
     * @group minimal
     */
    public function testAddFailure($groupName, $groupDescription, $isDynamic, $dynamicCriteria)
    {
        $response = $this->client->post('/displaygroup', [
            'displayGroup' => $groupName,
            'description' => $groupDescription,
            'isDynamic' => $isDynamic,
            'dynamicCriteria' => $dynamicCriteria
        ]);

        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
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
        $displayGroups = [];
        // Check each possible case to ensure it's not pre-existing
        // If it is, skip over it
        foreach ($cases as $case) {
            $flag = true;

            foreach ($this->startDisplayGroups as $tmpGroup) {
                if ($case[0] == $tmpGroup->displayGroup) {
                    $flag = false;
                }
            }

            if ($flag) {
                $displayGroups[] = (new XiboDisplayGroup($this->getEntityProvider()))->create($case[0],$case[1],$case[2],$case[3]);
            }
        }

        $this->client->get('/displaygroup');
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        # There should be as many groups as we created plus the number we started with in the system
        $this->assertEquals(count($displayGroups) + count($this->startDisplayGroups), $object->data->recordsTotal);
        # Clean up the groups we created
        foreach ($displayGroups as $group) {
            $group->delete();
        }
    }

    /**
     * List specific display groups
     * @group minimal
     * @group destructive
     * @depends testListKnown
     * @depends testAddSuccess
     * @dataProvider provideSuccessCases
     */
    public function testListFilter($groupName, $groupDescription, $isDynamic, $expectedDynamic, $dynamicCriteria, $expectedDynamicCriteria)
    {
        if (count($this->startDisplayGroups) > 0) {
            $this->skipTest("There are pre-existing DisplayGroups");
            return;
        }
        # Load in a known set of display groups
        # We can assume this works since we depend upon the test which
        # has previously added and removed these without issue:
        $cases =  $this->provideSuccessCases();
        $displayGroups = [];
        foreach ($cases as $case) {
            $displayGroups[] = (new XiboDisplayGroup($this->getEntityProvider()))->create($case[0], $case[1], $case[2], $case[3]);
        }
        $this->client->get('/displaygroup', [
                           'displayGroup' => $groupName
                           ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        # There should be at least one match
        $this->assertGreaterThanOrEqual(1, $object->data->recordsTotal);
        $flag = false;
        # Check that for the records returned, $groupName is in the groups names
        foreach ($object->data->data as $group) {
            if (strpos($groupName, $group->displayGroup) == 0) {
                $flag = true;
            }
            else {
                // The object we got wasn't the exact one we searched for
                // Make sure all the words we searched for are in the result
                foreach (array_map('trim',explode(",",$groupName)) as $word) {
                    assertTrue((strpos($word, $group->displayGroup) !== false), 'Group returned did not match the query string: ' . $group->displayGroup);
                }
            }
        }

        $this->assertTrue($flag, 'Search term not found');

        foreach ($displayGroups as $group) {
            $group->delete();
        }
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
            'English 1' => ['phpunit test group', 'Api', 0, 0, '', null],
            'English 2' => ['another phpunit test group', 'Api', 0, 0, '', null],
            'French 1' => ['Test de Français 1', 'Bienvenue à la suite de tests Xibo', 0, 0, null, null],
            'German 1' => ['Deutsch Prüfung 1', 'Weiß mit schwarzem Text', 0, 0, null, null],
            'Simplified Chinese 1' => ['试验组', '测试组描述', 0, 0, null, null],
            // Multi-language dynamic groups
            'English Dynamic 1' => ['phpunit test dynamic group', 'Api', 1, 1, 'test', 'test'],
            'French Dynamic 1' => ['Test de Français 2', 'Bienvenue à la suite de tests Xibo', 1, 1, 'test', 'test'],
            'German Dynamic 1' => ['Deutsch Prüfung 2', 'Weiß mit schwarzem Text', 1, 1, 'test', 'test'],
            // Tests for the various allowed values for isDynamic = 1
            'isDynamic on' => ['phpunit group dynamic is on', 'Api', 'on', 1, 'test', 'test'],
            'isDynamic true' => ['phpunit group dynamic is true', 'Api', 'true', 1, 'test', 'test'],
            // Invalid isDynamic flag (the CMS sanitises these for us to false)
            'isDynamic is 7 null criteria' => ['Invalid isDynamic flag 1', 'Invalid isDynamic flag', 7, 0, null, null],
            'isDynamic is 7 with criteria' => ['Invalid isDynamic flag 2 ', 'Invalid isDynamic flag', 7, 0, 'criteria', 'criteria'],
            'isDynamic is invalid null criteria' => ['Invalid isDynamic flag alpha 1', 'Invalid isDynamic flag alpha', 'invalid', 0, null, null],
            'isDynamic is invalid with criteria' => ['Invalid isDynamic flag alpha 2', 'Invalid isDynamic flag alpha', 'invalid', 0, 'criteria', 'criteria']
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
            'Description over 254 characters' => ['Too long description', Random::generateString(255), 0, null],
            // If isDynamic = 1 then criteria must be set
            'No dynamicCriteria on dynamic group' => ['No dynamic criteria', 'No dynamic criteria', 1, null],
            // Missing group names
            'Group name empty' => ['', 'Group name is empty', 0, null],
            'Group name null' => [null, 'Group name is null', 0, null]
        ];
    }

    /**
     *  Try and add two display groups with the same name
     *  @group minimal
     *  @depends testAddSuccess
     */
    public function testAddDuplicate()
    {
        $flag = true;
        foreach ($this->startDisplayGroups as $group) {
            if ($group->displayGroup == 'phpunit displaygroup') {
                $flag = false;
            }
        }

        # Load in a known display group if it's not there already
        if ($flag) {
            $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit displaygroup', 'phpunit displaygroup', 0, '');
        }

        $response = $this->client->post('/displaygroup', [
            'displayGroup' => 'phpunit displaygroup',
            'description' => 'phpunit displaygroup',
            'isDynamic' => 0,
            'dynamicCriteria' => ''
        ]);

        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
        $displayGroup->delete();
    }

   /**
    * Edit an existing display group
    * @depends testAddSuccess
    * @group minimal
    */
    public function testEdit()
    {
        foreach ($this->startDisplayGroups as $group) {
            if ($group->displayGroup == 'phpunit displaygroup') {
                $this->skipTest('displayGroup already exists with that name');
                return;
            }
        }
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
        $this->assertSame(1, $displayGroup->isDynamic);
        $this->assertSame($criteria, $displayGroup->dynamicCriteria);
        # Clean up the DisplayGroup as we no longer need it
        $displayGroup->delete();
    }

    /**
     * Test delete
     * @depends testAddSuccess
     * @group minimal
     */
    public function testDelete()
    {
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known display groups
        $displayGroup1 = (new XiboDisplayGroup($this->getEntityProvider()))->create($name1, 'phpunit description', 0, '');
        $displayGroup2 = (new XiboDisplayGroup($this->getEntityProvider()))->create($name2, 'phpunit description', 0, '');
        # Delete the one we created last
        $this->client->delete('/displaygroup/' . $displayGroup2->displayGroupId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        # Check only one remains
        $groups = (new XiboDisplayGroup($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startDisplayGroups) + 1, count($groups));

        $flag = false;
        foreach ($groups as $group) {
            if ($group->displayGroupId == $displayGroup1->displayGroupId) {
                $flag = true;
            }
        }

        $this->assertTrue($flag, 'DisplayGroup ID ' . $displayGroup1->displayGroupId . ' was not found after deleting a different DisplayGroup');
        $displayGroup1->delete();
    }

    /**
     * Assign new displays Test
     * @return mixed
     */
    public function testAssign()
    {
        // Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $response = $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        // Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get();
        $display = null;
        
        foreach ($displays as $disp) {
            if ($disp->license == $hardwareId) {
                $display = $disp;
            }
        }
        
        if ($display === null) {
            $this->fail('Display was not added correctly');
        }
        // Create a DisplayGroup to add the display to
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        
        $this->client->post('/displaygroup/' . $displayGroup->displayGroupId . '/display/assign', [
                            'displayId' => [$display->displayId]
                             ]);

        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        // Get a list of all Displays in the group
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['displayGroupId' => $displayGroup->displayGroupId]);
        // Check that there's only us in that group
        $this->assertEquals(1, count($displays));
        $this->assertEquals($display->displayId, $displays[0]->displayId);

        return array ($display->displayId, $displayGroup->displayGroupId);
    }

    /**
     * Unassign displays Test
     * @depends testAssign
     * @param display $displayId
     * @param displayGroup $displayGroupId
     * @group broken
     */
    public function testUnassign($displayId, $displayGroupId)
    {
        $display = (new XiboDisplay($this->getEntityProvider()))->getById($displayId);
        $group = (new XiboDisplayGroup($this->getEntityProvider()))->getById($displayGroupId);

        $this->client->post('/displaygroup/' . $group->displayGroupId . '/display/unassign', [
        'displayId' => [$display->displayId]
        ]);

        $this->assertSame(200, $this->client->response->status());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
    }

    /**
     * Assign new display group Test
     * @return mixed
     */
    public function testAssignGroup()
    {
        $name = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        $displayGroup2 = (new XiboDisplayGroup($this->getEntityProvider()))->create($name2, 'phpunit description', 0, '');

		$this->client->post('/displaygroup/' . $displayGroup->displayGroupId . '/displayGroup/assign', [
        'displayGroupId' => [$displayGroup2->displayGroupId]
        ]);

        $this->assertSame(200, $this->client->response->status());
        $object = json_decode($this->client->response->body());

        return array ($displayGroup->displayGroupId, $displayGroup2->displayGroupId);
    }

    /**
     * Unassign displays group Test
     * @param displayGroup $displayGroupId
     * @param displayGroup2 $displayGroupId
     * @depends testAssignGroup
     * @group broken
     */
    public function testUnassignGroup()
    {
        $group = (new XiboDisplayGroup($this->getEntityProvider()))->getById($displayGroupId);
        $group2 = (new XiboDisplayGroup($this->getEntityProvider()))->getById($displayGroupId);
		$this->client->post('/displaygroup/' . $group->displayGroupId . '/displayGroup/unassign', [
        	'displayGroupId' => [$group2->displayGroupId]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
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
     */
    public function testAssignLayout()
    {
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        $layout = (new XiboLayout($this->getEntityProvider()))->create('test layout', 'test description', '', 9);
        $layout2 = (new XiboLayout($this->getEntityProvider()))->create('test layout 2', 'test description 2', '', 9);

        $this->client->post('/displaygroup/' . $displayGroup->displayGroupId . '/layout/assign', [
        	'layoutId' => [$layout->layoutId, $layout2->layoutId],
        	'unassignLayoutsId' => [$layout2->layoutId]
        	]);

        $this->assertSame(200, $this->client->response->status());
        $object = json_decode($this->client->response->body());
    }

    /**
     * Unassign layouts from a group Test
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
     */
    public function testCollectNow()
    {
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');

        $this->client->post('/displaygroup/' . $displayGroup->displayGroupId . '/action/collectNow');

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        $object = json_decode($this->client->response->body());
    }

    /**
     * Change Layout action test
     */
    public function testChangeLayout()
    {
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        $layout = (new XiboLayout($this->getEntityProvider()))->create('test layout', 'test description', '', 9);

        $this->client->post('/displaygroup/' . $displayGroup->displayGroupId . '/action/changeLayout', [
		'layoutId' => $layout->layoutId,
		'duration' => 900,
		'downloadRequired' => 1,
		'changeMode' => 'queue'
    	]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        $object = json_decode($this->client->response->body());
    }

    /**
     * Revert to Schedule action test
     */
    public function testRevertToSchedule()
    {
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');

        $this->client->post('/displaygroup/' . $displayGroup->displayGroupId . '/action/revertToSchedule');

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        $object = json_decode($this->client->response->body());
    }

    /**
     * Send command action test
     */
    public function testCommand()
    {
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        $command = (new XiboCommand($this->getEntityProvider()))->create('phpunit command', 'phpunit description', 'phpunit code');

        $this->client->post('/displaygroup/' . $displayGroup->displayGroupId . '/action/command' , [
		'commandId' => $command->commandId
        	]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        $object = json_decode($this->client->response->body());
    }
}
