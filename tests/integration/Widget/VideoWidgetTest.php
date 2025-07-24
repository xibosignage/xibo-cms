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
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\OAuth2\Client\Entity\XiboVideo;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class VideoWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class VideoWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $draftLayout;

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

        $this->getLogger()->debug('Setup for ' . get_class($this) .' Test');

        // Create a Layout
        $this->publishedLayout = $this->createLayout();

        // Checkout
        $this->draftLayout = $this->getDraft($this->publishedLayout);

        // Create some media to upload
        $this->media = (new XiboLibrary($this->getEntityProvider()))->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/HLH264.mp4');

        // Assign the media we've created to our regions playlist.
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$this->media->mediaId], 10, $this->draftLayout->regions[0]->regionPlaylist->playlistId);

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

        $this->getLogger()->debug('Tear down for ' . get_class($this) .' Test');
    }

    public function testEdit()
    {
        $name = 'Edited Name: ' . Random::generateString(5);
        $useDuration = 1;
        $duration = 80;
        $scaleTypeId = 'stretch';
        $mute = 1;
        $loop = 0;

        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
            'name' => $name,
            'duration' => $duration,
            'useDuration' => $useDuration,
            'scaleTypeId' => $scaleTypeId,
            'mute' => $mute,
            'loop' => $loop,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

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

    public function testEndDetect()
    {
        $response = $this->sendRequest('PUT', '/playlist/widget/' . $this->widgetId, [
            'name' => 'End Detect',
            'duration' => 0,
            'useDuration' => 0,
            'scaleTypeId' => 'aspect',
            'mute' => 1,
            'loop' => 0,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());

        // Publish
        $this->publishedLayout = $this->publish($this->publishedLayout);

        // Build the Layout and get the XLF
        $this->buildLayout($this->publishedLayout);

        // Create a Display
        $display = $this->createDisplay();

        // Schedule the Layout "always" onto our display
        //  deleting the layout will remove this at the end
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            date('Y-m-d H:i:s', time()),
            date('Y-m-d H:i:s', time()+7200),
            $this->publishedLayout->campaignId,
            [$display->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0,
            0
        );

        $this->displaySetLicensed($display);
        $this->getXmdsWrapper()->RequiredFiles($display->license);

        // Get the file XLF
        $file = $this->getXmdsWrapper()->GetFile($display->license,
            $this->publishedLayout->layoutId,
            'layout',
            0,
            1024
        );

        $this->getLogger()->debug($file);

        $this->assertTrue(stripos($file, 'duration="0"') !== false, 'Duration is not 0 as expected for End Detection');
        $this->assertTrue(stripos($file, 'useDuration="0"') !== false, 'useDuration is incorrectly set');
    }

    public function testNotEndDetect()
    {
        $response = $this->sendRequest('PUT', '/playlist/widget/' . $this->widgetId, [
            'name' => 'End Detect',
            'duration' => 35,
            'useDuration' => 1,
            'scaleTypeId' => 'aspect',
            'mute' => 1,
            'loop' => 0,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());

        // Publish
        $this->publishedLayout = $this->publish($this->publishedLayout);

        // Build the Layout and get the XLF
        $this->buildLayout($this->publishedLayout);

        // Create a Display
        $display = $this->createDisplay();

        // Schedule the Layout "always" onto our display
        //  deleting the layout will remove this at the end
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            date('Y-m-d H:i:s', time()),
            date('Y-m-d H:i:s', time()+7200),
            $this->publishedLayout->campaignId,
            [$display->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0,
            0
        );

        $this->displaySetLicensed($display);
        $this->getXmdsWrapper()->RequiredFiles($display->license);

        // Get the file XLF
        $file = $this->getXmdsWrapper()->GetFile($display->license,
            $this->publishedLayout->layoutId,
            'layout',
            0,
            1024
        );

        $this->getLogger()->debug($file);

        $this->assertTrue(stripos($file, 'duration="35"') !== false, 'Duration is not > 0 as expected for non End Detection');
        $this->assertTrue(stripos($file, 'useDuration="1"') !== false, 'useDuration is incorrectly set');
    }
}
