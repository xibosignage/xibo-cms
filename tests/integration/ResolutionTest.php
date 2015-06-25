<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ResolutionTest.php)
 */

namespace Xibo\Tests;

use Xibo\Helper\Random;

class ResolutionLocalWebTest extends LocalWebTestCase
{
    public function __construct()
    {
        parent::__construct('Resolution Test');

        $this->start();
    }

    public function testListAll()
    {
        $this->get('/resolution');

        $this->assertSame(200, $this->app->response->status());
        $this->assertNotEmpty($this->app->response->body());

        $object = json_decode($this->app->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->app->response->body());
    }

    /**
     * @group add
     * @return int
     */
    public function testAdd()
    {
        $name = Random::generateString(8, 'phpunit');
        echo "response body: ";

        $this->post('/resolution', [
            'resolution' => $name,
            'width' => 1920,
            'height' => 1080
        ]);


        $this->assertSame(200, $this->app->response->status(), $this->app->response->body());

        $object = json_decode($this->app->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data[0]->resolution);

        return $object->id;
    }

    /**
     * Test edit
     * @param int $resolutionId
     * @return int the id
     * @depends testAdd
     */
    public function testEdit($resolutionId)
    {
        $resolution = $this->getResolution($resolutionId);

        $name = Random::generateString(8, 'phpunit');

        $this->put('/resolution/' . $resolutionId, [
            'resolution' => $name,
            'width' => $resolution->width,
            'height' => $resolution->height,
            'enabled' => 1
        ]);

        $this->assertSame(200, $this->app->response->status());

        $object = json_decode($this->app->response->body());

        $this->assertObjectHasAttribute('data', $object);

        // Deeper check by querying for resolution again
        $object = $this->getResolution($resolutionId);

        $this->assertSame($name, $object->resolution);
        $this->assertSame(1, $object->enabled, 'Enabled has been switched');

        return $resolutionId;
    }

    /**
     * @param $resolutionId
     * @return int
     * @depends testEdit
     */
    public function testEditEnabled($resolutionId)
    {
        $resolution = $this->getResolution($resolutionId);

        $this->put('/resolution/' . $resolutionId, [
            'resolution' => $resolution->resolution,
            'width' => 1080,
            'height' => 1920,
            'enabled' => $resolution->enabled
        ]);

        $this->assertSame(200, $this->app->response->status());

        $object = json_decode($this->app->response->body());

        $this->assertObjectHasAttribute('data', $object);

        // Deeper check by querying for resolution again
        $object = $this->getResolution($resolutionId);

        $this->assertSame($resolution->resolution, $object->resolution);
        $this->assertSame(1080, $object->width);
        $this->assertSame(1920, $object->height);
        $this->assertSame($resolution->enabled, $object->enabled, 'Enabled has been switched');

        return $resolutionId;
    }

    /**
     * @param $resolutionId
     * @depends testEditEnabled
     */
    public function testDelete($resolutionId)
    {
        $this->delete('/resolution/' . $resolutionId);

        $this->assertSame(200, $this->app->response->status(), $this->app->response->body());
    }

    /**
     * Get a resolution back
     * @param int $resolutionId
     * @return mixed
     */
    private function getResolution($resolutionId)
    {
        $this->get('/resolution', ['resolutionId' => $resolutionId]);

        $this->assertSame(200, $this->app->response->status());
        $this->assertNotEmpty($this->app->response->body());

        $object = json_decode($this->app->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->app->response->body());

        return $object->data[0];
    }
}
