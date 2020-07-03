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
use Xibo\OAuth2\Client\Entity\XiboCommand;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class DisplayGroupTest
 * @package Xibo\Tests\Integration
 */
class DisplayGroupTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    protected $startDisplayGroups;
    protected $startDisplays;
    protected $startLayouts;
    protected $startCommands;

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
        $response = $this->sendRequest('GET','/displaygroup');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
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

        $response = $this->sendRequest('POST','/displaygroup', [
            'displayGroup' => $groupName,
            'description' => $groupDescription,
            'isDynamic' => $isDynamic,
            'dynamicCriteria' => $dynamicCriteria
        ]);

        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($groupName, $object->data->displayGroup);
        $this->assertSame($groupDescription, $object->data->description);
        $this->assertSame($expectedDynamic, $object->data->isDynamic);
        $this->assertSame($expectedDynamicCriteria, $object->data->dynamicCriteria);
        # Check that the group was really added
        $displayGroups = (new XiboDisplayGroup($this->getEntityProvider()))->get(['length' => 1000]);
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
        $response = $this->sendRequest('POST','/displaygroup', [
            'displayGroup' => $groupName,
            'description' => $groupDescription,
            'isDynamic' => $isDynamic,
            'dynamicCriteria' => $dynamicCriteria
        ]);

        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode());
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

        $response = $this->sendRequest('GET','/displaygroup');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
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
        $response = $this->sendRequest('GET','/displaygroup', [
            'displayGroup' => $groupName
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
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
            'English 1' => ['phpunit test group', 'Api', 0, 0, null, null],
            'English 2' => ['another phpunit test group', 'Api', 0, 0, null, null],
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

        $response = $this->sendRequest('POST','/displaygroup', [
            'displayGroup' => 'phpunit displaygroup',
            'description' => 'phpunit displaygroup',
            'isDynamic' => 0,
            'dynamicCriteria' => ''
        ]);

        $this->assertSame(409, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode());
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
        # Load in a known display group
        /** @var XiboDisplayGroup $displayGroup */
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit displaygroup', 'phpunit displaygroup', 0, '');
        # Change the group name and description
        # Change it to a dynamic group with a fixed criteria
        $name = Random::generateString(8, 'phpunit');
        $description = Random::generateString(8, 'description');
        $criteria = 'test';

        $response = $this->sendRequest('PUT','/displaygroup/' . $displayGroup->displayGroupId, [
            'displayGroup' => $name,
            'description' => $description,
            'isDynamic' => 1,
            'dynamicCriteria' => $criteria
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
        $object = json_decode($response->getBody());
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
        $response = $this->sendRequest('DELETE','/displaygroup/' . $displayGroup2->displayGroupId);
        # This should return 204 for success
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
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
        # Clean up
        $displayGroup1->delete();
    }

    /**
     * Assign new displays Test
     */
    public function testAssignDisplay()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        # Now find the Id of that Display
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
        # Create a DisplayGroup to add the display to
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        # Call assign display to display group
        $response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/display/assign', [
            'displayId' => [$display->displayId]
        ]);
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Get a list of all Displays in the group
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['displayGroupId' => $displayGroup->displayGroupId]);
        # Check that there's only us in that group
        $this->assertEquals(1, count($displays));
        $this->assertEquals($display->displayId, $displays[0]->displayId);
        # Clean up
        $displayGroup->delete();
        $display->delete();
    }

    /**
     * Try to assign display to isDisplaySpecific displayGroupId
     */
    public function testAssignDisplayFailure()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        # Now find the Id of that Display
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

        # Call assign display to display specific display group
        $response = $this->sendRequest('POST','/displaygroup/' . $display->displayGroupId . '/display/assign', [
            'displayId' => [$display->displayId]
        ]);

        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode());
    }

    /**
     * Unassign displays Test
     */
    public function testUnassignDisplay()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        # Now find the Id of that Display
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

        # Create display group
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        # Assign display to display group
        $displayGroup->assignDisplay([$display->displayId]);
        # Unassign display from display group
        $response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/display/unassign', [
            'displayId' => [$display->displayId]
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Clean up
        $displayGroup->delete();
        $display->delete();
    }

    /**
     * Try to unassign display from isDisplaySpecific displayGroupId
     */
    public function testUnassignDisplayFailure()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        # Now find the Id of that Display
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

        # Create display group
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        # Assign display to display group - should be successful
        $displayGroup->assignDisplay([$display->displayId]);
        # Unassign display from isDisplaySpecific display group - should fail
        $response = $this->sendRequest('POST','/displaygroup/' . $display->displayGroupId . '/display/unassign', [
            'displayId' => [$display->displayId]
        ]);
        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode());
    }

    /**
     * Assign new display group Test
     */
    public function testAssignGroup()
    {
        # Generate new random names
        $name = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Create new display group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        $displayGroup2 = (new XiboDisplayGroup($this->getEntityProvider()))->create($name2, 'phpunit description', 0, '');
        # Assign second display group to the first one
		$response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/displayGroup/assign', [
		    'displayGroupId' => [$displayGroup2->displayGroupId]
        ]);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up
        $displayGroup->delete();
        $displayGroup2->delete();
    }

    /**
     * Unassign displays group Test
     */
    public function testUnassignGroup()
    {
        # Generate new random names
        $name = Random::generateString(8, 'PARENT');
        $name2 = Random::generateString(8, 'CHILD');
        # Create new display groups
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        $displayGroup2 = (new XiboDisplayGroup($this->getEntityProvider()))->create($name2, 'phpunit description', 0, '');
		# Assign second display group to the first one

        $displayGroup->assignDisplayGroup([$displayGroup2->displayGroupId]);
        # Unassign second display group from the first one
        $response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/displayGroup/unassign', [
            'displayGroupId' => [$displayGroup2->displayGroupId]
        ]);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up
        $displayGroup->delete();
        $displayGroup2->delete();
    }

    /**
     * Assign new media file to a group Test
     */
    public function testAssignMedia()
    {
        # Generate new random name
        $name = Random::generateString(8, 'phpunit');
        # Create new display group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        # Upload a known files
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video 12', PROJECT_ROOT . '/tests/resources/HLH264.mp4');
        $media2 = (new XiboLibrary($this->getEntityProvider()))->create('API image 12', PROJECT_ROOT . '/tests/resources/xts-night-001.jpg');
        # Assign two files o the display group and unassign one of them
		$response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/media/assign', [
        	'mediaId' => [$media->mediaId, $media2->mediaId],
        	'unassignMediaId' => [$media2->mediaId]
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up
        $displayGroup->delete();
        $media->delete();
        $media2->delete();
    }

    /**
     * Unassign media files from a group Test
     */
    public function testUnassignMedia()
    {
        # Generate new random name
        $name = Random::generateString(8, 'phpunit');
        # Create new display group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        # Upload a known file
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API image 29', PROJECT_ROOT . '/tests/resources/xts-night-001.jpg');
        # Assign media to display Group
        $displayGroup->assignMedia([$media->mediaId]);
        # Unassign the media from the display group
        $response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/media/unassign', [
        	'mediaId' => [$media->mediaId]
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up
        $displayGroup->delete();
        $media->delete();
    }

    /**
     * Assign new layouts to a group Test
     */
    public function testAssignLayout()
    {
        # Create new random name
        $name = Random::generateString(8, 'phpunit');

        # Create new display group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');

        # Create new layouts
        $layout = $this->createLayout();
        $layout2 = $this->createLayout();

        # Assign both layouts to display group then unassign the second layout from it
        $response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/layout/assign', [
        	'layoutId' => [$layout->layoutId, $layout2->layoutId],
        	'unassignLayoutsId' => [$layout2->layoutId]
        ]);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up
        $displayGroup->delete();
        $layout->delete();
        $layout2->delete();
    }

    /**
     * Unassign layouts from a group Test
     */
    public function testUnassignLayout()
    {
        # Create new random name
        $name = Random::generateString(8, 'phpunit');
        # Create new display group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');

        # Create new layout
        $layout = $this->createLayout();

        # assign layout to display group
        $displayGroup->assignLayout([$layout->layoutId]);
		# unassign layout from display group
        $response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/layout/unassign', [
            'layoutId' => [$layout->layoutId]
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up
        $displayGroup->delete();
        $layout->delete();
    }

    /**
     * Collect now action test
     */
    public function testCollectNow()
    {
        # Generate random name
        $name = Random::generateString(8, 'phpunit');
        # Create new display group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        # Call callectNow
        $response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/action/collectNow');
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up
        $displayGroup->delete();
    }

    /**
     * Change Layout action test
     */
    public function testChangeLayout()
    {
        # Generate random name
        $name = Random::generateString(8, 'phpunit');
        # Create new display group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');

        # Create new layout
        $layout = $this->createLayout();

        # Call changeLayout
        $response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/action/changeLayout', [
            'layoutId' => $layout->layoutId,
            'duration' => 900,
            'downloadRequired' => 1,
            'changeMode' => 'queue'
    	]);
        # Check if successful
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up
        $displayGroup->delete();
        $layout->delete();
    }

    /**
     * Revert to Schedule action test
     */
    public function testRevertToSchedule()
    {
        # Generate random name and create new display group
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        # Call RevertToSchedule
        $response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/action/revertToSchedule');
        # Check if successful
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up
        $displayGroup->delete();
    }

    /**
     * Send command action test
     */
    public function testCommand()
    {
        # Generate random name and create new display group
        $name = Random::generateString(8, 'phpunit');
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create($name, 'phpunit description', 0, '');
        # Create new command
        $command = (new XiboCommand($this->getEntityProvider()))->create('phpunit command', 'phpunit description', 'phpunitcode');
        # Send command to display group
        $response = $this->sendRequest('POST','/displaygroup/' . $displayGroup->displayGroupId . '/action/command' , [
		    'commandId' => $command->commandId
        ]);
        # Check if successful
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
        # Clean up
        $displayGroup->delete();
        $command->delete();
    }
}
