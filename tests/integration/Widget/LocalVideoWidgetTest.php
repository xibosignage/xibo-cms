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

use Xibo\OAuth2\Client\Entity\XiboLocalVideo;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class LocalVideoWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class LocalVideoWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

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

        // Create a Widget for us to edit.
        $response = $this->getEntityProvider()->post('/playlist/widget/localvideo/' . $layout->regions[0]->regionPlaylist->playlistId);

        $this->widgetId = $response['widgetId'];
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Delete the Layout we've been working with
        $this->deleteLayout($this->publishedLayout);

        parent::tearDown();

        $this->getLogger()->debug('Tear down for ' . get_class($this) .' Test');
    }

    /**
     * Each array is a test run
     * Format ($name, $duration, $theme, $clockTypeId, $offset, $format, $showSeconds, $clockFace)
     * @return array
     */
    public function provideEditCases()
    {
        # Sets of data used in testAdd
        return [
            'Aspect' => ['rtsp://184.72.239.149/vod/mp4:BigBuckBunny_115k.mov', 30, 1, 'aspect', 0],
            'Stretch muted' => ['rtsp://184.72.239.149/vod/mp4:BigBuckBunny_115k.mov', 100, 1, ' stretch', 1],
        ];
    }

    /**
     * @throws \Xibo\OAuth2\Client\Exception\XiboApiException
     * @dataProvider provideEditCases
     */
    public function testEdit($uri, $duration, $useDuration, $scaleTypeId, $mute)
    {
        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
            'uri' => $uri,
            'duration' => $duration,
            'useDuration' => $useDuration,
            'scaleTypeId' => $scaleTypeId,
            'mute' => $mute,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboLocalVideo $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboLocalVideo($this->getEntityProvider()))->hydrate($response[0]);

        $this->assertSame($duration, $checkWidget->duration);

        foreach ($checkWidget->widgetOptions as $option) {
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
}
