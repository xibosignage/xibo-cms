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

use Xibo\OAuth2\Client\Entity\XiboWebpage;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class WebpageWidgetTest extends LocalWebTestCase
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
        $response = $this->getEntityProvider()->post('/playlist/widget/webpage/' . $layout->regions[0]->regionPlaylist->playlistId);

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

    /**
     * @throws \Xibo\OAuth2\Client\Exception\XiboApiException
     * @dataProvider provideSuccessCases
     */
    public function testEdit($name, $duration, $useDuration, $transparency, $uri, $scaling, $offsetLeft, $offsetTop, $pageWidth, $pageHeight, $modeId)
    {
        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
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
        	], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboWebpage $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboWebpage($this->getEntityProvider()))->hydrate($response[0]);

        $this->assertSame($name, $checkWidget->name);
        $this->assertSame($duration, $checkWidget->duration);

        foreach ($checkWidget->widgetOptions as $option) {
            if ($option['option'] == 'uri') {
                $this->assertSame($uri, urldecode($option['value']));
            }
            if ($option['option'] == 'modeId') {
                $this->assertSame($modeId, intval($option['value']));
            }
        }
    }
}
