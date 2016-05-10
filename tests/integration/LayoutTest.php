<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LayoutTest.php)
 */


namespace Xibo\Tests\Integration;

use Xibo\Entity\Layout;
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
     * @group minimal
     */
    public function testAddSuccess($layoutName, $layoutDescription, $layoutTemplateId, $layoutResolutionId)
    {
        // Loop through any pre-existing Laouts to make sure we're not
        // going to get a clash
        foreach ($this->startlayouts as $tmpLayouts) {
            if ($tmpLayouts->layout == $layoutName) {
                $this->skipTest("There is a pre-existing Layout with this name");
                return;
            }
        }

        $response = $this->client->post('/layout', [
            'name' => $layoutName,
            'description' => $layoutDescription
            'layoutId' => $layoutTemplateId,
            'resolutionId' => $layoutResolutionId
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($layoutName, $object->data->layout);
        $this->assertSame($layoutDescription, $object->data->description);
        $this->assertSame($layoutTemplateId, $object->data->layoutId);
        $this->assertSame($layoutResolutionId, $object->data->layoutResolutionId);

        # Check that the layout was really added
        $layouts = (new XiboLayout($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startLayouts) + 1, count($layouts));

        # Check that the layout was added correctly
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($object->id);

        $this->assertSame($layoutName, $layout->layout);
        $this->assertSame($layoutDescription, $layout->description);
        $this->assertSame($layoutTemplateId, $layout->layoutId);
        $this->assertSame($layoutResolutionId, $layout->layoutResolutionId);

        # Clean up the Layout as we no longer need it
        $this->assertTrue($layout->delete(), 'Unable to delete ' . $layout->layoutId);
    }
    
    /**
     * testAddFailure - test adding various Layouts that should be invalid
     * @dataProvider provideFailureCases
     * @group minimal
     */

    public function testAddFailure($layoutName, $layoutDescription, $layoutTemplateId, $layoutResolutionId)
    {
        try {
            $response = $this->client->post('/layout', [
                'name' => $layoutName,
                'description' => $layoutDescription
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
     * Each array is a test run
     * Format (LayoutName, description, layoutID (template), resolution)
     * @return array
     */

    public function provideSuccessCases()
    {

        return [
            // Multi-language layouts
            'English 1' => ['phpunit test Layout', 'Api', '', 9],
            'French 1' => ['Test de Français 1', 'Bienvenue à la suite de tests Xibo', '', 9],
            'German 1' => ['Deutsch Prüfung 1', 'Weiß mit schwarzem Text', '', 9],
            'Simplified Chinese 1' => ['试验组', '测试组描述', '', 9]
        ];
    }

    /**
     * Each array is a test run
     * Format (LayoutName, description, layoutID (template), resolution)
     * @return array
     */

    public function provideFailureCases()
    {
        return [
            // Description is limited to 255 characters
            'Description over 254 characters' => ['Too long description', Random::generateString(255)],
            // Missing group names
            'layout name empty' => ['', 'Layout name is empty', '', 9],
            'Layout name null' => [null, 'Layout name is null', '', 9]
        ];
    }

    /**
     *  Try and add two layouts with the same name
     *  @group minimal
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
            $layout = (new XiboLayout($this->getEntityProvider()))->create('phpunit layout', 'phpunit layout');
        }
        try {
            $response = $this->client->post('/layout', [
            'name' => 'phpunit layout',
            'description' => 'phpunit layout',
            ]);
        }
        catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
            $this->closeOutputBuffers();
            $displayGroup->delete();
            return;
        }
        $this->fail('InvalidArgumentException not thown as expected');
    }

    /**
    * Edit an existing layout
    * @depends testAddSuccess
    * @group minimal
    */
    public function testEdit()
    {
        foreach ($this->startLayouts as $layout) {
            if ($layout->layout == 'phpunit layout') {
                $this->skipTest('layout already exists with that name');
                return;
            }
        }
        # Load in a known layout
        /** @var XiboLayout $layout */
        $layout = (new XiboLayout($this->getEntityProvider()))->create('phpunit layout', 'phpunit layout');
        # Change the layout name and description
        $name = Random::generateString(8, 'phpunit');
        $description = Random::generateString(8, 'description');

        $this->client->put('/layout/' . $layout->layoutId, [
            'name' => $name,
            'description' => $description
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        
        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->layout);
        $this->assertSame($description, $object->data->description);

        # Check that the group was actually renamed
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $layout->layout);
        $this->assertSame($description, $layout->description);

        # Clean up the Layout as we no longer need it
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
        # Load in a couple of known layouts
        $layout1 = (new XiboLayout($this->getEntityProvider()))->create($name1, 'phpunit description')
        $layout2 = (new XiboLayout($this->getEntityProvider()))->create($name2, 'phpunit description')
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
        // Get any layout
        $layout = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 1]);

        $this->assertGreaterThan(0, count($layout));

        // Call retire
        $this->client->put('/layout/retire/' . $layout[0]->layoutId, [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());

        // Get the same layout again and make sure its retired = 1
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layout[0]->layoutId);

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
     * List all layouts Test
     */
    public function testListAll()
    {
        $this->client->get('/layout');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
//       fwrite(STDOUT, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     *  Add new layout test
     */
    public function testAdd()
    {
        $name = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/layout', [
            'name' => $name,
            'description' => 'test desc'
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());
//        fwrite(STDOUT, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->layout);
        return $object->id;
    }


    /**
     * Test edit
     * @param int $layoutId
     * @return int the id
     * @depends testAdd
     * 
     */
    public function testEdit22($layoutId)
    {
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($layoutId);

        $name = Random::generateString(8, 'phpunit');

        $this->client->put('/layout/' . $layoutId, [
            'name' => $name,
            'description' => $layout->description,
            'tags' => $layout->tags,
            'retire' => 0,
            'backgroundColor' => $layout->backgroundColor,
            'backgroundImageId' => $layout->backgroundImageId,
            'backgroundzIndex' => $layout->backgroundzIndex,
            'resolutionId' => 9
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
     //   fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);

        // Deeper check by querying for layout again
        $object = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);

        $this->assertSame($name, $object->layout);

        return $layoutId;
    }

    /**
     * Test delete
     * @param int $layoutId
     * @depends testEdit
     */ 
    public function testDelete22($layoutId)
    {
        $this->client->delete('/layout/' . $layoutId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /**
     * Add new region to a specific layout
     */
    public function testAddRegion($layoutId)
    {

        $layout = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 1]);
        
        $this->client->post('/region/' . $layout[0]->layoutId);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

        return $object->id;
    }

    /**
     * Edit added specific region
     * @depends testAddRegion
     */
    public function testEditRegion($regionId)
    {
        $this->client->put('/region/' . $regionId, [
            'width' => 700,
            'height' => 500,
            'top' => 400,
            'left' => 400,
            'loop' => 0,
            'zIndex' => '1'
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        
        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
   //     fwrite(STDERR, $this->client->response->body());

        return $regionId;
    }
  
   /**
    *  delete region test
    *  @depends testEditRegion
    */
   public function testDeleteRegion($regionId)
   {
       $this->client->delete('/region/' . $regionId);

       $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
   }

    /**
     * Copy Layout Test
     */
    public function testCopy()
    {
        // Get any layout
       $layout = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 1]);

        // Generate new random name
        $name = Random::generateString(8, 'phpunit');
        // Call copy
        $this->client->post('/layout/copy/' . $layout[0]->layoutId, [
            'name' => $name,
            'description' => 'Copy',
            'copyMediaFiles' => 0
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->layout);

        return $object->id;
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

    /**
     * Test delete
     * @param int $layoutId
     * @depends testCopy
     */
    public function testDeleteCopy($layoutId)
    {
        $this->client->delete('/layout/' . $layoutId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

  }
