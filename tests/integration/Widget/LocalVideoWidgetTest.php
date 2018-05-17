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

namespace Xibo\Tests\Integration\Widget;

use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLocalVideo;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class LocalVideoWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

	protected $startLayouts;
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
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
        parent::tearDown();
    }

    /**
     * @group add
     * @dataProvider provideSuccessCases
     */
    public function testAdd($uri, $duration, $useDuration, $scaleTypeId, $mute)
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        $response = $this->client->post('/playlist/widget/localVideo/' . $playlistId, [
            'uri' => $uri,
            'duration' => $duration,
            'useDuration' => $useDuration,
            'scaleTypeId' => $scaleTypeId,
            'mute' => $mute,
            ]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboLocalVideo($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($duration, $widgetOptions->duration);
        
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'uri') {
                $this->assertSame($uri, urldecode($option['value']));
            }
            if ($option['option'] == 'scaleTypeId') {
                $this->assertSame($scaleTypeId, $option['value']);
            }
            if ($option['option'] == 'mute') {
                $this->assertSame($mute, intval($option['value']));
            }
        }
    }

    /**
     * Each array is a test run
     * Format ($name, $duration, $theme, $clockTypeId, $offset, $format, $showSeconds, $clockFace)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'Aspect' => ['rtsp://184.72.239.149/vod/mp4:BigBuckBunny_115k.mov', 30, 1, 'aspect', 0],
            'Stretch muted' => ['rtsp://184.72.239.149/vod/mp4:BigBuckBunny_115k.mov', 100, 1, ' stretch', 1],
        ];
    }

    public function testEdit()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Add local video widget
        $localVideo = (new XiboLocalVideo($this->getEntityProvider()))->create('rtsp://184.72.239.149/vod/mp4:BigBuckBunny_115k.mov', 30, 1, 'aspect', 0, $playlistId);
        $duration = 80;
        $scaleTypeId = 'stretch';
        $mute = 1;
        $uri = 'rtsp://184.72.239.149/vod/mp4:BigBuckBunny_115k.mov';
        $response = $this->client->put('/playlist/widget/' . $localVideo->widgetId, [
            'uri' => $uri,
            'duration' => $duration,
            'useDuration' => 1,
            'scaleTypeId' => $scaleTypeId,
            'mute' => $mute,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboLocalVideo($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($duration, $widgetOptions->duration);
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'uri') {
                $this->assertSame($uri, urldecode($option['value']));
            }
            if ($option['option'] == 'scaleTypeId') {
                $this->assertSame($scaleTypeId, $option['value']);
            }
            if ($option['option'] == 'mute') {
                $this->assertSame($mute, intval($option['value']));
            }
        }
    }

    public function testDelete()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Add local video widget
        $localVideo = (new XiboLocalVideo($this->getEntityProvider()))->create('rtsp://184.72.239.149/vod/mp4:BigBuckBunny_115k.mov', 30, 1, 'aspect', 0, $playlistId);
        # Delete it
        $this->client->delete('/playlist/widget/' . $localVideo->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
