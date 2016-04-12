<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LayoutTest.php)
 */


namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\Entity\Layout;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class LayoutTest
 * @package Xibo\Tests
 */
class LayoutTest extends LocalWebTestCase
{
    /**
     * Test Layout Require
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

    public function testListAll()
    {
        $this->client->get('/layout');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
      // fwrite(STDOUT, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

        public function testAdd()
    {
        $name = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/layout', [
            'name' => $name,
            'description' => 'test desc'
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());
       // fwrite(STDOUT, $this->client->response->body());

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
            'description' => 'edited',
            'bckgroundColor' => $layout->backgroundColor,
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
     * @depends testAdd
     */
        public function testDelete($layoutId)
    {
        $this->client->delete('/layout/' . $layoutId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }


/*

Delete specific layout

        public function testDelete2()
    {
        $this->client->delete('/layout/' . 6);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    */
  }
