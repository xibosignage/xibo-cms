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
use Xibo\Tests\LocalWebTestCase;

/**
 * Class StatisticsTest
 * @package Xibo\Tests\Integration
 */
class StatisticsTest extends LocalWebTestCase
{

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
     * @group broken
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
        $media2 = (new XiboLibrary($this->getEntityProvider()))->create('API image', PROJECT_ROOT . '/tests/resources/xts-flowers-001jpg');
        $media3 = (new XiboLibrary($this->getEntityProvider()))->create('API image', PROJECT_ROOT . '/tests/resources/xts-layout-003-background.jpg');
        # Assign media to a playlists
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$media->mediaId, $media2->mediaId], $region->playlists[0]->playlistId);
        $playlist2 = (new XiboPlaylist($this->getEntityProvider()))->assign([$media3->mediaId], $region2->playlists[0]->playlistId);
        # Add stats to the DB -  known set
        self::$container->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, `widgetId`)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => $this->type, // layout|media
            'statDate' => date("Y-m-d H:i:s"),
            'start' => $this->fromDt,
            'end' => $this->toDt,
            'scheduleId' => 0,
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => $media->mediaId,
            'tag' => null,
            'widgetId' => $playlist->widgetId
        ]);

        // TO DO
        # get stats and see if they match with what we expect
        $this->client->get('/stats' , [
        //    'fromDt' =>
        //    'toDt' =>
            'displayId' => $display->displayId,
            'layoutId' => $layout->layoutId,
            'mediaId' => [$media->mediaId,
                          $media2->mediaId,
                          $media3->mediaId]
            ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        fwrite(STDERR, $this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }
}
