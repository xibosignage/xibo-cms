<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleTest.php)
 */


namespace Xibo\Tests;


class ScheduleLocalWebTest extends LocalWebTestCase
{
    protected $route = '/schedule';

    public function __construct()
    {
        parent::__construct('Schedule Test');
    }

    public function testListAll()
    {
        $this->client->get($this->route . '/data/events');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('result', $object, $this->client->response->body());
    }

    /**
     * @group add
     * @return int
     */
    public function testAdd()
    {
        $fromDt = time();
        $toDt = time() + 3600;

        $this->client->post($this->route, [
            'fromDt' => date('Y-m-d h:i:s', $fromDt),
            'toDt' => date('Y-m-d h:i:s', $toDt),
            'campaignId' => 2,
            'displayGroupIds' => [1, 12],
            'displayOrder' => 1,
            'isPriority' => 0
        ]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertObjectHasAttribute('id', $object, $this->client->response->body());

        return $object->id;
    }

    /**
     * Test edit
     * @param int $eventId
     * @return int the id
     * @depends testAdd
     */
    public function testEdit($eventId)
    {
        $fromDt = time();
        $toDt = time() + 86400;

        $this->client->put($this->route . '/' . $eventId, [
            'fromDt' => date('Y-m-d h:i:s', $fromDt),
            'toDt' => date('Y-m-d h:i:s', $toDt),
            'campaignId' => 2,
            'displayGroupIds' => [1, 12],
            'displayOrder' => 1,
            'isPriority' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame(1, $object->data[0]->isPriority);

        return $eventId;
    }

    /**
     * @param $eventId
     * @depends testEdit
     */
    public function testDelete($eventId)
    {
        $this->client->delete($this->route . '/' . $eventId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }
}
