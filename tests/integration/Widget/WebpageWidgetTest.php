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
use Xibo\OAuth2\Client\Entity\XiboWebpage;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class WebpageWidgetTest extends LocalWebTestCase
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
	public function testAdd($name, $duration, $useDuration, $transparency, $uri, $scaling, $offsetLeft, $offsetTop, $pageWidth, $pageHeight, $modeId)
	{
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

		$response = $this->client->post('/playlist/widget/webpage/' . $playlistId, [
        	'name' => $name,
        	'duration' => $duration,
            'useDuration' => $useDuration,
        	'transparency' => $transparency,
        	'uri' => $uri,
        	'scaling' => $scaling,
        	'offsetLeft' => $offsetLeft,
        	'offsetTop' => $offsetTop,
        	'pageWidth' => $pageWidth,
        	'pageHeight' => $pageHeight,
        	'modeId' => $modeId
        	]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $webpageOptions = (new XiboWebpage($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($name, $webpageOptions->name);
        $this->assertSame($duration, $webpageOptions->duration);
        foreach ($webpageOptions->widgetOptions as $option) {
            if ($option['option'] == 'uri') {
                $this->assertSame($uri, urldecode($option['value']));
            }
            if ($option['option'] == 'modeId') {
                $this->assertSame($modeId, intval($option['value']));
            }
        }
	}

	/**
     * Each array is a test run
     * Format (($name, $duration, $useDuration, $transparency, $uri, $scaling, $offsetLeft, $offsetTop, $pageWidth, $pageHeight, $modeId)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'Open natively' => ['Open natively webpage widget', 60, 1, NULL, 'http://xibo.org.uk/', NULL, NULL, NULL, NULL, NULL, 1],
            'Manual Position default' => ['Manual Position default values', 10, 1, NULL, 'http://xibo.org.uk/', NULL, NULL, NULL, NULL, NULL, 2],
            'Manual Position custom' => ['Manual Position custom values', 100, 1, 1, 'http://xibo.org.uk/', 100, 200, 100, 1600, 900, 2],
            'Best Fit default' => ['Best Fit webpage widget', 10, 1, NULL, 'http://xibo.org.uk/', NULL, NULL, NULL, NULL, NULL, 3],
            'Best Fit custom' => ['Best Fit webpage widget', 150, 1, NULL, 'http://xibo.org.uk/', NULL, NULL, 1920, 1080, NULL, 3],
        ];
    }

    public function testEdit()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create a webpage with wrapper
    	$webpage = (new XiboWebpage($this->getEntityProvider()))->create('Open natively webpage widget', 60, 1, NULL, 'http://xibo.org.uk/', NULL, NULL, NULL, NULL, NULL, 1, $playlistId);
    	$nameNew = 'Edited Name';
    	$durationNew = 80;
    	$modeIdNew = 3;
    	$uriNew = 'http://xibo.org.uk/about/';
    	$response = $this->client->put('/playlist/widget/' . $webpage->widgetId, [
        	'name' => $nameNew,
        	'duration' => $durationNew,
            'useDuration' => 1,
        	'transparency' => $webpage->transparency,
        	'uri' => $uriNew,
        	'scaling' => $webpage->scaling,
        	'offsetLeft' => $webpage->offsetLeft,
        	'offsetTop' => $webpage->offsetTop,
        	'pageWidth' => $webpage->pageWidth,
        	'pageHeight' => $webpage->pageHeight,
        	'modeId' => $modeIdNew
        	], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $webpageOptions = (new XiboWebpage($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($nameNew, $webpageOptions->name);
        $this->assertSame($durationNew, $webpageOptions->duration);
        foreach ($webpageOptions->widgetOptions as $option) {
            if ($option['option'] == 'uri') {
                $this->assertSame($uriNew, urldecode($option['value']));
            }
            if ($option['option'] == 'modeId') {
                $this->assertSame($modeIdNew, intval($option['value']));
            }
        }
    }

    public function testDelete()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create a clock with wrapper
		$webpage = (new XiboWebpage($this->getEntityProvider()))->create('Open natively webpage widget', 60, 1, NULL, 'http://xibo.org.uk/', NULL, NULL, NULL, NULL, NULL, 1, $playlistId);
		# Delete it
		$this->client->delete('/playlist/widget/' . $webpage->widgetId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
