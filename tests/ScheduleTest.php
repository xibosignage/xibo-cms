<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleTest.php)
 */


namespace Xibo\Tests;


class ScheduleTest extends TestCase
{
    protected $route = '/schedule';

    public function __construct()
    {
        parent::__construct('Schedule Test');

        $this->start();
    }

    public function testListAll()
    {
        $response = \Requests::get($this->url($this->route) . '/data/events');

        $this->assertSame(200, $response->status_code);
        $this->assertNotEmpty($response->body);

        $object = json_decode($response->body);

        $this->assertObjectHasAttribute('result', $object, $response->body);
    }

    /**
     * @group add
     * @return int
     */
    public function testAdd()
    {
        $fromDt = time();
        $toDt = time() + 3600;

        $response = \Requests::post($this->url($this->route), [], [
            'fromDt' => date('Y-m-d h:i:s', $fromDt),
            'toDt' => date('Y-m-d h:i:s', $toDt),
            'campaignId' => 2,
            'displayGroupIds' => [1, 12],
            'displayOrder' => 1,
            'isPriority' => 0
        ]);

        $this->assertSame(200, $response->status_code, $response->body);

        $object = json_decode($response->body);

        $this->assertObjectHasAttribute('data', $object, $response->body);
        $this->assertObjectHasAttribute('id', $object, $response->body);

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

        $response = \Requests::put($this->url($this->route . '/' . $eventId), [], [
            'fromDt' => date('Y-m-d h:i:s', $fromDt),
            'toDt' => date('Y-m-d h:i:s', $toDt),
            'campaignId' => 2,
            'displayGroupIds' => [1, 12],
            'displayOrder' => 1,
            'isPriority' => 1
        ]);

        $this->assertSame(200, $response->status_code);

        $object = json_decode($response->body);

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
        $response = \Requests::delete($this->url($this->route . '/' . $eventId));

        $this->assertSame(200, $response->status_code, $response->body);
    }
}
