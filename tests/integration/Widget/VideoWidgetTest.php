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
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboVideo;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class VideoWidgetTest extends LocalWebTestCase
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
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Upload new media
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video', PROJECT_ROOT . '/tests/resources/HLH264.mp4');
        # Assign media to a playlist
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$media->mediaId], 10, $playlistId);
        $name = 'Edited Name';
        $useDuration = 1;
        $duration = 80;
        $scaleTypeId = 'stretch';
        $mute = 1;
        $loop = 0;
        $widget = $playlist->widgets[0];
        $response = $this->client->put('/playlist/widget/' . $widget->widgetId, [
            'name' => $name,
            'duration' => $duration,
            'useDuration' => $useDuration,
            'scaleTypeId' => $scaleTypeId,
            'mute' => $mute,
            'loop' => $loop,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboVideo($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($name, $widgetOptions->name);
        $this->assertSame($duration, $widgetOptions->duration);
        $this->assertSame($media->mediaId, intval($widgetOptions->mediaIds[0]));
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'scaleTypeId') {
                $this->assertSame($scaleTypeId, $option['value']);
            }
            if ($option['option'] == 'mute') {
                $this->assertSame($mute, intval($option['value']));
            }
            if ($option['option'] == 'loop') {
                $this->assertSame($loop, intval($option['value']));
            }
            if ($option['option'] == 'useDuration') {
                $this->assertSame($useDuration, $option['value']);
            }
        }
    }

    public function testDelete()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Upload new media
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video', PROJECT_ROOT . '/tests/resources/HLH264.mp4');
        # Assign media to a region
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$media->mediaId], 10, $playlistId);
        $widget = $playlist->widgets[0];
        # Delete it
        $this->client->delete('/playlist/widget/' . $widget->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
