<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleTest.php)
 */
namespace Xibo\Tests\Integration;
use Xibo\OAuth2\Client\Entity\XiboCampaign;
use Xibo\OAuth2\Client\Entity\XiboCommand;
use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ScheduleTest
 * @package Xibo\Tests\Integration
 */
class ScheduleTest extends LocalWebTestCase
{
    protected $route = '/schedule';
    
    protected $startCommands;
    protected $startDisplayGroups;
    protected $startEvents;
    protected $startLayouts;
    protected $startCampaigns;
    
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startDisplayGroups = (new XiboDisplayGroup($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startCampaigns = (new XiboCampaign($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startCommands = (new XiboCommand($this->getEntityProvider()))->get(['start' => 0, 'length' => 1000]);
    }
    
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all display groups that weren't there initially
        $finalDisplayGroups = (new XiboDisplayGroup($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining display groups and nuke them
        foreach ($finalDisplayGroups as $displayGroup) {
            /** @var XiboDisplayGroup $displayGroup */
            $flag = true;
            foreach ($this->startDisplayGroups as $startGroup) {
               if ($startGroup->displayGroupId == $displayGroup->displayGroupId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $displayGroup->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $displayGroup->displayGroupId . '. E:' . $e->getMessage());
                }
            }
        }
        // tearDown all layouts that weren't there initially
        $finalLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
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
                    fwrite(STDERR, 'Layout: Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
                }
            }
        }
        
        // tearDown all campaigns that weren't there initially
        $finalCamapigns = (new XiboCampaign($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining campaigns and nuke them
        foreach ($finalCamapigns as $campaign) {
            /** @var XiboCampaign $campaign */
            $flag = true;
            foreach ($this->startCampaigns as $startCampaign) {
               if ($startCampaign->campaignId == $campaign->campaignId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $campaign->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Campaign: Unable to delete ' . $campaign->campaignId . '. E:' . $e->getMessage());
                }
            }
        }
        // tearDown all commands that weren't there initially
        $finalCommands = (new XiboCommand($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining commands and nuke them
        foreach ($finalCommands as $command) {
            /** @var XiboCommand $command */
            $flag = true;
            foreach ($this->startCommands as $startCom) {
               if ($startCom->commandId == $command->commandId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $command->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Command: Unable to delete ' . $command->commandId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }
    
    /**
     * testListAll
     */
    public function testListAll()
    {
        $this->client->get('/schedule/data/events');
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('result', $object, $this->client->response->body());
    }
    
    /**
     * @group add
     * @dataProvider provideSuccessCasesLayout
     */
    public function testAddEventCampaign($isCampaign, $scheduleFrom, $scheduleTo, $scheduledayPartId, $scheduleRecurrenceType, $scheduleRecurrenceDetail, $scheduleRecurrenceRange, $scheduleOrder, $scheduleIsPriority)
    {
        // Get a Display Group Id
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
        $layout = null;
        $campaign = null;
        if ($isCampaign) {
            // Create Campaign
            /* @var XiboCampaign $campaign */
            $campaign = (new XiboCampaign($this->getEntityProvider()))->create('phpunit');
            $response = $this->client->post($this->route, [
                'fromDt' => date('Y-m-d H:i:s', $scheduleFrom),
                'toDt' => date('Y-m-d H:i:s', $scheduleTo),
                'eventTypeId' => 1,
                'campaignId' => $campaign->campaignId,
                'displayGroupIds' => [$displayGroup->displayGroupId],
                'displayOrder' => $scheduleOrder,
                'isPriority' => $scheduleIsPriority,
                'scheduleRecurrenceType' => $scheduleRecurrenceType,
                'scheduleRecurrenceDetail' => $scheduleRecurrenceDetail,
                'scheduleRecurrenceRange' => $scheduleRecurrenceRange
            ]);
        } else {
            // Create layout
            $layout = (new XiboLayout($this->getEntityProvider()))->create('phpunit layout', 'phpunit layout', '', 9);
            $response = $this->client->post($this->route, [
                'fromDt' => date('Y-m-d H:i:s', $scheduleFrom),
                'toDt' => date('Y-m-d H:i:s', $scheduleTo),
                'eventTypeId' => 1,
                'campaignId' => $layout->campaignId,
                'displayGroupIds' => [$displayGroup->displayGroupId],
                'displayOrder' => $scheduleOrder,
                'isPriority' => $scheduleIsPriority,
                'scheduleRecurrenceType' => $scheduleRecurrenceType,
                'scheduleRecurrenceDetail' => $scheduleRecurrenceDetail,
                'scheduleRecurrenceRange' => $scheduleRecurrenceRange
            ]);
        }
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $displayGroup->delete();
        if ($campaign != null)
            $campaign->delete();
        if ($layout != null)
            $layout->delete();
    }
    
    /**
     * Each array is a test run
     * Format ($isCampaign, $scheduleFrom, $scheduleTo, $scheduledayPartId, $scheduleRecurrenceType, $scheduleRecurrenceDetail, $scheduleRecurrenceRange, $scheduleOrder, $scheduleIsPriority)
     * @return array
     */
    public function provideSuccessCasesLayout()
    {
        return [
            'Campaign no priority, no recurrence' => [true, time()+3600, time()+7200, 0, NULL, NULL, NULL, 0, 0],
            'Layout no priority, no recurrence' => [false, time()+3600, time()+7200, 0, NULL, NULL, NULL, 0, 0]
        ];
    }
    
    /**
     * @group add
     * @dataProvider provideSuccessCasesCommand
     */
    public function testAddEventCommand($scheduleFrom, $scheduledayPartId, $scheduleRecurrenceType, $scheduleRecurrenceDetail, $scheduleRecurrenceRange, $scheduleOrder, $scheduleIsPriority)
    {
        // Create command
        $command = (new XiboCommand($this->getEntityProvider()))->create('phpunit command', 'phpunit command desc', 'code');
        // Get a Display Group Id
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
            $response = $this->client->post($this->route, [
                'fromDt' => date('Y-m-d H:i:s', $scheduleFrom),
                'eventTypeId' => 2,
                'commandId' => $command->commandId,
                'displayGroupIds' => [$displayGroup->displayGroupId],
                'displayOrder' => $scheduleOrder,
                'isPriority' => $scheduleIsPriority,
                'scheduleRecurrenceType' => $scheduleRecurrenceType,
                'scheduleRecurrenceDetail' => $scheduleRecurrenceDetail,
                'scheduleRecurrenceRange' => $scheduleRecurrenceRange
            ]);
        
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $displayGroup->delete();
        $command->delete();
    }
    
    /**
     * Each array is a test run
     * Format ($scheduleFrom, $scheduleDisplays, $scheduledayPartId, $scheduleRecurrenceType, $scheduleRecurrenceDetail, $scheduleRecurrenceRange, $scheduleOrder, $scheduleIsPriority)
     * @return array
     */
    public function provideSuccessCasesCommand()
    {
        return [
            'Command no priority, no recurrence' => [time()+3600, 0, NULL, NULL, NULL, 0, 0],
        ];
    }
    
    /**
     * @group add
     */
    public function testEdit()
    {
        // Get a Display Group Id
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
        // Create Campaign
        /* @var XiboCampaign $campaign */
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create('phpunit');
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            date('Y-m-d H:i:s', time()+3600),
            date('Y-m-d H:i:s', time()+7200),
            $campaign->campaignId,
            [$displayGroup->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0
        );
        $fromDt = time() + 3600;
        $toDt = time() + 86400;
        $this->client->put($this->route . '/' . $event->eventId, [
            'fromDt' => date('Y-m-d H:i:s', $fromDt),
            'toDt' => date('Y-m-d H:i:s', $toDt),
            'eventTypeId' => 1,
            'campaignId' => $event->campaignId,
            'displayGroupIds' => [$displayGroup->displayGroupId],
            'displayOrder' => 1,
            'isPriority' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Check if edit function was successful
        $this->assertSame($toDt, intval($object->data->toDt));
        $this->assertSame($fromDt, intval($object->data->fromDt));
        // Tidy up
        $displayGroup->delete();
        $campaign->delete();
    }
    
    /**
     * @param $eventId
     */
    public function testDelete()
    {

        // Get a Display Group Id
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
        // Create Campaign
        /* @var XiboCampaign $campaign */
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create('phpunit');
        
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            date('Y-m-d H:i:s', time()+3600),
            date('Y-m-d H:i:s', time()+7200),
            $campaign->campaignId,
            [$displayGroup->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0
        );

        $this->client->delete($this->route . '/' . $event->eventId);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $displayGroup->delete();
        $campaign->delete();
    }
}
