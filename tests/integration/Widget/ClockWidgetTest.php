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

use Xibo\OAuth2\Client\Entity\XiboClock;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ClockWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class ClockWidgetTest extends LocalWebTestCase
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
        $response = $this->getEntityProvider()->post('/playlist/widget/clock/' . $layout->regions[0]->regionPlaylist->playlistId);

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
     * Format ($name, $duration, $useDuration, $theme, $clockTypeId, $offset, $format, $showSeconds, $clockFace)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'Analogue' => ['Api Analogue clock', 20, 1, 1, 1, null, null, 0, 'TwentyFourHourClock'],
            'Digital' => ['API digital clock', 20, 1, 0, 2, null, '[HH:mm]', 0, 'TwentyFourHourClock'],
            'Flip 24h' => ['API Flip clock 24h', 5, 1, 0, 3, null, null, 1, 'TwentyFourHourClock'],
            'Flip counter' => ['API Flip clock Minute counter', 50, 1, 0, 3, null, null, 1, 'MinuteCounter']
        ];
    }

    /**
     * @param $name
     * @param $duration
     * @param $useDuration
     * @param $theme
     * @param $clockTypeId
     * @param $offset
     * @param $format
     * @param $showSeconds
     * @param $clockFace
     * @dataProvider provideSuccessCases
     */
    public function testEdit($name, $duration, $useDuration, $theme, $clockTypeId, $offset, $format, $showSeconds, $clockFace)
    {
        $response = $this->sendRequest('PUT', '/playlist/widget/' . $this->widgetId, [
            'name' => $name,
            'useDuration' => $useDuration,
            'duration' => $duration,
            'themeId' => $theme,
            'clockTypeId' => $clockTypeId,
            'offset' => $offset,
            'format' => $format,
            'showSeconds' => $showSeconds,
            'clockFace' => $clockFace
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboClock $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboClock($this->getEntityProvider()))->hydrate($response[0]);

        foreach ($checkWidget->widgetOptions as $option) {
            if ($option['option'] == 'clockTypeId') {
                $this->assertSame($clockTypeId, intval($option['value']));
            } else {
                if ($option['option'] == 'name') {
                    $this->assertSame($name, $option['value']);
                }
            }
        }
    }
}
