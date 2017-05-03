<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TickerWidgetTest.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDataSet;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboTicker;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\Tests\LocalWebTestCase;

class TickerWidgetTest extends LocalWebTestCase
{

	protected $startLayouts;
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
                    fwrite(STDERR, 'Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
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
                fwrite(STDERR, 'Unable to delete ' . $dataSet->dataSetId . '. E: ' . $e->getMessage() . PHP_EOL);
            }
        }
        parent::tearDown();
    }

    /**
     * @dataProvider provideSuccessCases
     */
    public function testAdd($isFeed, $uri, $duration, $useDuration)
    {
        # Create layout
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Ticker layout add', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        
        if ($isFeed) {
            $response = $this->client->post('/playlist/widget/ticker/' . $region->playlists[0]['playlistId'], [
                'uri' => $uri,
                'duration' => $duration,
                'useDuration' => $useDuration,
                'sourceId' => 1
            ]);
        } else {
            # Create a new dataset
            $dataSet = (new XiboDataSet($this->getEntityProvider()))->create('phpunit dataset', 'phpunit description');

            $response = $this->client->post('/playlist/widget/ticker/' . $region->playlists[0]['playlistId'], [
                'dataSetId' => $dataSet->dataSetId,
                'duration' => $duration,
                'useDuration' => $useDuration,
                'sourceId' => 2
            ]);
        }

        $widgetOptions = (new XiboTicker($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame($duration, $widgetOptions->duration);
    }

    /**
     * Each array is a test run
     * Format ($sourceId, $uri, $duration, $useDuration)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'Feed' => [true, 'http://ceu.xibo.co.uk/mediarss/feed.xml', 70, 1],
            'dataset' => [false, null, 80, 1]
        ];
    }

    /**
     * Edit rss feed ticker 
     */
    public function testEditFeed()
    {
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Ticker edit Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create a ticker with wrapper
        $ticker = (new XiboTicker($this->getEntityProvider()))->create(1, 'http://xibo.org.uk/feed', null, 70, 1, $region->playlists[0]['playlistId']);
        # Edit ticker widget
        $uriNew = 'http://ceu.xibo.co.uk/mediarss/feed.xml';
        $copyright = 'Copyrights ©Spring Signage';
        $templateId = 'media-rss-with-title';
        $noDataMessage = 'no records found';
        $response = $this->client->put('/playlist/widget/' . $ticker->widgetId, [
                'uri' => $uriNew,
                'name' => 'Edited widget',
                'duration' => 90,
                'useDuration' => 1,
                'updateInterval' => 100,
                'effect' => 'fade',
                'speed' => 5,
                'copyright' => $copyright,
                'numItems' => 10,
                'durationIsPerItem' => 1,
                'itemsSideBySide' => 0,
                'upperLimit' => 0,
                'lowerLimit' => 0,
                'itemsPerPage' => 1,
                'stripTags' => 'p',
                'textDirection' => 'ltr',
                'overrideTemplate' => 0,
                'templateId' => $templateId,
                'noDataMessage' => $noDataMessage
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboTicker($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        # check if changes were correctly saved
        $this->assertSame('Edited widget', $widgetOptions->name);
        $this->assertSame(90, $widgetOptions->duration);
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'uri') {
                $this->assertSame($uriNew, urldecode($option['value']));
            }
            if ($option['option'] == 'updateInterval') {
                $this->assertSame(100, intval($option['value']));
            }
            if ($option['option'] == 'effect') {
                $this->assertSame('fade', $option['value']);
            }
            if ($option['option'] == 'speed') {
                $this->assertSame(5, intval($option['value']));
            }
            if ($option['option'] == 'copyright') {
                $this->assertSame($copyright, $option['value']);
            }
            if ($option['option'] == 'numItems') {
                $this->assertSame(10, intval($option['value']));
            }
            if ($option['option'] == 'durationIsPerItem') {
                $this->assertSame(1, intval($option['value']));
            }
            if ($option['option'] == 'itemsSideBySide') {
                $this->assertSame(0, intval($option['value']));
            }
            if ($option['option'] == 'stripTags') {
                $this->assertSame('p', $option['value']);
            }
            if ($option['option'] == 'textDirection') {
                $this->assertSame('ltr', $option['value']);
            }
            if ($option['option'] == 'templateId') {
                $this->assertSame($templateId, $option['value']);
            }
            if ($option['option'] == 'noDataMessage') {
                $this->assertSame($noDataMessage, $option['value']);
            }
        }
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
        $this->assertSame(200, $this->client->response->status());
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
        $ticker = (new XiboTicker($this->getEntityProvider()))->create(1, 'http://ceu.xibo.co.uk/mediarss/feed.xml', null, 70, 1, $region->playlists[0]['playlistId']);
        # Delete it
        $this->client->delete('/playlist/widget/' . $ticker->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
