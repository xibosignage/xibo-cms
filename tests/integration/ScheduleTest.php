<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleTest.php)
 */


namespace Xibo\Tests;


use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ScheduleFactory;

class ScheduleTest extends LocalWebTestCase
{
    protected $route = '/schedule';

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
        // Get a layout to schedule
        $layout = LayoutFactory::query(null, ['start' => 1, 'length' => 1])[0];
        // Get a Display Group Id
        $displayGroup = DisplayGroupFactory::query(null, ['start' => 1, 'length' => 1])[0];

        $fromDt = time();
        $toDt = time() + 3600;

        $this->client->post($this->route, [
            'fromDt' => date('Y-m-d h:i:s', $fromDt),
            'toDt' => date('Y-m-d h:i:s', $toDt),
            'campaignId' => $layout->campaignId,
            'displayGroupIds' => [$displayGroup->displayGroupId],
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
        // Get the scheduled event
        $event = ScheduleFactory::getById($eventId);
        $event->load();

        $fromDt = time();
        $toDt = time() + 86400;

        $this->client->put($this->route . '/' . $eventId, [
            'fromDt' => date('Y-m-d h:i:s', $event->fromDt),
            'toDt' => date('Y-m-d h:i:s', $event->toDt),
            'campaignId' => $event->campaignId,
            'displayGroupIds' => $event->displayGroups,
            'displayOrder' => 1,
            'isPriority' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame(1, $object->data->isPriority);

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
