<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (AudioWidgetTest.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboImage;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboAudio;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\Tests\LocalWebTestCase;

class AudioWidgetTest extends LocalWebTestCase
{
	protected $startLayouts;
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startMedias = (new XiboLibrary($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
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
                    fwrite(STDERR, 'Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
                }
            }
        }
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
        parent::tearDown();
    }

    public function testEdit()
    {
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Audio edit Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Upload new media
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API audio', PROJECT_ROOT . '/tests/resources/cc0_f1_gp_cars_pass_crash.mp3');
        # Assign media to a playlist
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$media->mediaId], null, $region->playlists[0]['playlistId']);
        $name = 'Edited Name';
        $duration = 80;
        $useDuration = 1;
        $mute = 0;
        $loop = 0;
        $widget = $playlist->widgets[0];
        $response = $this->client->put('/playlist/widget/' . $widget->widgetId, [
            'name' => $name,
            'duration' => $duration,
            'useDuration' => $useDuration,
            'mute' => $mute,
            'loop' => $loop,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboAudio($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame($name, $widgetOptions->name);
        $this->assertSame($duration, $widgetOptions->duration);
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'mute') {
                $this->assertSame($mute, intval($option['value']));
            }
            if ($option['option'] == 'loop') {
                $this->assertSame($loop, intval($option['value']));
            }
            if ($option['option'] == 'useDuration') {
                $this->assertSame($useDuration, intval($option['value']));
            }
        }
    }

    public function testDelete()
    {
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Audio edit Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Upload new media
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API Audio', PROJECT_ROOT . '/tests/resources/cc0_f1_gp_cars_pass_crash.mp3');
        # Assign media to a playlist
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$media->mediaId], null, $region->playlists[0]['playlistId']);
        $widget = $playlist->widgets[0];
        # Delete it
        $this->client->delete('/playlist/widget/' . $widget->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }

    public function testEditAssign()
    {
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Audio Assign Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Upload new medias
        $mediaImg = (new XiboLibrary($this->getEntityProvider()))->create('API image', PROJECT_ROOT . '/tests/resources/xts-night-001.jpg');
        $mediaAud = (new XiboLibrary($this->getEntityProvider()))->create('API audio', PROJECT_ROOT . '/tests/resources/cc0_f1_gp_cars_pass_crash.mp3');
        # Assign image media to a playlist
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$mediaImg->mediaId], null, $region->playlists[0]['playlistId']);
        $widget = $playlist->widgets[0];
        $volume = 80;
        $loop = 1;
        # Add audio to image assigned to a playlist
        $response = $this->client->put('/playlist/widget/' . $widget->widgetId . '/audio', [
            'mediaId' => $mediaAud->mediaId,
            'volume' => $volume,
            'loop' => $loop,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboImage($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame('API image', $widgetOptions->name);
        $this->assertSame(10, $widgetOptions->duration);
        $this->assertSame($mediaImg->mediaId, intval($widgetOptions->mediaIds[0]));
        $this->assertSame($mediaAud->mediaId, intval($widgetOptions->mediaIds[1]));
        $this->assertSame($volume, intval($widgetOptions->audio[0]['volume']));
        $this->assertSame($loop, intval($widgetOptions->audio[0]['loop']));
    }
}
