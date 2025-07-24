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

use Xibo\OAuth2\Client\Entity\XiboHls;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class HlsWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class HlsWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var int */
    protected $widgetId;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        parent::installModuleIfNecessary('hls', '\Xibo\Widget\Hls');
    }

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
        $response = $this->getEntityProvider()->post('/playlist/widget/hls/' . $layout->regions[0]->regionPlaylist->playlistId);

        $this->widgetId = $response['widgetId'];

        $this->getLogger()->debug('Setup Finished');
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
     * Format ($name, $useDuration, $duration, $uri, $mute, $transparency)
     * @return array
     */
    public function provideEditCases()
    {
        # Sets of data used in testAdd
        return [
            'HLS stream' => [200, 'HLS stream', 1, 20, 'http://ceu.xibo.co.uk/hls/big_buck_bunny_adaptive_master.m3u8', 0, 0],
            'HLS stream 512' => [200, 'HLS stream with transparency', 1, 20, 'http://ceu.xibo.co.uk/hls/big_buck_bunny_adaptive_512.m3u8', 0, 1],
            'No url provided' => [422, 'no uri', 1, 10, '', 0, 0],
            'No duration provided' => [422, 'no duration with useDuration 1', 1, 0, 'http://ceu.xibo.co.uk/hls/big_buck_bunny_adaptive_512.m3u8', 0, 0],
        ];
    }

    /**
    * Edit
    * @dataProvider provideEditCases
    */
    public function testEdit($statusCode, $name, $useDuration, $duration, $uri, $mute, $transparency)
    {
        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
            'name' => $name,
            'useDuration' => $useDuration,
            'duration' => $duration,
            'uri' => $uri,
            'mute' => $mute,
            'transparency' => $transparency,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame($statusCode, $response->getStatusCode());

        if ($statusCode == 422)
            return;

        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboHls $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboHls($this->getEntityProvider()))->hydrate($response[0]);

        $this->assertSame($name, $checkWidget->name);
        $this->assertSame($duration, $checkWidget->duration);
        
        foreach ($checkWidget->widgetOptions as $option) {
            if ($option['option'] == 'uri') {
                $this->assertSame($uri, urldecode(($option['value'])));
            }
        }
    }
}
