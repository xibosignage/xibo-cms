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

use Xibo\OAuth2\Client\Entity\XiboCurrencies;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class CurrenciesWidgetTest extends LocalWebTestCase
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
     * @dataProvider provideSuccessCases
     * @group broken
     */
    public function testAdd($isOverride, $templateId, $name, $duration, $useDuration, $base, $items, $reverseConversion, $effect, $speed, $backgroundColor, $noRecordsMessage, $dateFormat, $updateInterval, $durationIsPerPage, $widgetOriginalWidth, $widgetOriginalHeight, $maxItemsPerPage, $mainTemplate, $itemTemplate, $styleSheet, $javaScript)
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];
        
        if ($isOverride) {
            $response = $this->client->post('/playlist/widget/currencies/' . $playlistId, [
                'templateId' => $templateId,
                'name' => $name,
                'duration' => $duration,
                'useDuration' => $useDuration,
                'base' => $base,
                'items' => $items,
                'reverseConversion' => $reverseConversion,
                'effect' => $effect,
                'speed' => $speed,
                'backgroundColor' => $backgroundColor,
                'noRecordsMessage' => $noRecordsMessage,
                'dateFormat' => $dateFormat,
                'updateInterval' => $updateInterval,
                'overrideTemplate' => 1,
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
            $response = $this->client->post('/playlist/widget/currencies/' . $playlistId, [
                'templateId' => $templateId,
                'name' => $name,
                'duration' => $duration,
                'useDuration' => $useDuration,
                'base' => $base,
                'items' => $items,
                'reverseConversion' => $reverseConversion,
                'effect' => $effect,
                'speed' => $speed,
                'backgroundColor' => $backgroundColor,
                'noRecordsMessage' => $noRecordsMessage,
                'dateFormat' => $dateFormat,
                'updateInterval' => $updateInterval,
                'overrideTemplate' => 0,
                'durationIsPerPage' => $durationIsPerPage,
            ]);
        }

        $widgetOptions = (new XiboCurrencies($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($name, $widgetOptions->name);
        $this->assertSame($duration, $widgetOptions->duration);
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'templateId') {
                $this->assertSame($templateId, $option['value']);
            }
            if ($option['option'] == 'base') {
                $this->assertSame($base, $option['value']);
            }
            if ($option['option'] == 'items') {
                $this->assertSame($items, $option['value']);
            }
            if ($option['option'] == 'updateInterval') {
                $this->assertSame($updateInterval, intval($option['value']));
            }
            if ($option['option'] == 'reverseConversion') {
                $this->assertSame($reverseConversion, intval($option['value']));
            }
            if ($option['option'] == 'maxItemsPerPage') {
                $this->assertSame($maxItemsPerPage, intval($option['value']));
            }
        }

    }

    /**
     * Each array is a test run
     * Format ($overrideTemplate, $templateId, $name, $duration, $useDuration, $base, $items, $reverseConversion, $effect, $speed, $backgroundColor, $noRecordsMessage, $dateFormat, $updateInterval, $durationIsPerPage, $widgetOriginalWidth, $widgetOriginalHeight, $maxItemsPerPage, $mainTemplate, $itemTemplate, $styleSheet, $javaScript)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'No override template 1' => [false, 'currencies1', 'template 1', 6, 1, 'GBP', 'PLN', 0, NULL, NULL, NULL, 'No messages', NULL, 12, 1, null, null, 5, null, null, null, null],
            'No override template 2 reverse' => [false, 'currencies2', 'template 2', 120, 1, 'GBP', 'EUR', 1, NULL, NULL, NULL, 'No messages', NULL, 120, 0, null, null, 2, null, null, null, null],
            'Override' => [true, 'currencies1', 'override template', 12, 1, 'GBP', 'EUR', 0, NULL, NULL, NULL, 'No messages', NULL, 60, 1, 1000, 800, 5, '<div class="container-main"><div class="container "><div class="row row-header"><div class="col-xs-2 col-xs-offset-7 text-center value">BUY</div><div class="col-xs-2 col-xs-offset-1 value text-center">SELL</div></div><div id="cycle-container">[itemsTemplate]</div></div></div>', '<div class="row row-finance"><div class="col-xs-1 flags"><img class="img-circle center-block " src="[CurrencyFlag]"></div><div class="col-xs-1 value ">[NameShort]</div><div class="col-xs-2 col-xs-offset-5 text-center value">[Bid]</div><div class="col-xs-2 col-xs-offset-1 value text-center">[Ask]</div> </div>','body {     font-family: "Helvetica", "Arial", sans-serif;     line-height: 1; }  .container-main {height: 420px !important;width: 820px !important; }  .container { height: 420px !important; width: 820px !important; float: left;  margin-top: 20px; }  .row-finance { height: 60px;  background: rgba(0, 0, 0, 0.87);  margin-bottom: 20px; }  .row {margin-right: 0; margin-left: 0; }  .row-header { margin-right: -15px; margin-left: -15px; margin-bottom: 20px; }  #cycle-container { margin-left: -15px; margin-right: -15px; }  .value { font-size: 20px; padding-top: 20px; font-weight: bold; color: #fff; }  .down-arrow { font-size: 20px; color: red; padding-top: 17px; }  .up-arrow { font-size: 20px;color: green; padding-top: 17px; } .variant { font-size: 20px; padding-top: 17px; }  .flags { padding-top: 4px; }  .center-block { width: 50px; height: 50px; }', NULL]
        ];
    }

    /**
     * @group broken
     */
    public function testEdit()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create a currencies with wrapper
        $currencies = (new XiboCurrencies($this->getEntityProvider()))->create('currencies2', 'Unedited widget', 120, 1, 'GBP', 'EUR', 1, NULL, NULL, NULL, 'No messages', NULL, 50, 1, $playlistId);
        $nameNew = 'Edited widget';
        $durationNew = 80;
        $templateNew = 'currencies1';
        $notReverse = 0;
        $itemsNew = 'USD';
        # Edit currency widget and change name, duration, template, reverseConversion and items
        $response = $this->client->put('/playlist/widget/' . $currencies->widgetId, [
                'templateId' => $templateNew,
                'name' => $nameNew,
                'duration' => $durationNew,
                'useDuration' => $currencies->useDuration,
                'base' => $currencies->base,
                'items' => $itemsNew,
                'reverseConversion' => $notReverse,
                'effect' => $currencies->effect,
                'speed' => $currencies->speed,
                'backgroundColor' => $currencies->backgroundColor,
                'noRecordsMessage' => $currencies->noRecordsMessage,
                'dateFormat' => $currencies->dateFormat,
                'updateInterval' => $currencies->updateInterval,
                'durationIsPerPage' => $currencies->durationIsPerPage,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboCurrencies($this->getEntityProvider()))->getById($playlistId);
        # check if changes were correctly saved
        $this->assertSame($nameNew, $widgetOptions->name);
        $this->assertSame($durationNew, $widgetOptions->duration);
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'templateId') {
                $this->assertSame($templateNew, $option['value']);
            }
            if ($option['option'] == 'items') {
                $this->assertSame($itemsNew, $option['value']);
            }
            if ($option['option'] == 'reverseConversion') {
                $this->assertSame($notReverse, intval($option['value']));
            }
        }
    }

    /**
     * @group broken
     */
    public function testDelete()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create a currencies with wrapper
        $currencies = (new XiboCurrencies($this->getEntityProvider()))->create('currencies2', 'Unedited widget', 120, 1, 'GBP', 'EUR', 1, NULL, NULL, NULL, 'No messages', NULL, 50, 1, $playlistId);
        # Delete it
        $this->client->delete('/playlist/widget/' . $currencies->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
