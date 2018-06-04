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

use Xibo\OAuth2\Client\Entity\XiboGoogleTraffic;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class GoogleTrafficWidgetTest extends LocalWebTestCase
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
     * @group broken
     * @dataProvider provideSuccessCases
     */
	public function testAdd($name, $duration, $useDisplayLocation, $longitude, $latitude, $zoom)
	{
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

		$response = $this->client->post('/playlist/widget/googleTraffic/' . $playlistId, [
        	'name' => $name,
        	'duration' => $duration,
        	'useDisplayLocation' => $useDisplayLocation,
        	'longitude' => $longitude,
        	'latitude' => $latitude,
        	'zoom' => $zoom,
        	]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboGoogleTraffic($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($name, $widgetOptions->name);
        $this->assertSame($duration, $widgetOptions->duration);
        
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'useDisplayLocation') {
                $this->assertSame($useDisplayLocation, intval($option['value']));
            }
            if ($option['option'] == 'longitude' && $useDisplayLocation == 0) {
                $this->assertSame($longitude, floatval($option['value']));
            }
            if ($option['option'] == 'latitude' && $useDisplayLocation == 0) {
                $this->assertSame($latitude, floatval($option['value']));
            }
            if ($option['option'] == 'zoom') {
                $this->assertSame($zoom, intval($option['value']));
            }
        }
	}

	/**
     * Each array is a test run
     * Format ($name, $duration, $useDisplayLocation, $longitude, $latitude, $zoom)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'Use Display location' => ['Traffic with display location', 20, 1, null, null, 100],
            'Custom location 1' => ['Traffic with custom location - Italy', 45, 0, 7.640974, 45.109612, 80],
            'Custom location 2' => ['Traffic with custom location - Japan', 45, 0, 139.7336, 35.7105, 50],
        ];
    }

    /**
     * testAddFailure - test adding various Google traffic widgets that should be invalid
     * @dataProvider provideFailureCases
     * @group broken
     */
    public function testAddFailure($name, $duration, $useDisplayLocation, $longitude, $latitude, $zoom)
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create Google traffic widgets with arguments from provideFailureCases
        $response = $this->client->post('/playlist/widget/googleTraffic/' . $playlistId, [
            'name' => $name,
            'duration' => $duration,
            'useDisplayLocation' => $useDisplayLocation,
            'longitude' => $longitude,
            'latitude' => $latitude,
            'zoom' => $zoom,
            ]);
        # check if they fail as expected
        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
    }

    /**
     * Each array is a test run
     * Format ($name, $duration, $useDisplayLocation, $longitude, $latitude, $zoom)
     * @return array
     */
    public function provideFailureCases()
    {
        # Data for testAddfailure, easily expandable - just add another set of data below
        return [
            'No zoom provided' => ['no zoom', 20, 1, null, null, null],
            'no lat/long' => ['no lat/long provided with useDisplayLocation 0', 30, 0, null, null, 20]
        ];
    }

    /**
    * Edit
    * @group broken
    */
    public function testEdit()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create Google traffic widget
        $googleTraffic = (new XiboGoogleTraffic($this->getEntityProvider()))->create('Traffic with custom location - Italy', 45, 1, 0, 7.640974, 45.109612, 80, $playlistId);
        $nameNew = 'Edited Widget';
        $durationNew = 100;
        $longNew = 23.1223;
        $latNew = 49.6873;
        $zoomNew = 70;
        $response = $this->client->put('/playlist/widget/' . $googleTraffic->widgetId, [
            'name' => $nameNew,
            'duration' => $durationNew,
            'useDuration' => 1,
            'useDisplayLocation' => 0,
            'longitude' => $longNew,
            'latitude' => $latNew,
            'zoom' => $zoomNew,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboGoogleTraffic($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($nameNew, $widgetOptions->name);
        $this->assertSame($durationNew, $widgetOptions->duration);
        
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'longitude') {
                $this->assertSame($longNew, floatval($option['value']));
            }
            if ($option['option'] == 'latitude') {
                $this->assertSame($latNew, floatval($option['value']));
            }
            if ($option['option'] == 'zoom') {
                $this->assertSame($zoomNew, intval($option['value']));
            }
        }
    }

    /**
    * Delete
    * @group broken
    */
    public function testDelete()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create Google traffic widget
        $googleTraffic = (new XiboGoogleTraffic($this->getEntityProvider()))->create('Traffic with custom location - Italy', 45, 1, 0, 7.640974, 45.109612, 80, $playlistId);
        # Delete it
        $this->client->delete('/playlist/widget/' . $googleTraffic->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
