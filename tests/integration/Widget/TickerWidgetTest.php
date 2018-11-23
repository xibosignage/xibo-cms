<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TickerWidgetTest.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Entity\DataSet;
use Xibo\Entity\Layout;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDataSet;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\OAuth2\Client\Entity\XiboTicker;
use Xibo\OAuth2\Client\Exception\XiboApiException;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class TickerWidgetTest
 *  tests the Ticker Widget Module
 * @package Xibo\Tests\Integration\Widget
 */
class TickerWidgetTest extends LocalWebTestCase
{
    use DisplayHelperTrait;

    /** @var Layout[] */
	private $startLayouts;

	/** @var DataSet[] */
	private $startDataSets;

	/** @var XiboDisplay */
	private $display;

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
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startDataSets = (new XiboDataSet($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
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
                    $this->getLogger()->error('Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
                }
            }
        }
        // tearDown all datasets that weren't there initially
        $finalDataSets = (new XiboDataSet($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);

        $difference = array_udiff($finalDataSets, $this->startDataSets, function ($a, $b) {
            /** @var XiboDataSet $a */
            /** @var XiboDataSet $b */
            return $a->dataSetId - $b->dataSetId;
        });

        # Loop over any remaining datasets and nuke them
        foreach ($difference as $dataSet) {
            /** @var XiboDataSet $dataSet */
            try {
                $dataSet->deleteWData();
            } catch (\Exception $e) {
                $this->getLogger()->error('Unable to delete ' . $dataSet->dataSetId . '. E: ' . $e->getMessage());
            }
        }

        if ($this->display !== null) {
            // Delete the resources we've created (deleting the display also clears out the event)
            $this->deleteDisplay($this->display);
        }

        parent::tearDown();
    }

    //</editor-fold>

