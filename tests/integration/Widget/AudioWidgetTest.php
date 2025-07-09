<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
use Xibo\OAuth2\Client\Entity\XiboImage;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class AudioWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class AudioWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var XiboLibrary */
    protected $media;

    /** @var XiboLibrary */
    protected $audio;

    /** @var int */
    protected $widgetId;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup for ' . get_class($this) .' Test');

        // Create a Layout
        $this->publishedLayout = $this->createLayout();

        // Checkout
        $layout = $this->getDraft($this->publishedLayout);

        // Create some media to upload
        $this->media = (new XiboLibrary($this->getEntityProvider()))->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/cc0_f1_gp_cars_pass_crash.mp3');
        $this->audio = (new XiboLibrary($this->getEntityProvider()))->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/cc0_f1_gp_cars_pass_crash.mp3');

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
        $this->audio->delete();

        parent::tearDown();

        $this->getLogger()->debug('Tear down for ' . get_class($this) .' Test');
    }

    /**
     * @throws \Xibo\OAuth2\Client\Exception\XiboApiException
     */
    public function testEdit()
    {
        $this->getLogger()->debug('testEdit ' . get_class($this) .' Test');

        // Now try to edit our assigned Media Item.
        $name = 'Edited Name ' . Random::generateString(5);
        $duration = 80;
        $useDuration = 1;
        $mute = 0;
        $loop = 0;

        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
            'name' => $name,
            'duration' => $duration,
            'useDuration' => $useDuration,
            'mute' => $mute,
            'loop' => $loop,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboImage $widgetOptions */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $widgetOptions = (new XiboImage($this->getEntityProvider()))->hydrate($response[0]);

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

        $this->getLogger()->debug('testEdit finished');
    }

    /**
     * Test to edit and assign an auto item to a widget
     */
    public function testAssign()
    {
        $this->getLogger()->debug('testAssign');

        $volume = 80;
        $loop = 1;

        // Add audio to image assigned to a playlist
        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId . '/audio', [
            'mediaId' => $this->audio->mediaId,
            'volume' => $volume,
            'loop' => $loop,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);

        /** @var XiboImage $widgetOptions */
        $widgetOptions = (new XiboImage($this->getEntityProvider()))->hydrate($response[0]);

        $this->assertSame($this->media->name, $widgetOptions->name);
        $this->assertSame($this->media->mediaId, intval($widgetOptions->mediaIds[0]));
        $this->assertSame($this->audio->mediaId, intval($widgetOptions->mediaIds[1]));
        $this->assertSame($volume, intval($widgetOptions->audio[0]['volume']));
        $this->assertSame($loop, intval($widgetOptions->audio[0]['loop']));

        $this->getLogger()->debug('testAssign finished');
    }
}
