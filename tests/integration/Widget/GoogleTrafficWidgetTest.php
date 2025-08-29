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

use Xibo\OAuth2\Client\Entity\XiboGoogleTraffic;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class GoogleTrafficWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class GoogleTrafficWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var int */
    protected $widgetId;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        parent::installModuleIfNecessary('googletraffic', '\Xibo\Widget\GoogleTraffic');
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
        $response = $this->getEntityProvider()->post('/playlist/widget/googletraffic/' . $layout->regions[0]->regionPlaylist->playlistId);

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
     * Format ($name, $duration, $useDisplayLocation, $longitude, $latitude, $zoom)
     * @return array
     */
    public function provideEditCases()
    {
        # Sets of data used in testAdd
        return [
            'Use Display location' => [200, 'Traffic with display location', 2000, 1, null, null, 100],
            'Custom location 1' => [200, 'Traffic with custom location - Italy', 4500, 0, 7.640974, 45.109612, 80],
            'Custom location 2' => [200, 'Traffic with custom location - Japan', 4500, 0, 35.7105, 139.7336, 50],
            'No zoom provided' => [422, 'no zoom', 2000, 1, null, null, null],
            'no lat/long' => [422, 'no lat/long provided with useDisplayLocation 0', 3000, 0, null, null, 20],
            'low min duration' => [422, 'Traffic with display location', 20, 1, null, null, 100],
        ];
    }

    /**
     * Edit
     * @dataProvider provideEditCases
     * This test works correctly, it's marked as broken because we don't have this widget installed by default
     * @group broken
     */
    public function testEdit($statusCode, $name, $duration, $useDisplayLocation, $lat, $long, $zoom)
    {
        $this->getLogger()->debug('testEdit ' . get_class($this) .' Test');

        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
            'name' => $name,
            'duration' => $duration,
            'useDuration' => 1,
            'useDisplayLocation' => $useDisplayLocation,
            'longitude' => $long,
            'latitude' => $lat,
            'zoom' => $zoom,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame($statusCode, $response->getStatusCode(), 'Incorrect status code.', var_export($response, true));

        if ($statusCode == 422)
            return;

        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboGoogleTraffic $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboGoogleTraffic($this->getEntityProvider()))->hydrate($response[0]);

        $this->assertSame($name, $checkWidget->name);
        $this->assertSame($duration, $checkWidget->duration);
        
        foreach ($checkWidget->widgetOptions as $option) {
            if ($option['option'] == 'longitude' && $long !== null) {
                $this->assertSame($long, floatval($option['value']));
            }
            if ($option['option'] == 'latitude' && $lat !== null) {
                $this->assertSame($lat, floatval($option['value']));
            }
            if ($option['option'] == 'zoom') {
                $this->assertSame($zoom, intval($option['value']));
            }
        }
    }
}
