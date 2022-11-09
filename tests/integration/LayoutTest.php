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
use Xibo\OAuth2\Client\Entity\XiboCampaign;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboResolution;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
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

        $response = $this->sendRequest('GET','/layout');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
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
        $response = $this->sendRequest('POST','/layout', [
            'name' => $layoutName,
            'description' => $layoutDescription,
            'layoutId' => $layoutTemplateId,
            'resolutionId' => $layoutResolutionId
        ]);

        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());

        $object = json_decode($response->getBody());
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
        $request = $this->createRequest('POST','/layout');
        $request->withParsedBody([
            'name' => $layoutName,
            'description' => $layoutDescription,
            'layoutId' => $layoutTemplateId,
            'resolutionId' => $layoutResolutionId
        ]);

        try {
            $this->app->handle($request);
        } catch (InvalidArgumentException $e) {
            # check if they fail as expected
            $this->assertSame(422, $e->getCode(), 'Expecting failure, received ' . $e->getMessage());
        } catch (NotFoundException $e ) {
            $this->assertSame(404, $e->getCode(), 'Expecting failure, received ' . $e->getMessage());
        }

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

        $response = $this->sendRequest('GET','/layout');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

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
        $response = $this->sendRequest('GET','/layout', ['name' => $layoutName]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

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
        $landscapeId = $this->getResolutionId('landscape');

        if ($flag) {
            (new XiboLayout($this->getEntityProvider()))->create(
                'phpunit layout',
                'phpunit layout',
                '',
                $landscapeId
            );
        }

        $response = $this->sendRequest('POST','/layout', [
            'name' => 'phpunit layout',
            'description' => 'phpunit layout',
            'resolutionId' => $landscapeId
        ]);
        $this->assertSame(409, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode() . '. Body = ' . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(false, $object->success);
        $this->assertContains('You already own a Layout called ', $object->error);
    }

    /**
    * Edit an existing layout
    */
    public function testEdit()
    {
        // Create a known layout with a random name for us to work with.
        // it will automatically get deleted in tearDown()
        $layout = $this->createLayout();

        // We do not need to checkout the Layout to perform an edit of its top level data.
        // Change the layout name and description
        $name = Random::generateString(8, 'phpunit');
        $description = Random::generateString(8, 'description');
        $response = $this->sendRequest('PUT','/layout/' . $layout->layoutId, [
            'name' => $name,
            'description' => $description
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
        $object = json_decode($response->getBody());
        
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
        // Create a known layout with a random name for us to work with.
        // it will automatically get deleted in tearDown()
        $layout = $this->createLayout();

        // Set a background z-index that is outside parameters
        $response = $this->sendRequest('PUT','/layout/' . $layout->layoutId, [
            'backgroundColor' => $layout->backgroundColor,
            'backgroundzIndex' => -1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getBody());
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
        $response = $this->sendRequest('DELETE','/layout/' . $layout2->layoutId);
        # This should return 204 for success
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
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
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create($name);

        // Assign layout to campaign
        $this->getEntityProvider()->post('/campaign/layout/assign/' . $campaign->campaignId, [
            'layoutId' => $layout->layoutId
        ]);

        # Check if it's assigned 
        $campaignCheck = (new XiboCampaign($this->getEntityProvider()))->getById($campaign->campaignId);
        $this->assertSame(1, $campaignCheck->numberLayouts);
        # Try to Delete the layout assigned to the campaign
        $response = $this->sendRequest('DELETE','/layout/' . $layout->layoutId);
        # This should return 204 for success
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
    }

    /**
     * Test Layout Retire
     */
    public function testRetire()
    {
        // Get known layout
        $layout = $this->createLayout();

        // Call retire
        $response = $this->sendRequest('PUT','/layout/retire/' . $layout->layoutId, [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $response->getStatusCode());

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
        $response = $this->sendRequest('PUT','/layout/unretire/' . $layout->layoutId, [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
        ]);

        // Make sure that was successful
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());

        // Get the same layout again and make sure its retired = 0
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);

        $this->assertSame(0, $layout->retired, 'Retired flag not updated. ' . $response->getBody());
    }
    
    /**
     * Add new region to a specific layout
     * @dataProvider regionSuccessCases
     */
    public function testAddRegionSuccess($regionWidth, $regionHeight, $regionTop, $regionLeft)
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $layout = $this->getDraft($layout);

        // Add region to our layout with data from regionSuccessCases
        $response = $this->sendRequest('POST','/region/' . $layout->layoutId, [
            'width' => $regionWidth,
            'height' => $regionHeight,
            'top' => $regionTop,
            'left' => $regionLeft
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Check if region has intended values
        $this->assertSame($regionWidth, $object->data->width);
        $this->assertSame($regionHeight, $object->data->height);
        $this->assertSame($regionTop, $object->data->top);
        $this->assertSame($regionLeft, $object->data->left);
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
        $layout = $this->getDraft($layout);

        # Add region to our layout with datafrom regionFailureCases
        $response = $this->sendRequest('POST','/region/' . $layout->layoutId, [
            'width' => $regionWidth,
            'height' => $regionHeight,
            'top' => $regionTop,
            'left' => $regionLeft
        ]);

        # Check if we receive failure as expected
        $this->assertSame($expectedHttpCode, $response->getStatusCode(), 'Expecting failure, received ' . $response->getBody());
        if ($expectedHttpCode == 200) {
            $object = json_decode($response->getBody());
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
            'region negative dimensions' => [-69, -420, 20, 420, 422, null, null]
        ];
    }

    /**
     * Edit known region
     */
    public function testEditRegion()
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $layout = $this->getDraft($layout);

        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,300,75,125);

        # Edit region
        $response = $this->sendRequest('PUT','/region/' . $region->regionId, [
            'name' => $layout->layout . ' edited',
            'width' => 700,
            'height' => 500,
            'top' => 400,
            'left' => 400,
            'loop' => 0,
            'zIndex' => 1
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        # Check if successful
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
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
        $layout = $this->getDraft($layout);

        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,300,75,125);
        # Edit region
        $response = $this->sendRequest('PUT','/region/' . $region->regionId, [
            'width' => 700,
            'height' => 500,
            'top' => 400,
            'left' => 400,
            'loop' => 0,
            'zIndex' => -1
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if it failed
        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getBody());
    }
  
    /**
     *  delete region test
     */
    public function testDeleteRegion()
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $layout = $this->getDraft($layout);

        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200, 670, 100, 100);

        # Delete region
        $response = $this->sendRequest('DELETE','/region/' . $region->regionId);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status);
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
        $response = $this->sendRequest('POST','/layout/' . $layout->layoutId . '/tag' , [
            'tag' => ['API']
        ]);
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        foreach ($layout->tags as $tag) {
            $this->assertSame('API', $tag['tag']);
        }
    }

    /**
     * Delete tags from layout
     */
    public function testDeleteTag()
    {
        $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', $this->getResolutionId('landscape'));
        $tag = 'API';
        $layout->addTag($tag);
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);

        $response = $this->sendRequest('POST','/layout/' . $layout->layoutId . '/untag', [
            'tag' => [$tag]
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
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
        $response = $this->sendRequest('GET','/layout/status/' . $layout->layoutId);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
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
        $response = $this->sendRequest('POST','/layout/copy/' . $layout->layoutId, [
            'name' => $nameCopy,
            'description' => 'Copy',
            'copyMediaFiles' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $object = json_decode($response->getBody());
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
        $layout = $this->getDraft($layout);

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

        $response = $this->sendRequest('PUT','/region/position/all/' . $layout->layoutId, [
            'regions' => $regionJson
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        # Check if successful
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(true, $object->success);
        $this->assertSame(200, $object->status);
    }

    /**
     * Position Test with incorrect parameters (missing height and incorrect spelling)
     */
    public function testPositionFailure()
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $layout = $this->getDraft($layout);

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

        $response = $this->sendRequest('PUT','/region/position/all/' . $layout->layoutId, [
            'regions' => $regionJson
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        # Check if it fails as expected 
        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(false, $object->success);
        $this->assertSame(422, $object->httpStatus);
    }

    /**
     * Add a Drawer to the Layout
     */
    public function testAddDrawer()
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $layout = $this->getDraft($layout);

        // Add Drawer
        $response = $this->sendRequest('POST', '/region/drawer/' . $layout->layoutId);

        // Check if successful
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        // Check if drawer has the right values
        $this->assertSame($layout->width, $object->data->width);
        $this->assertSame($layout->height, $object->data->height);
        $this->assertSame(0, $object->data->top);
        $this->assertSame(0, $object->data->left);
    }

    /**
     * Edit a Drawer to the Layout
     */
    public function testSaveDrawer()
    {
        // Create a Layout and Checkout
        $layout = $this->createLayout();
        $layout = $this->getDraft($layout);

        // Add Drawer
        $drawer = $this->getEntityProvider()->post('/region/drawer/' . $layout->layoutId);

        // Save drawer
        $response = $this->sendRequest('PUT', '/region/drawer/' . $drawer['regionId'], [
            'width' => 1280,
            'height' => 720
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        // Check if successful
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        // Check if drawer has the right values
        $this->assertSame(1280, $object->data->width);
        $this->assertSame(720, $object->data->height);
        $this->assertSame(0, $object->data->top);
        $this->assertSame(0, $object->data->left);
    }
}
