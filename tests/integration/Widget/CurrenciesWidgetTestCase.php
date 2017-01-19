<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CurrenciesWidgetTestCase.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboCurrencies;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\Tests\LocalWebTestCase;
use Xibo\Tests\Integration\Widget\WidgetTestCase;

class CurrenciesWidgetTestCase extends WidgetTestCase
{

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
    public function testAdd($overrideTemplate, $templateId, $name, $duration, $base, $items, $effect, $speed, $backgroundColor, $noRecordsMessage, $dateFormat, $updateInterval, $durationIsPerPage, $widgetOriginalWidth, $widgetOriginalHeight, $maxItemsPerPage, $mainTemplate, $itemTemplate, $styleSheet, $javaScript)
    {
        # Create layout
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Webpage add', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        
        if ($overrideTemplate = 1) {
            $response = $this->client->post('/playlist/widget/currencies/' . $region->playlists[0]['playlistId'], [
                'templateId' => $templateId,
                'name' => $name,
                'duration' => $duration,
                'base' => $base,
                'items' => $items,
                'effect' => $effect,
                'speed' => $speed,
                'backgroundColor' => $backgroundColor,
                'noRecordsMessage' => $noRecordsMessage,
                'dateFormat' => $dateFormat,
                'updateInterval' => $updateInterval,
                'durationIsPerPage' => $durationIsPerPage,
                'widgetOriginalWidth' => $widgetOriginalWidth,
                'widgetOriginalHeight' => $widgetOriginalHeight,
                'maxItemsPerPage' => $maxItemsPerPage,
                'mainTemplate' => $mainTemplate,
                'itemTemplate' => $itemTemplate,
                'styleSheet' => $styleSheet,
                'javaScript' => $javaScript
            ]);
        } else {
            $response = $this->client->post('/playlist/widget/currencies/' . $region->playlists[0]['playlistId'], [
                'templateId' => $templateId,
                'name' => $name,
                'duration' => $duration,
                'base' => $base,
                'items' => $items,
                'effect' => $effect,
                'speed' => $speed,
                'backgroundColor' => $backgroundColor,
                'noRecordsMessage' => $noRecordsMessage,
                'dateFormat' => $dateFormat,
                'updateInterval' => $updateInterval,
                'durationIsPerPage' => $durationIsPerPage,
                'maxItemsPerPage' => $maxItemsPerPage,
            ]);
        }

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);

    }

    /**
     * Each array is a test run
     * Format ($overrideTemplate, $templateId, $name, $duration, $base, $items, $effect, $speed, $backgroundColor, $noRecordsMessage, $dateFormat, $updateInterval, $durationIsPerPage, $widgetOriginalWidth, $widgetOriginalHeight, $maxItemsPerPage, $mainTemplate, $itemTemplate, $styleSheet, $javaScript)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'No override' => [0, 1, 'template 1', 6, 'GBP', 'PLN', NULL, NULL, NULL, 'No messages', NULL, 12, 1, 5],
            'No override' => [0, 2, 'template 2', 120, 'GBP', 'EUR', NULL, NULL, NULL, 'No messages', NULL, 120, 0, 2],
            'Overide' => [1, NULL, 'override template', 12, 'GBP', 'EUR', NULL, NULL, NULL, 'No messages', NULL, 60, 1, 1000, 800, 5, '<div class="container-main"><div class="container "><div class="row row-header"><div class="col-xs-2 col-xs-offset-7 text-center value">BUY</div><div class="col-xs-2 col-xs-offset-1 value text-center">SELL</div></div><div id="cycle-container">[itemsTemplate]</div></div></div>', '<div class="row row-finance"><div class="col-xs-1 flags"><img class="img-circle center-block " src="[CurrencyFlag]"></div><div class="col-xs-1 value ">[NameShort]</div><div class="col-xs-2 col-xs-offset-5 text-center value">[Bid]</div><div class="col-xs-2 col-xs-offset-1 value text-center">[Ask]</div> </div>','body {     font-family: "Helvetica", "Arial", sans-serif;     line-height: 1; }  .container-main {height: 420px !important;width: 820px !important; }  .container { height: 420px !important; width: 820px !important; float: left;  margin-top: 20px; }  .row-finance { height: 60px;  background: rgba(0, 0, 0, 0.87);  margin-bottom: 20px; }  .row {margin-right: 0; margin-left: 0; }  .row-header { margin-right: -15px; margin-left: -15px; margin-bottom: 20px; }  #cycle-container { margin-left: -15px; margin-right: -15px; }  .value { font-size: 20px; padding-top: 20px; font-weight: bold; color: #fff; }  .down-arrow { font-size: 20px; color: red; padding-top: 17px; }  .up-arrow { font-size: 20px;color: green; padding-top: 17px; } .variant { font-size: 20px; padding-top: 17px; }  .flags { padding-top: 4px; }  .center-block { width: 50px; height: 50px; }', NULL]
        ];
    }
}
