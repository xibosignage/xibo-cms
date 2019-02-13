<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015-2018 Spring Signage Ltd
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

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboStats;
use Xibo\OAuth2\Client\Entity\XiboText;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class StatisticsTest
 * @package Xibo\Tests\Integration
 */
class StatisticsTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    protected $startMedias;
    protected $startLayouts;
    protected $startDisplays;
    
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startMedias = (new XiboLibrary($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startDisplays = (new XiboDisplay($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all media files that weren't there initially
        $finalMedias = (new XiboLibrary($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining media files and nuke them
        foreach ($finalMedias as $media) {
            /** @var XiboLibrary $media */
            $flag = true;
            foreach ($this->startMedias as $startMedia) {
               if ($startMedia->mediaId == $media->mediaId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $media->deleteAssigned();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $media->mediaId . '. E:' . $e->getMessage());
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

        // Tear down any displays that weren't there before
        $finalDisplays = (new XiboDisplay($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        
        # Loop over any remaining displays and nuke them
        foreach ($finalDisplays as $display) {
            /** @var XiboDisplay $display */
            $flag = true;
            foreach ($this->startDisplays as $startDisplay) {
               if ($startDisplay->displayId == $display->displayId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $display->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $display->displayId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    /**
     * Test the method call with default values
     * @group broken
     */
    public function testListAll()
    {
        $this->client->get('/stats');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }


    /**
     * Check if proof of play statistics are correct
     */
    public function testProof()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $response = $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get();
        $display = null;
        
        foreach ($displays as $disp) {
            if ($disp->license == $hardwareId) {
                $display = $disp;
            }
        }
        
        if ($display === null) {
            $this->fail('Display was not added correctly');
        }

        // Create layout with random name
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);

        // Add another region
        $region = $layout->regions[0];
        $region2 = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 100,100,475,425);

        # Upload three media files
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API image', PROJECT_ROOT . '/tests/resources/xts-night-001.jpg');
        $media2 = (new XiboLibrary($this->getEntityProvider()))->create('API image 2', PROJECT_ROOT . '/tests/resources/xts-layout-003-background.jpg');

        # Create and assign new text widget
        $text = (new XiboText($this->getEntityProvider()))->create('Text item', 10, 1, 'marqueeRight', 5, null, null, 'TEST API TEXT', null, $region2->regionPlaylist->playlistId);

        # Assign media to a playlists
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$media->mediaId, $media2->mediaId], 10, $region->regionPlaylist->playlistId);

        # Get Widget Id
        $widget = $playlist->widgets[0];
        $widget2 = $playlist->widgets[1];

        # Set start and date time
        /*
        $fromDt =  '2017-02-12 00:00:00';
        $toDt =  '2017-02-15 00:00:00';

        $fromDt2 =  '2017-02-12 00:00:00';
        $toDt2 =  '2017-02-14 00:00:00';

        $fromDt3 =  '2017-02-14 00:00:00';
        $toDt3 =  '2017-02-15 00:00:00';
         
        $fromDt4 =  '2017-02-15 00:00:00';
        $toDt4 =  '2017-02-16 00:00:00';
        */
        # Add stats to the DB -  known set
        # 
        # 1 layout, 1 region, 1 media
        # type,start,end,layout,media
        # layout,2016-10-12 00:00:00, 2016-10-15 00:00:00, L1, NULL
        # media,2016-10-12 00:00:00, 2016-10-15 00:00:00, L1, M1
        #
        # Result
        # L1 72 hours
        # M1 72 hours
        #
        # 1 layout, 1 region, 2 medias
        # type,start,end,layout,media
        # layout,2016-10-12 00:00:00, 2016-10-15 00:00:00, L1, NULL
        # media,2016-10-12 00:00:00, 2016-10-13 00:00:00, L1, M1
        # media,2016-10-13 00:00:00, 2016-10-15 00:00:00, L1, M2
        #
        # Result
        # L1 72 hours
        # M1 24 hours
        # M2 48 hours
        #
        # 1 layout, 2 region, 2 medias (1 per region)
        # type,start,end,layout,media
        # layout,2016-10-12 00:00:00, 2016-10-15 00:00:00, L1, NULL
        # media,2016-10-12 00:00:00, 2016-10-13 00:00:00, L1, M1
        # media,2016-10-13 00:00:00, 2016-10-14 00:00:00, L1, M1
        # media,2016-10-14 00:00:00, 2016-10-15 00:00:00, L1, M1
        # media,2016-10-12 00:00:00, 2016-10-15 00:00:00, L1, M2
        #
        # Result
        # L1 72 hours
        # M1 72 hours
        # M2 72 hours
        #
        #


        # First insert
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'layout', 
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-12 00:00:00',
            'end' => '2017-02-15 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => null,
            'tag' => null,
            'widgetId' => null
        ]);
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'media', 
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-12 00:00:00',
            'end' => '2017-02-13 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => $media->mediaId,
            'tag' => null,
            'widgetId' => $widget->widgetId
        ]);
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'media', 
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-14 00:00:00',
            'end' => '2017-02-15 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => $media2->mediaId,
            'tag' => null,
            'widgetId' => $widget2->widgetId
        ]);
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'widget',
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-12 00:00:00',
            'end' => '2017-02-15 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => null,
            'tag' => null,
            'widgetId' => $text->widgetId
        ]);

        # Second insert
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'layout', 
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-15 00:00:00',
            'end' => '2017-02-16 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => null,
            'tag' => null,
            'widgetId' => null
        ]);
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'media', 
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-13 00:00:00',
            'end' => '2017-02-14 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => $media->mediaId,
            'tag' => null,
            'widgetId' => $widget->widgetId
        ]);
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'media', 
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-15 00:00:00',
            'end' => '2017-02-16 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => $media2->mediaId,
            'tag' => null,
            'widgetId' => $widget2->widgetId
        ]);
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'widget',
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-15 00:00:00',
            'end' => '2017-02-16 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => null,
            'tag' => null,
            'widgetId' => $text->widgetId
        ]);
        
        # Third insert
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'layout', 
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-16 00:00:00',
            'end' => '2017-02-17 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => null,
            'tag' => null,
            'widgetId' => null
        ]);
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'media', 
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-16 12:00:00',
            'end' => '2017-02-17 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => $media->mediaId,
            'tag' => null,
            'widgetId' => $widget->widgetId
        ]);
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'media', 
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-16 00:00:00',
            'end' => '2017-02-16 12:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => $media2->mediaId,
            'tag' => null,
            'widgetId' => $widget2->widgetId
        ]);
        self::$container->store->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, widgetId)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => 'widget',
            'statDate' => date("Y-m-d H:i:s"),
            'start' => '2017-02-16 00:00:00',
            'end' => '2017-02-17 00:00:00',
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => null,
            'tag' => null,
            'widgetId' => $text->widgetId
        ]);        

        self::$container->store->commitIfNecessary();
        # get stats and see if they match with what we expect
        $this->client->get('/stats' , [
            'fromDt' => '2017-02-12 00:00:00',
            'toDt' => '2017-02-17 00:00:00',
            'displayId' => $display->displayId
            ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        //fwrite(STDERR, $this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $stats = (new XiboStats($this->getEntityProvider()))->get([$layout->layoutId]);
        //print_r($stats);
        self::$container->store->update('DELETE FROM `stat`', []);
    }
}
