<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LayoutTest.php)
 */


namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class LayoutTest
 * @package Xibo\Tests\Integration
 */
class LayoutTest extends LocalWebTestCase
{
    protected $startLayouts;

    /**
     * setUp - called before every test automatically
     */

    public function setup()
    {  
        parent::setup();
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get();
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all layouts that weren't there initially
        $finalLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 1000]);
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
        parent::tearDown();
    }

    /**
     *  List all layouts known empty
     */
    public function testListEmpty()
    {

        if (count($this->startLayouts) > 0) {
            $this->skipTest("There are pre-existing Layouts");
            return;
        }

        $this->client->get('/layout');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        # There should be no layouts in the system
        $this->assertEquals(0, $object->data->recordsTotal);
    }
    
    /**
     * testAddSuccess - test adding various Layouts that should be valid
     * @dataProvider provideSuccessCases
     */
    public function testAddSuccess($layoutName, $layoutDescription, $layoutTemplateId, $layoutResolutionId)
    {
        $response = $this->client->post('/layout', [
            'name' => $layoutName,
            'description' => $layoutDescription,
            'layoutId' => $layoutTemplateId,
            'resolutionId' => $layoutResolutionId
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($layoutName, $object->data->layout);
        $this->assertSame($layoutDescription, $object->data->description);
 //       $this->assertSame($layoutResolutionId, $object->data->resolutionId);

        # Check that the layout was really added
        $layouts = (new XiboLayout($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startLayouts) + 1, count($layouts));

        # Check that the layout was added correctly
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($object->id);

        $this->assertSame($layoutName, $layout->layout);
        $this->assertSame($layoutDescription, $layout->description);
//        $this->assertSame($layoutResolutionId, $layout->resolutionId);

        # Clean up the Layout as we no longer need it
        $this->assertTrue($layout->delete(), 'Unable to delete ' . $layout->layoutId);
    }
    
    /**
     * testAddFailure - test adding various Layouts that should be invalid
     * @dataProvider provideFailureCases
     */

    public function testAddFailure($layoutName, $layoutDescription, $layoutTemplateId, $layoutResolutionId)
    {
        try {
            $response = $this->client->post('/layout', [
                'name' => $layoutName,
                'description' => $layoutDescription,
                'layoutId' => $layoutTemplateId,
                'resolutionId' => $layoutResolutionId
        ]);
        }
        catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
            $this->closeOutputBuffers();
            return;
        }
        $this->fail('InvalidArgumentException not raised');
    }

    /**
     *  List all layouts known set
     *  @group minimal
     *  @depends testAddSuccess
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
                $layouts[] = (new XiboLayout($this->getEntityProvider()))->create($case[0],$case[1],$case[2],$case[3]);
            }
        }
        $this->client->get('/layout');
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        # There should be as many layouts as we created plus the number we started with in the system
        $this->assertEquals(count($layouts) + count($this->startLayouts), $object->data->recordsTotal);
        # Clean up the groups we created
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
    public function testListFilter($layoutName, $layoutDescription, $layoutTemplateId, $layoutResolutionId)
    {
        if (count($this->startLayouts) > 0) {
            $this->skipTest("There are pre-existing Layouts");
            return;
        }
        # Load in a known set of layouts
        # We can assume this works since we depend upon the test which
        # has previously added and removed these without issue:
        $cases =  $this->provideSuccessCases();
        $lyouts = [];
        foreach ($cases as $case) {
            $layouts[] = (new XiboLayout($this->getEntityProvider()))->create($case[0], $case[1], $case[2], $case[3]);
        }
        $this->client->get('/layout', [
                           'name' => $layoutName
                           ]);
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
                    assertTrue((strpos($word, $lay->layout) !== false), 'Group returned did not match the query string: ' . $lay->layout);
                }
            }
        }
        $this->assertTrue($flag, 'Search term not found');
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

        return [
            // Multi-language layouts
            'English 1' => ['phpunit test Layout', 'Api', '', 9],
            'French 1' => ['Test de Français 1', 'Bienvenue à la suite de tests Xibo', '', 9],
            'German 1' => ['Deutsch Prüfung 1', 'Weiß mit schwarzem Text', '', 9],
            'Simplified Chinese 1' => ['试验组', '测试组描述', '', 9],
            'Portrait layout' => ['Portrait layout', '1080x1920', '', 11],
            'No Description' => ['Just the title and resolution', NULL, '', 11],
            'Just title' => ['Just the name', NULL, NULL, NULL]
        ];
    }

    /**
     * Each array is a test run
     * Format (LayoutName, description, layoutID (template), resolution ID)
     * @return array
     */

    public function provideFailureCases()
    {
        return [
            // Description is limited to 255 characters
            'Description over 254 characters' => ['Too long description', Random::generateString(255), '', 9],
            // Missing layout names
            'layout name empty' => ['', 'Layout name is empty', '', 9],
            'Layout name null' => [null, 'Layout name is null', '', 9],
 //           'Wrong resolution ID' => ['id not found', 'not found exception', '', 69]
        ];
    }

    /**
     *  Try and add two layouts with the same name
     *  @depends testAddSuccess
     */
    public function testAddDuplicate()
    {
        $flag = true;
        foreach ($this->startLayouts as $layout) {
            if ($layout->layout == 'phpunit layout') {
                $flag = false;
            }
        }
        # Load in a known layout if it's not there already
        if ($flag) {
            $layout = (new XiboLayout($this->getEntityProvider()))->create('phpunit layout', 'phpunit layout', '', 9);
        }
        try {
            $response = $this->client->post('/layout', [
            'name' => 'phpunit layout',
            'description' => 'phpunit layout'
            ]);
        }
        catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
            $this->closeOutputBuffers();
            $layout->delete();
            return;
        }
        $this->fail('InvalidArgumentException not thown as expected');
    }

    /**
    * Edit an existing layout
    * @depends testAddSuccess
    */
    public function testEdit()
    {

        foreach ($this->startLayouts as $lay) {
            if ($lay->layout == 'phpunit layout') {
                $this->skipTest('layout already exists with that name');
                return;
            }
        }
        # Load in a known layout
        /** @var XiboLayout $layout */
        $layout = (new XiboLayout($this->getEntityProvider()))->create('phpunit layout', 'phpunit layout', '', 9);
        # Change the layout name and description
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
     * Test delete
     * @depends testAddSuccess
     * @group minimal
     */
    public function testDelete()
    {
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known layouts
        $layout1 = (new XiboLayout($this->getEntityProvider()))->create($name1, 'phpunit description', '', 9);
        $layout2 = (new XiboLayout($this->getEntityProvider()))->create($name2, 'phpunit description', '', 9);
        # Delete the one we created last
        $this->client->delete('/layout/' . $layout2->layoutId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        # Check only one remains
        $layouts = (new XiboLayout($this->getEntityProvider()))->get();
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
     * Test Layout Retire
     * @return mixed
     */
    public function testRetire()
    {
        // Get known layout
        $layout = (new XiboLayout($this->getEntityProvider()))->create('test layout', 'test description', '', 9);

        // Call retire
        $this->client->put('/layout/retire/' . $layout->layoutId, [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());

        // Get the same layout again and make sure its retired = 1
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);

        $this->assertSame(1, $layout->retired, 'Retired flag not updated');

        return $layout->layoutId;
    }

    /**
     * @param Layout $layoutId
     * @depends testRetire
     */
    public function testUnretire($layoutId)
    {
        // Get back the layout details for this ID
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layoutId);

        // Reset the flag to retired
        $layout->retired = 0;

        // Call layout edit with this Layout
        $this->client->put('/layout/' . $layout->layoutId, array_merge((array)$layout, ['name' => $layout->layout]), ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);


        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        // Get the same layout again and make sure its retired = 0
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);

        $this->assertSame(0, $layout->retired, 'Retired flag not updated. ' . $this->client->response->body());
    }

    

    /**
     * Add new region to a specific layout
     * @dataProvider regionSuccessCases
     */
    public function testAddRegion()
    {
        $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', 9);

        
        $this->client->post('/region/' . $layout->layoutId, [
        'width' => $regionWidth,
        'height' => $regionHeight,
        'top' => $regionTop,
        'left' => $regionLeft
            ]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
//        $this->assertSame($regionWidth, $object->data->width);
 //       $this->assertSame($regionHeight, $object->data->height);
//        $this->assertSame($regionTop, $object->data->top);
 //       $this->assertSame($regionLeft, $object->data->left);

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
            // Multi-language layouts
            'test case 1' => [500, 350, 100, 150],
            'test case 2' => [350, 200, 50, 50],
            'test case 3' => [69,69,20,420]
        ];
    }

    /**
     * Edit known region
     * @depends testAddRegion
     */
    public function testEditRegion()
    {

        $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', 9);

        $region = $this->client->post('/region/' . $layout->layoutId, [
        'width' => 200,
        'height' => 300,
        'top' => 75,
        'left' => 125
            ]);
       
        $this->assertSame(200, $this->client->response->status());
        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        $this->assertSame(200, $object->data->width);
        $this->assertSame(300, $object->data->height);
        $this->assertSame(75, $object->data->top);
        $this->assertSame(125, $object->data->left);

        return $object->id;
       
        $this->client->put('/region/' . $region->$regionId, [
            'width' => 700,
            'height' => 500,
            'top' => 400,
            'left' => 400,
            'loop' => 0,
            'zIndex' => '1'
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        
        $this->assertSame(200, $this->client->response->status());
        $object = json_decode($this->client->response->body());


        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        $this->assertSame(700, $object->data->width);
        $this->assertSame(500, $object->data->height);
        $this->assertSame(400, $object->data->top);
        $this->assertSame(400, $object->data->left);

        $layout->delete();
    }
  
   /**
    *  delete region test
    *  @depends testEditRegion
    */
   public function testDeleteRegion()
   {

    $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', 9);

        $region = $this->client->post('/region/' . $layout->layoutId, [
        'width' => 200,
        'height' => 670,
        'top' => 100,
        'left' => 100
            ]);
       
        $this->assertSame(200, $this->client->response->status());
        $object = json_decode($this->client->response->body());

        return $object->id;

        $this->assertSame(200, $object->data->width);
        $this->assertSame(670, $object->data->height);
        $this->assertSame(100, $object->data->top);
        $this->assertSame(100, $object->data->left);

        $this->client->delete('/region/' . $region->$regionId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $layout->delete();
   }

    /**
     * Copy Layout Test
     */
    public function testCopy()
    {
        # Load in a known layout
        /** @var XiboLayout $layout */
        $layout = (new XiboLayout($this->getEntityProvider()))->create('phpunit layout', 'phpunit layout', '', 9);

        // Generate new random name
        $nameCopy = Random::generateString(8, 'phpunit');

        // Call copy

        $this->client->post('/layout/copy/' . $layout->layoutId, [
            'name' => $nameCopy,
            'description' => 'Copy',
            'copyMediaFiles' => 1
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($nameCopy, $object->data->layout);

        # Clean up the Layout as we no longer need it
        $this->assertTrue($layout->delete(), 'Unable to delete ' . $layout->layoutId);
    }

    /**
     * Position Test
     * @depends testCopy
     * @depends testEditRegion2
     * @group broken
     */
    public function testPosition($layoutId, $regionId)
    {
        $this->client->put('/region/position/all/' . $layoutId, ['regions' => [
            'regionId' => $regionId,
            'width' => 700,
            'height' => 500,
            'top' => 400,
            'left' => 400
            ]]);
        
        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
   //     fwrite(STDERR, $this->client->response->body());

    }
}
