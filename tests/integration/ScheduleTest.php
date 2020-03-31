<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Tests\Integration;

use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;
use Xibo\OAuth2\Client\Entity\XiboCampaign;
use Xibo\OAuth2\Client\Entity\XiboCommand;
use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ScheduleTest
 * @package Xibo\Tests\Integration
 */
class ScheduleTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

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
        # list all scheduled events
        $response = $this->sendRequest('GET','/schedule/data/events');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('result', $object, $response->getBody());
    }
    
    /**
     * @group add
     * @dataProvider provideSuccessCasesCampaign
     */
    public function testAddEventCampaign($isCampaign, $scheduleFrom, $scheduleTo, $scheduledayPartId, $scheduleRecurrenceType, $scheduleRecurrenceDetail, $scheduleRecurrenceRange, $scheduleOrder, $scheduleIsPriority)
    {
        # Create new display group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
        $layout = null;
        $campaign = null;
        # isCampaign checks if we want to add campaign or layout to our event
        if ($isCampaign) {
            # Create Campaign
            /* @var XiboCampaign $campaign */
            $campaign = (new XiboCampaign($this->getEntityProvider()))->create('phpunit');
            # Create new event with data from provideSuccessCasesCampaign where isCampaign is set to true
            $response = $this->sendRequest('POST', $this->route, [
                'fromDt' => Carbon::createFromTimestamp($scheduleFrom)->format(DateFormatHelper::getSystemFormat()),
                'toDt' => Carbon::createFromTimestamp($scheduleTo)->format(DateFormatHelper::getSystemFormat()),
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
            # Create layout
            $layout = $this->createLayout();

            # Create new event with data from provideSuccessCasesCampaign where isCampaign is set to false
            $response = $this->sendRequest('POST', $this->route, [
                'fromDt' => Carbon::createFromTimestamp($scheduleFrom)->format(DateFormatHelper::getSystemFormat()),
                'toDt' => Carbon::createFromTimestamp($scheduleTo)->format(DateFormatHelper::getSystemFormat()),
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
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Clean up
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
    public function provideSuccessCasesCampaign()
    {
        # Sets of data used in testAddEventCampaign, first argument (isCampaign) controls if it's layout or campaign
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
        # Create command
        $command = (new XiboCommand($this->getEntityProvider()))->create('phpunit command', 'phpunit command desc', 'code');
        # Create Display Group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
        # Create new event with scheduled command and data from provideSuccessCasesCommand
            $response = $this->sendRequest('POST', $this->route, [
                'fromDt' => Carbon::createFromTimestamp($scheduleFrom)->format(DateFormatHelper::getSystemFormat()),
                'eventTypeId' => 2,
                'commandId' => $command->commandId,
                'displayGroupIds' => [$displayGroup->displayGroupId],
                'displayOrder' => $scheduleOrder,
                'isPriority' => $scheduleIsPriority,
                'scheduleRecurrenceType' => $scheduleRecurrenceType,
                'scheduleRecurrenceDetail' => $scheduleRecurrenceDetail,
                'scheduleRecurrenceRange' => $scheduleRecurrenceRange
            ]);
        # Check if successful
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Clean up
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
     * @dataProvider provideSuccessCasesOverlay
     */
    public function testAddEventOverlay($scheduleFrom, $scheduleTo, $scheduleCampaignId, $scheduleDisplays, $scheduledayPartId, $scheduleRecurrenceType, $scheduleRecurrenceDetail, $scheduleRecurrenceRange, $scheduleOrder, $scheduleIsPriority)
    {
        # Create new dispay group
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');

        # Create layout
        $layout = $this->createLayout();

        # Create new event with data from provideSuccessCasesOverlay
            $response = $this->sendRequest('POST', $this->route, [
                'fromDt' => Carbon::createFromTimestamp($scheduleFrom)->format(DateFormatHelper::getSystemFormat()),
                'toDt' => Carbon::createFromTimestamp($scheduleTo)->format(DateFormatHelper::getSystemFormat()),
                'eventTypeId' => 3,
                'campaignId' => $layout->campaignId,
                'displayGroupIds' => [$displayGroup->displayGroupId],
                'displayOrder' => $scheduleOrder,
                'isPriority' => $scheduleIsPriority,
                'scheduleRecurrenceType' => $scheduleRecurrenceType,
                'scheduleRecurrenceDetail' => $scheduleRecurrenceDetail,
                'scheduleRecurrenceRange' => $scheduleRecurrenceRange
            ]);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Clean up
        $displayGroup->delete();
        if ($layout != null)
            $layout->delete();
    }
    
    /**
     * Each array is a test run
     * Format ($scheduleFrom, $scheduleTo, $scheduledayPartId, $scheduleRecurrenceType, $scheduleRecurrenceDetail, $scheduleRecurrenceRange, $scheduleOrder, $scheduleIsPriority)
     * @return array
     */
    public function provideSuccessCasesOverlay()
    {
        return [
             'Overlay, no recurrence' => [time()+3600, time()+7200, 0, NULL, NULL, NULL, 0, 0, 0, 0],
        ];
    }
    /**
     * @group minimal
     */
    public function testEdit()
    {
        // Get a Display Group Id
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
        // Create Campaign
        /* @var XiboCampaign $campaign */
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create('phpunit');
        # Create new event
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            Carbon::now()->addSeconds(7200)->format(DateFormatHelper::getSystemFormat()),
            $campaign->campaignId,
            [$displayGroup->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0,
            0
        );
        $fromDt = time() + 3600;
        $toDt = time() + 86400;
        # Edit event
        $response = $this->sendRequest('PUT',$this->route . '/' . $event->eventId, [
            'fromDt' => Carbon::createFromTimestamp($fromDt)->format(DateFormatHelper::getSystemFormat()),
            'toDt' => Carbon::createFromTimestamp($toDt)->format(DateFormatHelper::getSystemFormat()),
            'eventTypeId' => 1,
            'campaignId' => $event->campaignId,
            'displayGroupIds' => [$displayGroup->displayGroupId],
            'displayOrder' => 1,
            'isPriority' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Check if edit was successful
        $this->assertSame($toDt, intval($object->data->toDt));
        $this->assertSame($fromDt, intval($object->data->fromDt));
        # Tidy up
        $displayGroup->delete();
        $campaign->delete();
    }
    
    /**
     * @param $eventId
     */
    public function testDelete()
    {

        # Get a Display Group Id
        $displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
        # Create Campaign
        /* @var XiboCampaign $campaign */
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create('phpunit');
        # Create event
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            Carbon::now()->addSeconds(7200)->format(DateFormatHelper::getSystemFormat()),
            $campaign->campaignId,
            [$displayGroup->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0,
            0
        );
        # Delete event
        $response = $this->sendRequest('DELETE',$this->route . '/' . $event->eventId);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        # Clean up
        $displayGroup->delete();
        $campaign->delete();
    }
}
