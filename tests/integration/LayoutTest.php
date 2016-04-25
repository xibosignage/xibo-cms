<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LayoutTest.php)
 */


namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\Entity\Layout;
use Xibo\Entity\Region;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class LayoutTest
 * @package Xibo\Tests
 */
class LayoutTest extends LocalWebTestCase
{
    /**
     * Test Layout Retire
     * @return mixed
     */
    public function testRetire()
    {
        // Get any layout
        $layout = $this->container->layoutFactory->query(null, ['start' => 1, 'length' => 1])[0];

        // Call retire
        $this->client->put('/layout/retire/' . $layout->layoutId, [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());


        // Get the same layout again and make sure its retired = 1
        $layout = $this->container->layoutFactory->getById($layout->layoutId);

        $this->assertSame(1, $layout->retired, 'Retired flag not updated');

        return $layout;

    }

    /**
     * @param Layout $layout
     * @depends testRetire
     */
    public function testUnretire($layout)
    {
        $layout->retired = 0;
        $this->client->put('/layout/' . $layout->layoutId, array_merge((array)$layout, ['name' => $layout->layout]), ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        // Get the same layout again and make sure its retired = 1
        $layout = $this->container->layoutFactory->getById($layout->layoutId);

        $this->assertSame(0, $layout->retired, 'Retired flag not updated. ' . $this->client->response->body());
    }

    /**
    *  List all layouts Test
    */ 

    public function testListAll()
    {
        $this->client->get('/layout');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
      // fwrite(STDOUT, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
    *  List specific layouts Test
    */ 
/*
    public function testListAll2()
    {
        $this->client->get('/layout', [
        'layoutId' => 3
//       'layout' =>
//       'userId' =>
//        'retired'=>
//        'tags' =>
//        'embed' =>
            ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDOUT, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }
*/
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
     */

    public function testEdit($layoutId)
    {
        $layout = $this->container->layoutFactory->getById($layoutId);

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
        $object = $this->container->layoutFactory->getById($layoutId);

        $this->assertSame($name, $object->layout);

        return $layoutId;
    }

    /**
     * Test delete
     * @param int $layoutId
     * @depends testEdit
     */ 
        public function testDelete($layoutId)
    {
        $this->client->delete('/layout/' . $layoutId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }


    /**
    * Delete specific layout
    */

/*
        public function testDelete2()
    {
        $this->client->delete('/layout/' . 6);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

*/



    /**
    *  Add new region to a specific layout
    */ 

        public function testAddRegion()
    {

        $layout = $this->container->layoutFactory->query(null, ['start' => 1, 'length' => 1])[0];
        $this->client->post('/region/' . $layout->layoutId);

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
            'loop' => 0
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        
        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
   //     fwrite(STDERR, $this->client->response->body());

        return $regionId;
    }


  
   /**
    *  delete region test
    *  @depends testAddRegion
    */ 

        public function testDeleteRegion($regionId)
    {
        $this->client->delete('/region/' . $regionId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /**
    *  Copy Layout Test
    */ 

    public function testCopy()
    {
    
        // Get any layout
        $layout = $this->container->layoutFactory->query(null, ['start' => 1, 'length' => 1])[0];

        // Generate new random name
        $name = Random::generateString(8, 'phpunit');
        // Call copy
        $this->client->post('/layout/copy/' . $layout->layoutId, [
            'name' => $name,
            'description' => 'Copy',
            'copyMediaFiles' => 0
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
  //      fwrite(STDERR, $this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->layout);

        return $object->id;
    }

    /**
    *  Add new region to Copied Layout
    *  @depends testCopy
    */ 

    public function testAddRegion2($layoutId)
    {

        $this->client->post('/region/' . $layoutId, [
            'width' => 500,
            'height' => 500,
            'top' => 400,
            'left' => 400,
            'loop' => 0
        ],['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
  //      fwrite(STDERR, $this->client->response->body());

        return $object->id;
    }


    /**
    *  Edit region test
    *  @depends testAddRegion2
    */ 

    public function testEditRegion2($regionId)
    {

        $this->client->put('/region/' . $regionId, [
            'width' => 700,
            'height' => 500,
            'top' => 400,
            'left' => 400,
            'loop' => 0
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        
        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
   //     fwrite(STDERR, $this->client->response->body());

        return $regionId;
    }


    /**
    * Position Test
    * @depends testCopy
    * @depends testEditRegion2
    */
/*
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

    */
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