    /**
     * Each array is a test run
     * Format ($sourceId, $uri, $duration, $useDuration)
     * @return array
     */
    public function provideAddSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'Feed' => [true, 'http://localhost/rss/feed.xml', 70, 1],
            'DataSet' => [false, null, 80, 1]
        ];
    }

    /**
     * @dataProvider provideAddSuccessCases
     */
    public function testAdd($isFeed, $uri, $duration, $useDuration)
    {
        # Create layout
        $name = Random::generateString();
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        
        if ($isFeed) {
            $this->client->post('/playlist/widget/ticker/' . $region->playlists[0]['playlistId'], [
                'uri' => $uri,
                'duration' => $duration,
                'useDuration' => $useDuration,
                'sourceId' => 1
            ]);
        } else {
            # Create a new dataset
            $dataSet = (new XiboDataSet($this->getEntityProvider()))->create('phpunit dataset', 'phpunit description');

            $this->client->post('/playlist/widget/ticker/' . $region->playlists[0]['playlistId'], [
                'dataSetId' => $dataSet->dataSetId,
                'duration' => $duration,
                'useDuration' => $useDuration,
                'sourceId' => 2
            ]);
        }

        // Check response
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());

        // Get the Ticker we created back out.
        // TODO: why are we getting this out by its Playlist ID?!
        $widgetOptions = (new XiboTicker($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame($duration, $widgetOptions->duration);
    }

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
                    'copyright' => 'Copyrights ©Spring Signage',
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
                    'copyright' => 'Copyrights ©Spring Signage',
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
        # Create layout
        $name = Random::generateString();
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', 9);
        # Create a ticker with wrapper
        $ticker = (new XiboTicker($this->getEntityProvider()))->create(1, 'http://xibo.org.uk/feed', null, 70, 1, $layout->regions[0]->playlists[0]['playlistId']);

        // Edit ticker widget
        $this->client->put('/playlist/widget/' . $ticker->widgetId, $newWidgetOptions, ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        // Check response
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        // Get the edited ticker back out again.
        $editedTicker = (new XiboTicker($this->getEntityProvider()))->getById($layout->regions[0]->playlists[0]['playlistId']);

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

        // Create a Display and Schedule my Layout to it
        $this->display = $this->createDisplay();
        $this->displaySetLicensed($this->display);

        // Schedule the Layout "always" onto our display
        //  deleting the layout will remove this at the end
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            date('Y-m-d H:i:s', time()+3600),
            date('Y-m-d H:i:s', time()+7200),
            $layout->campaignId,
            [$this->display->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0
        );

        // Confirm our Layout is in the Schedule
        $schedule = $this->getXmdsWrapper()->Schedule($this->display->license);

        $this->assertContains('file="' . $layout->layoutId . '"', $schedule, 'Layout not scheduled');

        // Call Required Files
        $rf = $this->getXmdsWrapper()->RequiredFiles($this->display->license);

        $this->assertContains('layoutid="' . $layout->layoutId . '"', $rf, 'Layout not in Required Files');

        // Call getResource
        // Parse the output, check the "duration" field.
        $this->getLogger()->debug('Calling GetResource - for ' . $layout->layoutId . ' - ' . $layout->regions[0]->regionId . ' - ' . $editedTicker->widgetId);

        $html = $this->getXmdsWrapper()->GetResource($this->display->license, $layout->layoutId, $layout->regions[0]->regionId, $editedTicker->widgetId);

        // Parse the HTML for the expected duration is per item
        $matches = [];
        preg_match('<!-- DURATION=(.*?) -->', $html, $matches);

        $this->getLogger()->debug('Matches: ' . var_export($matches, true));

        $this->assertEquals(2, count($matches), 'More than 1 duration tag in HTML');
        $this->assertEquals($expectedDuration, intval($matches[1]), 'Duration doesn\'t match expected duration');
    }

     /**
     * Edit dataSet ticker 
     */
    public function testEditDataset()
    {
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Ticker edit Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create dataset
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create('phpunit dataset', 'phpunit description');
        # Create a ticker with wrapper
        $ticker = (new XiboTicker($this->getEntityProvider()))->create(2, null, $dataSet->dataSetId, 30, 1, $region->playlists[0]['playlistId']);
        # Edit ticker widget
        $noDataMessage = 'no records found';
        $response = $this->client->put('/playlist/widget/' . $ticker->widgetId, [
                'sourceId' => 2,
                'dataSetId' => $dataSet->dataSetId,
                'name' => 'Edited widget',
                'duration' => 90,
                'useDuration' => 1,
                'updateInterval' => 100,
                'effect' => 'fadeout',
                'speed' => 500,
                'template' => '[Col1]',
                'durationIsPerItem' => 1,
                'itemsSideBySide' => 1,
                'upperLimit' => 0,
                'lowerLimit' => 0,
                'itemsPerPage' => 5,
                'noDataMessage' => $noDataMessage
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboTicker($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        # check if changes were correctly saved
        $this->assertSame('Edited widget', $widgetOptions->name);
        $this->assertSame(90, $widgetOptions->duration);
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'updateInterval') {
                $this->assertSame(100, intval($option['value']));
            }
            if ($option['option'] == 'effect') {
                $this->assertSame('fadeout', $option['value']);
            }
            if ($option['option'] == 'speed') {
                $this->assertSame(500, intval($option['value']));
            }
            if ($option['option'] == 'template') {
                $this->assertSame('[Col1]', $option['value']);
            }
            if ($option['option'] == 'durationIsPerItem') {
                $this->assertSame(1, intval($option['value']));
            }
            if ($option['option'] == 'itemsSideBySide') {
                $this->assertSame(1, intval($option['value']));
            }
            if ($option['option'] == 'upperLimit') {
                $this->assertSame(0, intval($option['value']));
            }
            if ($option['option'] == 'lowerLimit') {
                $this->assertSame(0, intval($option['value']));
            }
            if ($option['option'] == 'itemsPerPage') {
                $this->assertSame(5, intval($option['value']));
            }
            if ($option['option'] == 'noDataMessage') {
                $this->assertSame($noDataMessage, $option['value']);
            }
        }
    }
    public function testDelete()
    {
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Ticker delete Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create a ticker with wrapper
        $ticker = (new XiboTicker($this->getEntityProvider()))->create(1, 'http://localhost/rss/feed.xml', null, 70, 1, $region->playlists[0]['playlistId']);
        # Delete it
        $this->client->delete('/playlist/widget/' . $ticker->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
