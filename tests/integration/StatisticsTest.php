<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (StatisticsTest.php)
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
use Xibo\Tests\LocalWebTestCase;

/**
 * Class StatisticsTest
 * @package Xibo\Tests\Integration
 */
class StatisticsTest extends LocalWebTestCase
{


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
        # Create layout with random name
        $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', 9);
        # Add two regions to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,300,75,125);
        $region2 = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 100,100,475,425);
        # Upload three media files
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API image', PROJECT_ROOT . '/tests/resources/xts-night-001.jpg');
        $media2 = (new XiboLibrary($this->getEntityProvider()))->create('API image 2', PROJECT_ROOT . '/tests/resources/xts-layout-003-background.jpg');
        # Create and assign new text widget
        $text = (new XiboText($this->getEntityProvider()))->create('Text item', 10, 1, 'marqueeRight', 5, null, null, 'TEST API TEXT', null, $region2->playlists[0]['playlistId']);
        # Assign media to a playlists
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$media->mediaId, $media2->mediaId], 10, $region->playlists[0]['playlistId']);
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
