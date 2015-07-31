<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ResolutionTest.php)
 */

namespace Xibo\Tests;

use Xibo\Factory\ResolutionFactory;
use Xibo\Helper\Random;

class ResolutionTest extends LocalWebTestCase
{
    public function __construct()
    {
        parent::__construct('Resolution Test');
    }

    public function testListAll()
    {
        $this->client->get('/resolution');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     * @group add
     * @return int
     */
    public function testAdd()
    {
        $name = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/resolution', [
            'resolution' => $name,
            'width' => 1920,
            'height' => 1080
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->resolution);
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
        $resolution = ResolutionFactory::getById($resolutionId);

        $name = Random::generateString(8, 'phpunit');

        $this->client->put('/resolution/' . $resolutionId, [
            'resolution' => $name,
            'width' => $resolution->width,
            'height' => $resolution->height,
            'enabled' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);

        // Deeper check by querying for resolution again
        $object = ResolutionFactory::getById($resolutionId);

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
        $resolution = ResolutionFactory::getById($resolutionId);

        $this->client->put('/resolution/' . $resolutionId, [
            'resolution' => $resolution->resolution,
            'width' => 1080,
            'height' => 1920,
            'enabled' => $resolution->enabled
        ], array('CONTENT_TYPE' => 'application/x-www-form-urlencoded'));

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);

        // Deeper check by querying for resolution again
        $object = ResolutionFactory::getById($resolutionId);

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
        $this->client->delete('/resolution/' . $resolutionId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }
}
