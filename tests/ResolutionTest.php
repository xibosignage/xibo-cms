<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ResolutionTest.php)
 */

namespace Xibo\Tests;

class ResolutionTest extends TestCase
{
    public function __construct()
    {
        parent::__construct('Resolution Test');

        $this->start();
    }

    public function testListAll()
    {
        $response = \Requests::get($this->url('/resolution'));

        $this->assertSame(200, $response->status_code);
        $this->assertNotEmpty($response->body);

        $object = json_decode($response->body);

        $this->assertObjectHasAttribute('data', $object, $response->body);
    }

    /**
     * @group add
     * @return int
     */
    public function testAdd()
    {
        $name = \Xibo\Helper\Random::generateString(8, 'phpunit');

        $response = \Requests::post($this->url('/resolution'), [], [
            'resolution' => $name,
            'width' => 1920,
            'height' => 1080
        ]);

        $this->assertSame(200, $response->status_code, $response->body);

        $object = json_decode($response->body);

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
        $name = \Xibo\Helper\Random::generateString(8, 'phpunit');

        $response = \Requests::put($this->url('/resolution/' . $resolutionId), [], [
            'resolution' => $name,
            'width' => 1920,
            'height' => 1080,
            'enabled' => 1
        ]);

        $this->assertSame(200, $response->status_code);

        $object = json_decode($response->body);

        $this->assertObjectHasAttribute('data', $object);

        // Check the name has need edited
        $this->assertSame($name, $object->data[0]->resolution);

        // Deeper check by querying for resolution again
        $object = $this->getResolution($resolutionId);

        $this->assertSame($name, $object->resolution);
        $this->assertSame(1, $object->enabled, 'Enabled has been switched');

        return $resolutionId;
    }

    /**
     * @param $resolutionId
     * @depends testEdit
     */
    public function testDelete($resolutionId)
    {
        $response = \Requests::delete($this->url('/resolution/' . $resolutionId));

        $this->assertSame(200, $response->status_code, $response->body);
    }

    /**
     * Get a resolution back
     * @param int $resolutionId
     * @return mixed
     */
    private function getResolution($resolutionId)
    {
        $response = \Requests::get($this->url('/resolution', ['resolutionId' => $resolutionId]));

        $this->assertSame(200, $response->status_code);
        $this->assertNotEmpty($response->body);

        $object = json_decode($response->body);

        $this->assertObjectHasAttribute('data', $object, $response->body);

        return $object->data[0];
    }
}
