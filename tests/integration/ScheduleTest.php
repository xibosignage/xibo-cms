<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleTest.php)
 */


namespace Xibo\Tests\Integration;


use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ScheduleTest
 * @package Xibo\Tests\Integration
 */
class ScheduleTest extends LocalWebTestCase
{
    protected $route = '/schedule';

    /**
     * testListAll
     */
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
     * @group broken
     */
    public function testAdd()
    {
        // Get a layout to schedule
        $layout = (new XiboLayout($this->getEntityProvider()))->create('phpunit layout', 'phpunit layout', '', 9);

        // Get a Display Group Id
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');

        $fromDt = time();
        $toDt = time() + 3600;

        $this->client->post($this->route, [
            'fromDt' => date('Y-m-d h:i:s', $fromDt),
            'toDt' => date('Y-m-d h:i:s', $toDt),
            'eventTypeId' => Schedule::$LAYOUT_EVENT,
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
     * @group broken
     */
    public function testEdit($eventId)
    {
        // Get the scheduled event
        $event = (new XiboSchedule($this->getEntityProvider()))->getById($eventId);
        $event->load();

        $displayGroups = array_map(function ($displayGroup) {
            /** DisplayGroup $displayGroup */
            return $displayGroup->displayGroupId;
        }, $event->displayGroups);

        $fromDt = time();
        $toDt = time() + 86400;

        $this->client->put($this->route . '/' . $eventId, [
            'fromDt' => date('Y-m-d h:i:s', $fromDt),
            'toDt' => date('Y-m-d h:i:s', $toDt),
            'eventTypeId' => Schedule::$LAYOUT_EVENT,
            'campaignId' => $event->campaignId,
            'displayGroupIds' => $displayGroups,
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
     * @group broken
     */
    public function testDelete($eventId)
    {
        $this->client->delete($this->route . '/' . $eventId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }
}
