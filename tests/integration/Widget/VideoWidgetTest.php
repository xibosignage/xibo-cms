<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboVideo;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class VideoWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class VideoWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var XiboLibrary */
    protected $media;

    /** @var int */
    protected $widgetId;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup for ' . get_class() .' Test');

        // Create a Layout
        $this->publishedLayout = $this->createLayout();

        // Checkout
        $layout = $this->getDraft($this->publishedLayout);

        // Create some media to upload
        $this->media = (new XiboLibrary($this->getEntityProvider()))->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/HLH264.mp4');

        // Assign the media we've created to our regions playlist.
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$this->media->mediaId], 10, $layout->regions[0]->regionPlaylist->playlistId);

        // Store the widgetId
        $this->widgetId = $playlist->widgets[0]->widgetId;
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Delete the Layout we've been working with
        $this->deleteLayout($this->publishedLayout);

        // Tidy up the media
        $this->media->delete();

        parent::tearDown();

        $this->getLogger()->debug('Tear down for ' . get_class() .' Test');
    }

    public function testEdit()
    {
        $name = 'Edited Name: ' . Random::generateString(5);
        $useDuration = 1;
        $duration = 80;
        $scaleTypeId = 'stretch';
        $mute = 1;
        $loop = 0;

        $response = $this->client->put('/playlist/widget/' . $this->widgetId, [
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

        /** @var XiboVideo $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboVideo($this->getEntityProvider()))->hydrate($response[0]);

        $this->assertSame($name, $checkWidget->name);
        $this->assertSame($duration, $checkWidget->duration);
        $this->assertSame($this->media->mediaId, intval($checkWidget->mediaIds[0]));


        foreach ($checkWidget->widgetOptions as $option) {
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
}
