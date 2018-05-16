<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015-2018 Spring Signage Ltd
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
use Xibo\OAuth2\Client\Entity\XiboCampaign;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboResolution;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class LayoutTest
 * @package Xibo\Tests\Integration
 */
class LayoutTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var XiboLayout[] */
    protected $startLayouts;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all layouts that weren't there initially
        $finalLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);

        // Loop over any remaining layouts and nuke them
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
                    $this->getLogger()->error('Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    /**
     * @param $type
     * @return int
     */
    private function getResolutionId($type)
    {
        if ($type === 'landscape') {
            $width = 1920;
            $height = 1080;
        } else if ($type === 'portrait') {
            $width = 1080;
            $height = 1920;
        } else {
            return -10;
        }

        //$this->getLogger()->debug('Querying for ' . $width . ', ' . $height);

        $resolutions = (new XiboResolution($this->getEntityProvider()))->get(['width' => $width, 'height' => $height]);

        if (count($resolutions) <= 0)
            return -10;

        return $resolutions[0]->resolutionId;
    }

    /**
     *  List all layouts known empty
     */
    public function testListEmpty()
    {
        # Check that there is one layout in the database (the 'default layout')
        if (count($this->startLayouts) > 1) {
            $this->skipTest("There are pre-existing Layouts");
            return;
        }
        $this->client->get('/layout');
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        # There should be one default layout in the system
        $this->assertEquals(1, $object->data->recordsTotal);
    }
    
    /**
     * testAddSuccess - test adding various Layouts that should be valid
     * @dataProvider provideSuccessCases
     */
    public function testAddSuccess($layoutName, $layoutDescription, $layoutTemplateId, $layoutResolutionType)
    {
        $layoutResolutionId = $this->getResolutionId($layoutResolutionType);

        # Create layouts with arguments from provideSuccessCases
        $this->client->post('/layout', [
            'name' => $layoutName,
            'description' => $layoutDescription,
            'layoutId' => $layoutTemplateId,
            'resolutionId' => $layoutResolutionId
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $this->client->response->status() . $this->client->response->body());

        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        $this->assertSame($layoutName, $object->data->layout);
        $this->assertSame($layoutDescription, $object->data->description);

        # Check that the layout was really added
        $layouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->assertEquals(count($this->startLayouts) + 1, count($layouts));

        # Check that the layout was added correctly
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($layoutName, $layout->layout);
        $this->assertSame($layoutDescription, $layout->description);

        # Clean up the Layout as we no longer need it
        $this->assertTrue($layout->delete(), 'Unable to delete ' . $layout->layoutId);
    }
    
    /**
     * testAddFailure - test adding various Layouts that should be invalid
     * @dataProvider provideFailureCases
     */
    public function testAddFailure($layoutName, $layoutDescription, $layoutTemplateId, $layoutResolutionType)
    {
        $layoutResolutionId = $this->getResolutionId($layoutResolutionType);

        # Create layouts with arguments from provideFailureCases
        $this->client->post('/layout', [
            'name' => $layoutName,
            'description' => $layoutDescription,
            'layoutId' => $layoutTemplateId,
            'resolutionId' => $layoutResolutionId
        ]);

        # check if they fail as expected
        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
    }

    /**
     *  List all layouts known set
     *  @group minimal
     */
    public function testListKnown()
    {
        $cases =  $this->provideSuccessCases();
        $layouts = [];

        // Check each possible case to ensure it's not pre-existing
        // If it is, skip over it
        foreach ($cases as $case) {
            $flag = true;
            foreach ($this->startLayouts as $tmpLayout) {
                if ($case[0] == $tmpLayout->layout) {
                    $flag = false;
                }
            }
            if ($flag) {
                $layouts[] = (new XiboLayout($this->getEntityProvider()))->create($case[0],$case[1],$case[2],$this->getResolutionId($case[3]));
            }
        }

        $this->client->get('/layout');
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        # There should be as many layouts as we created plus the number we started with in the system
        $this->assertEquals(count($layouts) + count($this->startLayouts), $object->data->recordsTotal);

        # Clean up the Layouts we created
        foreach ($layouts as $lay) {
            $lay->delete();
        }
    }

    /**
     * List specific layouts
     * @group minimal
     * @group destructive
     * @depends testListKnown
     * @depends testAddSuccess
     * @dataProvider provideSuccessCases
     */
    public function testListFilter($layoutName, $layoutDescription, $layoutTemplateId, $layoutResolutionType)
    {
        if (count($this->startLayouts) > 1) {
            $this->skipTest("There are pre-existing Layouts");
            return;
        }

        # Load in a known set of layouts
        # We can assume this works since we depend upon the test which
        # has previously added and removed these without issue:
        $cases =  $this->provideSuccessCases();
        $layouts = [];
        foreach ($cases as $case) {
            $layouts[] = (new XiboLayout($this->getEntityProvider()))->create($case[0], $case[1], $case[2], $this->getResolutionId($case[3]));
        }

        // Fitler for our specific layout
        $this->client->get('/layout', ['name' => $layoutName]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        # There should be at least one match
        $this->assertGreaterThanOrEqual(1, $object->data->recordsTotal);

        $flag = false;
        # Check that for the records returned, $layoutName is in the groups names
        foreach ($object->data->data as $lay) {
            if (strpos($layoutName, $lay->layout) == 0) {
                $flag = true;
            }
            else {
                // The object we got wasn't the exact one we searched for
                // Make sure all the words we searched for are in the result
                foreach (array_map('trim',explode(",",$layoutName)) as $word) {
                    assertTrue((strpos($word, $lay->layout) !== false), 'Layout returned did not match the query string: ' . $lay->layout);
                }
            }
        }

        $this->assertTrue($flag, 'Search term not found');

        // Remove the Layouts we've created
        foreach ($layouts as $lay) {
            $lay->delete();
        }
    }

    /**
     * Each array is a test run
     * Format (LayoutName, description, layoutID (template), resolution ID)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Data for testAddSuccess, easily expandable - just add another set of data below
        return [
            // Multi-language layouts
            'English 1' => ['phpunit test Layout', 'Api', NULL, 'landscape'],
            'French 1' => ['Test de Français 1', 'Bienvenue à la suite de tests Xibo', NULL, 'landscape'],
            'German 1' => ['Deutsch Prüfung 1', 'Weiß mit schwarzem Text', NULL, 'landscape'],
            'Simplified Chinese 1' => ['试验组', '测试组描述', NULL, 'landscape'],
            'Portrait layout' => ['Portrait layout', '1080x1920', '', 'portrait'],
            'No Description' => ['Just the title and resolution', NULL, '', 'portrait'],
            'Just title' => ['Just the name', NULL, NULL, 'portrait']
        ];
    }
    /**
     * Each array is a test run
     * Format (LayoutName, description, layoutID (template), resolution ID)
     * @return array
     */
    public function provideFailureCases()
    {
        # Data for testAddfailure, easily expandable - just add another set of data below
        return [
            // Description is limited to 255 characters
            'Description over 254 characters' => ['Too long description', Random::generateString(255), '', 'landscape'],
            // Missing layout names
            'layout name empty' => ['', 'Layout name is empty', '', 'landscape'],
            'Layout name null' => [null, 'Layout name is null', '', 'landscape'],
            'Wrong resolution ID' => ['id not found', 'not found exception', '', 'invalid']
        ];
    }

     /**
     * Try and add two layouts with the same name
     */
    public function testAddDuplicate()
    {
        # Check if there are layouts with that name already in the system
        $flag = true;
        foreach ($this->startLayouts as $layout) {
            if ($layout->layout == 'phpunit layout') {
                $flag = false;
            }
        }
        # Load in a known layout if it's not there already
        if ($flag)
            (new XiboLayout($this->getEntityProvider()))->create('phpunit layout', 'phpunit layout', '', $this->getResolutionId('landscape'));
        $this->client->post('/layout', [
            'name' => 'phpunit layout',
            'description' => 'phpunit layout'
        ]);
        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status() . '. Body = ' . $this->client->response->body());
    }

    /**
    * Edit an existing layout
    */
    public function testEdit()
    {
        // Create a known layout with a random name for us to work with.
        // it will automatically get deleted in tearDown()
        $layout = $this->createLayout();

        // Checkout the Layout
        $this->checkout($layout);

        // Change the layout name and description
        $name = Random::generateString(8, 'phpunit');
        $description = Random::generateString(8, 'description');
        $this->client->put('/layout/' . $layout->layoutId, [
            'name' => $name,
            'description' => $description,
            'backgroundColor' => $layout->backgroundColor,
            'backgroundzIndex' => $layout->backgroundzIndex
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        
        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->layout);
        $this->assertSame($description, $object->data->description);
        # Check that the layout was actually renamed
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $layout->layout);
        $this->assertSame($description, $layout->description);
        # Clean up the Layout as we no longer need it
        $layout->delete();
    }

    /**
    * Edit an existing layout that should fail because of negative value in the backgroundzIndex
    */
    public function testEditFailure()
    {
        # Check if there are layouts with that name already in the system
        foreach ($this->startLayouts as $lay) {
            if ($lay->layout == 'phpunit layout') {
                $this->skipTest('layout already exists with that name');
                return;
            }
        }
        # Load in a known layout
        /** @var XiboLayout $layout */
        $layout = (new XiboLayout($this->getEntityProvider()))->create('phpunit layout', 'phpunit layout', '', $this->getResolutionId('landscape'));
        # Change the layout name and description
        $name = Random::generateString(8, 'phpunit');
        $description = Random::generateString(8, 'description');
        $this->client->put('/layout/' . $layout->layoutId, [
            'name' => $name,
            'description' => $description,
            'backgroundColor' => $layout->backgroundColor,
            'backgroundzIndex' => -1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
    }

    /**
     * Test delete
     * @group minimal
     */
    public function testDelete()
    {
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known layouts
        $layout1 = (new XiboLayout($this->getEntityProvider()))->create($name1, 'phpunit description', '', $this->getResolutionId('landscape'));
        $layout2 = (new XiboLayout($this->getEntityProvider()))->create($name2, 'phpunit description', '', $this->getResolutionId('landscape'));
        # Delete the one we created last
        $this->client->delete('/layout/' . $layout2->layoutId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        # Check only one remains
        $layouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->assertEquals(count($this->startLayouts) + 1, count($layouts));
        $flag = false;
        foreach ($layouts as $layout) {
            if ($layout->layoutId == $layout1->layoutId) {
                $flag = true;
            }
        }
        $this->assertTrue($flag, 'Layout ID ' . $layout1->layoutId . ' was not found after deleting a different layout');
        $layout1->delete();
    }

    /**
    * Try to delete a layout that is assigned to a campaign
    */
    public function testDeleteAssigned()
    {
        # Load in a known layout
        /** @var XiboLayout $layout */
        $layout = (new XiboLayout($this->getEntityProvider()))->create('phpunit layout assigned', 'phpunit layout', '', $this->getResolutionId('landscape'));
        // Make a campaign with a known name
        $name = Random::generateString(8, 'phpunit');
        /* @var XiboCampaign $campaign */
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create($name);
        $this->assertGreaterThan(0, count($layout), 'Cannot find layout for test');
        // Assign layout to campaign
        $campaign->assignLayout($layout->layoutId);
        # Check if it's assigned 
        $campaignCheck = (new XiboCampaign($this->getEntityProvider()))->getById($campaign->campaignId);
        $this->assertSame(1, $campaignCheck->numberLayouts);
        # Try to Delete the layout assigned to the campaign
        $this->client->delete('/layout/' . $layout->layoutId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
    }

    /**
     * Test Layout Retire
     */
    public function testRetire()
    {
        // Get known layout
        $layout = $this->createLayout();

        // Call retire
        $this->client->put('/layout/retire/' . $layout->layoutId, [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());

        // Get the same layout again and make sure its retired = 1
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);
        $this->assertSame(1, $layout->retired, 'Retired flag not updated');
    }

    /**
     * Test Unretire
     */
    public function testUnretire()
    {
        // Get known layout
        /** @var XiboLayout $layout */
        $layout = $this->createLayout();

        // Retire it
        $this->getEntityProvider()->put('/layout/retire/' . $layout->layoutId);

        // Call layout edit with this Layout
        $this->client->put('/layout/unretire/' . $layout->layoutId, [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
        ]);

        // Make sure that was successful
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        // Get the same layout again and make sure its retired = 0
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);

        $this->assertSame(0, $layout->retired, 'Retired flag not updated. ' . $this->client->response->body());
    }
    
    /**
     * Add new region to a specific layout
     * @dataProvider regionSuccessCases
     */
    public function testAddRegionSuccess($regionWidth, $regionHeight, $regionTop, $regionLeft)
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $this->checkout($layout);

        // Add region to our layout with data from regionSuccessCases
        $this->client->post('/region/' . $layout->layoutId, [
            'width' => $regionWidth,
            'height' => $regionHeight,
            'top' => $regionTop,
            'left' => $regionLeft
        ]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Check if region has intended values
        $this->assertSame($regionWidth, $object->data->width);
        $this->assertSame($regionHeight, $object->data->height);
        $this->assertSame($regionTop, $object->data->top);
        $this->assertSame($regionLeft, $object->data->left);
        # Clean up
        $this->assertTrue($layout->delete(), 'Unable to delete ' . $layout->layoutId);
    }

    /**
     * Each array is a test run
     * Format (width, height, top,  left)
     * @return array
     */
    public function regionSuccessCases()
    {
        return [
            // various correct regions
            'region 1' => [500, 350, 100, 150],
            'region 2' => [350, 200, 50, 50],
            'region 3' => [69, 69, 20, 420],
            'region 4 no offsets' => [69, 69, 0, 0]
        ];
    }

    /**
     * testAddFailure - test adding various regions that should be invalid
     * @dataProvider regionFailureCases
     */
    public function testAddRegionFailure($regionWidth, $regionHeight, $regionTop, $regionLeft, $expectedHttpCode, $expectedWidth, $expectedHeight)
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $this->checkout($layout);

        # Add region to our layout with datafrom regionFailureCases
        $response = $this->client->post('/region/' . $layout->layoutId, [
            'width' => $regionWidth,
            'height' => $regionHeight,
            'top' => $regionTop,
            'left' => $regionLeft
        ]);

        # Check if we receive failure as expected
        $this->assertSame($expectedHttpCode, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
        if ($expectedHttpCode == 200) {
            $object = json_decode($this->client->response->body());
            $this->assertObjectHasAttribute('data', $object);
            $this->assertObjectHasAttribute('id', $object);
            $this->assertSame($expectedWidth, $object->data->width);
            $this->assertSame($expectedHeight, $object->data->height);
        }
    }

    /**
     * Each array is a test run
     * Format (width, height, top,  left)
     * @return array
     */
    public function regionFailureCases()
    {
        return [
            // various incorrect regions
            'region no size' => [NULL, NULL, 20, 420, 200, 250, 250],
            'region negative dimensions' => [-69, -420, 20, 420, 500, null, null]
        ];
    }

    /**
     * Edit known region
     */
    public function testEditRegion()
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $this->checkout($layout);

        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,300,75,125);

        # Edit region
        $this->client->put('/region/' . $region->regionId, [
            'width' => 700,
            'height' => 500,
            'top' => 400,
            'left' => 400,
            'loop' => 0,
            'zIndex' => 1
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        # Check if successful
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Check if region has updated values
        $this->assertSame(700, $object->data->width);
        $this->assertSame(500, $object->data->height);
        $this->assertSame(400, $object->data->top);
        $this->assertSame(400, $object->data->left);
    }

    /**
     * Edit known region that should fail because of negative z-index value
     */
    public function testEditRegionFailure()
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $this->checkout($layout);

        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,300,75,125);
        # Edit region
        $this->client->put('/region/' . $region->regionId, [
            'width' => 700,
            'height' => 500,
            'top' => 400,
            'left' => 400,
            'loop' => 0,
            'zIndex' => -1
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if it failed
        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
    }
  
    /**
     *  delete region test
     */
    public function testDeleteRegion()
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $this->checkout($layout);

        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200, 670, 100, 100);

        # Delete region
        $this->client->delete('/region/' . $region->regionId);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /**
     * Add tag to a layout
     */
    public function testAddTag()
    {
        # Create layout 
        $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', $this->getResolutionId('landscape'));
        # Assign new tag to our layout 
        $this->client->post('/layout/' . $layout->layoutId . '/tag' , [
            'tag' => ['API']
            ]);
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        $this->assertSame('API', $layout->tags);
    }

    /**
     * Delete tags from layout
     * @group broken
     */
    public function testDeleteTag()
    {
        $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', $this->getResolutionId('landscape'));
        $tag = 'API';
        $layout->addTag($tag);
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);
        print_r($layout->tags);
        $this->client->delete('/layout/' . $layout->layoutId . '/untag', [
            'tag' => [$tag]
            ]);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $layout->delete();
    }

    /**
     * Calculate layout status
     */
    public function testStatus()
    {
        # Create layout 
        $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', $this->getResolutionId('landscape'));
        # Calculate layouts status
        $this->client->get('/layout/status/' . $layout->layoutId);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /**
     * Copy Layout Test
     */
    public function testCopy()
    {
        # Load in a known layout
        /** @var XiboLayout $layout */
        $layout = (new XiboLayout($this->getEntityProvider()))->create(
            Random::generateString(8, 'phpunit'),
            'phpunit layout',
            '',
            $this->getResolutionId('landscape')
        );

        // Generate new random name
        $nameCopy = Random::generateString(8, 'phpunit');

        // Call copy
        $this->client->post('/layout/copy/' . $layout->layoutId, [
                'name' => $nameCopy,
                'description' => 'Copy',
                'copyMediaFiles' => 1
            ], [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
            ]);

        $this->assertSame(200, $this->client->response->status());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        # Check if copied layout has correct name
        $this->assertSame($nameCopy, $object->data->layout);

        # Clean up the Layout as we no longer need it
        $this->assertTrue($layout->delete(), 'Unable to delete ' . $layout->layoutId);
    }

    /**
     * Position Test
     */
    public function testPosition()
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $this->checkout($layout);

        # Create Two known regions and add them to that layout
        $region1 = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,670,75,125);
        $region2 = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,300,475,625);
       
        # Reposition regions on that layout
        $regionJson = json_encode([
                    [
                        'regionid' => $region1->regionId,
                        'width' => 700,
                        'height' => 500,
                        'top' => 400,
                        'left' => 400
                    ],
                    [
                        'regionid' => $region2->regionId,
                        'width' => 100,
                        'height' => 100,
                        'top' => 40,
                        'left' => 40
                    ]
                ]);

        $this->client->put('/region/position/all/' . $layout->layoutId, [
                'regions' => $regionJson
            ], [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
            ]);

        # Check if successful
        $this->assertSame(200, $this->client->response->status());
        $object = json_decode($this->client->response->body());

        # Clean up
        $layout->delete();
    }

    /**
     * Position Test with incorrect parameters (missing height and incorrect spelling)
     */
    public function testPositionFailure()
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $this->checkout($layout);

        # Create Two known regions and add them to that layout
        $region1 = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,670,75,125);
        $region2 = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,300,475,625);

        # Reposition regions on that layout with incorrect/missing parameters 
        $regionJson = json_encode([
                    [
                        'regionid' => $region1->regionId,
                        'width' => 700,
                        'top' => 400,
                        'left' => 400
                    ],
                    [
                        'regionid' => $region2->regionId,
                        'heigTH' => 100,
                        'top' => 40,
                        'left' => 40
                    ]
                ]);

        $this->client->put('/region/position/all/' . $layout->layoutId, [
                'regions' => $regionJson
            ], [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
            ]);

        # Check if it fails as expected 
        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
        $object = json_decode($this->client->response->body());

        # Clean up
        $layout->delete();
    }
}
