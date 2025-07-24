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

use Carbon\Carbon;
use Xibo\Entity\Display;
use Xibo\Helper\DateFormatHelper;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\OAuth2\Client\Entity\XiboTicker;
use Xibo\OAuth2\Client\Exception\XiboApiException;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class TickerWidgetTest
 *  tests the Ticker Widget Module
 * @package Xibo\Tests\Integration\Widget
 */
class TickerWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var int */
    protected $widgetId;

    /** @var XiboDisplay */
    protected $display;

    // <editor-fold desc="Init">

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        // Copy the rss resources folder into web
        shell_exec('cp -r ' . PROJECT_ROOT . '/tests/resources/rss ' . PROJECT_ROOT . '/web');
    }

    public static function tearDownAfterClass()
    {
        shell_exec('rm -r ' . PROJECT_ROOT . '/web/rss');

        parent::tearDownAfterClass();
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
        $response = $this->getEntityProvider()->post('/playlist/widget/ticker/' . $layout->regions[0]->regionPlaylist->playlistId);

        $this->widgetId = $response['widgetId'];

        // Create a Display
        $this->display = $this->createDisplay();
        $this->displaySetLicensed($this->display);

        // Schedule the Layout "always" onto our display
        //  deleting the layout will remove this at the end
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            Carbon::now()->addSeconds(3600)->format(DateFormatHelper::getSystemFormat()),
            Carbon::now()->addSeconds(7200)->format(DateFormatHelper::getSystemFormat()),
            $this->publishedLayout->campaignId,
            [$this->display->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0,
            0
        );

        $this->displaySetStatus($this->display, Display::$STATUS_DONE);
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

    //</editor-fold>

    /**
     * @return array
     */
    public function providerEditTest()
    {
        return [
            'Duration is per item with num items specified (higher than actual) with copyright' => [
                [
                    'uri' => 'http://localhost/rss/feed.xml',
                    'name' => 'Edited widget',
                    'duration' => 90,
                    'useDuration' => 1,
                    'numItems' => 10,
                    'durationIsPerItem' => 1,
                    'updateInterval' => 100,
                    'effect' => 'fade',
                    'speed' => 5,
                    'copyright' => 'Copyrights ©Xibo Signage',
                    'itemsSideBySide' => 0,
                    'upperLimit' => 0,
                    'lowerLimit' => 0,
                    'itemsPerPage' => 1,
                    'stripTags' => 'p',
                    'textDirection' => 'ltr',
                    'overrideTemplate' => 0,
                    'templateId' => 'media-rss-with-title',
                    'noDataMessage' => 'no records found'
                ],
                90*5
            ],
            'Duration is per item with num items specified (higher than actual) without copyright' => [
                [
                    'uri' => 'http://localhost/rss/feed.xml',
                    'name' => 'Edited widget',
                    'duration' => 90,
                    'useDuration' => 1,
                    'numItems' => 10,
                    'durationIsPerItem' => 1,
                    'updateInterval' => 100,
                    'effect' => 'fade',
                    'speed' => 5,
                    'copyright' => null,
                    'itemsSideBySide' => 0,
                    'upperLimit' => 0,
                    'lowerLimit' => 0,
                    'itemsPerPage' => 1,
                    'stripTags' => 'p',
                    'textDirection' => 'ltr',
                    'overrideTemplate' => 0,
                    'templateId' => 'media-rss-with-title',
                    'noDataMessage' => 'no records found'
                ],
                90*4
            ],
            'Duration is per item with num items specified (lower than actual)' => [
                [
                    'uri' => 'http://localhost/rss/feed.xml',
                    'name' => 'Edited widget',
                    'duration' => 90,
                    'useDuration' => 1,
                    'numItems' => 2,
                    'durationIsPerItem' => 1,
                    'updateInterval' => 100,
                    'effect' => 'fade',
                    'speed' => 5,
                    'copyright' => null,
                    'itemsSideBySide' => 0,
                    'upperLimit' => 0,
                    'lowerLimit' => 0,
                    'itemsPerPage' => 1,
                    'stripTags' => 'p',
                    'textDirection' => 'ltr',
                    'overrideTemplate' => 0,
                    'templateId' => 'media-rss-with-title',
                    'noDataMessage' => 'no records found'
                ],
                90*2
            ],
            'Duration not per item with num items specified' => [
                [
                    'uri' => 'http://localhost/rss/feed.xml',
                    'name' => 'Edited widget',
                    'duration' => 90,
                    'useDuration' => 1,
                    'numItems' => 2,
                    'durationIsPerItem' => 0,
                    'updateInterval' => 100,
                    'effect' => 'fade',
                    'speed' => 5,
                    'copyright' => null,
                    'itemsSideBySide' => 0,
                    'upperLimit' => 0,
                    'lowerLimit' => 0,
                    'itemsPerPage' => 1,
                    'stripTags' => 'p',
                    'textDirection' => 'ltr',
                    'overrideTemplate' => 0,
                    'templateId' => 'media-rss-with-title',
                    'noDataMessage' => 'no records found'
                ],
                90
            ],
            'Default Duration' => [
                [
                    'uri' => 'http://localhost/rss/feed.xml',
                    'name' => 'Edited widget',
                    'duration' => 90,
                    'useDuration' => 0,
                    'numItems' => 0,
                    'durationIsPerItem' => 0,
                    'updateInterval' => 100,
                    'effect' => 'fade',
                    'speed' => 5,
                    'copyright' => 'Copyrights ©Xibo Signage',
                    'itemsSideBySide' => 0,
                    'upperLimit' => 0,
                    'lowerLimit' => 0,
                    'itemsPerPage' => 1,
                    'stripTags' => 'p',
                    'textDirection' => 'ltr',
                    'overrideTemplate' => 0,
                    'templateId' => 'media-rss-with-title',
                    'noDataMessage' => 'no records found'
                ],
                5
            ]
        ];
    }

    /**
     * Edit rss feed ticker
     * @dataProvider providerEditTest
     * @param array $newWidgetOptions
     * @param int $expectedDuration What is the expected duration in rendered output?
     * @throws XiboApiException
     */
    public function testEditFeed($newWidgetOptions, $expectedDuration)
    {
        $this->getLogger()->debug('testEditFeed - IN');

        // Edit ticker widget
        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, $newWidgetOptions, ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->getLogger()->debug('Check Response');

        // Check response
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        // Get the edited ticker back out again.
        /** @var XiboTicker $editedTicker */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $editedTicker = (new XiboTicker($this->getEntityProvider()))->hydrate($response[0]);

        // check if changes were correctly saved
        $this->assertSame('Edited widget', $editedTicker->name);
        $this->assertSame(90, $editedTicker->duration);

        foreach ($editedTicker->widgetOptions as $option) {
            if ($option['option'] == 'uri') {
                $this->assertSame($newWidgetOptions['uri'], urldecode($option['value']));
            } else if ($option['option'] == 'updateInterval') {
                $this->assertSame($newWidgetOptions['updateInterval'], intval($option['value']));
            } else if ($option['option'] == 'effect') {
                $this->assertSame($newWidgetOptions['effect'], $option['value']);
            } else if ($option['option'] == 'speed') {
                $this->assertSame($newWidgetOptions['speed'], intval($option['value']));
            } else if ($option['option'] == 'copyright') {
                $this->assertSame($newWidgetOptions['copyright'], $option['value']);
            } else if ($option['option'] == 'numItems') {
                $this->assertSame($newWidgetOptions['numItems'], intval($option['value']));
            } else if ($option['option'] == 'durationIsPerItem') {
                $this->assertSame($newWidgetOptions['durationIsPerItem'], intval($option['value']));
            } else if ($option['option'] == 'itemsSideBySide') {
                $this->assertSame($newWidgetOptions['itemsSideBySide'], intval($option['value']));
            } else if ($option['option'] == 'stripTags') {
                $this->assertSame($newWidgetOptions['stripTags'], $option['value']);
            } else if ($option['option'] == 'textDirection') {
                $this->assertSame($newWidgetOptions['textDirection'], $option['value']);
            } else if ($option['option'] == 'templateId') {
                $this->assertSame($newWidgetOptions['templateId'], $option['value']);
            } else if ($option['option'] == 'noDataMessage') {
                $this->assertSame($newWidgetOptions['noDataMessage'], $option['value']);
            }
        }

        $this->getLogger()->debug('Test Complete - publish and validate');

        // Publish
        $this->publishedLayout = $this->publish($this->publishedLayout);
        $layout = $this->publishedLayout;

        // Confirm our Layout is in the Schedule
        $schedule = $this->getXmdsWrapper()->Schedule($this->display->license);

        $this->assertContains('file="' . $layout->layoutId . '"', $schedule, 'Layout not scheduled');

        // Call Required Files
        $rf = $this->getXmdsWrapper()->RequiredFiles($this->display->license);

        $this->assertContains('layoutid="' . $layout->layoutId . '"', $rf, 'Layout not in Required Files');

        // Call getResource
        // Parse the output, check the "duration" field.
        $this->getLogger()->debug('Calling GetResource - for ' . $layout->layoutId . ' - ' . $layout->regions[0]->regionId . ' - ' . $editedTicker->widgetId);

        $html = null;
        try {
            $html = $this->getXmdsWrapper()->GetResource($this->display->license, $layout->layoutId, $layout->regions[0]->regionId, $editedTicker->widgetId);
            $this->getLogger()->debug('Get Resource Complete');
        } catch (\Exception $exception) {
            $this->getLogger()->error($html);
            $this->fail($exception->getMessage());
        }

        // Parse the HTML for the expected duration is per item
        $matches = [];
        preg_match('<!-- DURATION=(.*?) -->', $html, $matches);

        $this->getLogger()->debug('Matches: ' . var_export($matches, true));

        $this->assertEquals(2, count($matches), 'More than 1 duration tag in HTML');
        $this->assertEquals($expectedDuration, intval($matches[1]), 'Duration doesn\'t match expected duration');

        $this->getLogger()->debug('Test Complete');
    }
}
