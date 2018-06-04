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

use Xibo\OAuth2\Client\Entity\XiboFinance;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class FinanceWidgetTest extends LocalWebTestCase
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
    public function testAdd($isOverride, $templateId, $name, $duration, $useDuration, $item, $effect, $speed, $backgroundColor, $noRecordsMessage, $dateFormat, $updateInterval, $durationIsPerItem, $javaScript, $template, $styleSheet, $yql, $resultIdentifier)
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];
        
        if ($isOverride) {
            $response = $this->client->post('/playlist/widget/finance/' . $playlistId, [
                'templateId' => $templateId,
                'name' => $name,
                'duration' => $duration,
                'useDuration' => $useDuration,
                'item' => $item,
                'effect' => $effect,
                'speed' => $speed,
                'backgroundColor' => $backgroundColor,
                'noRecordsMessage' => $noRecordsMessage,
                'dateFormat' => $dateFormat,
                'updateInterval' => $updateInterval,
                'overrideTemplate' => 1,
                'durationIsPerItem' => $durationIsPerItem,
                'javaScript' => $javaScript,
                'template' => $template,
                'styleSheet' => $styleSheet,
                'yql' => $yql,
                'resultIdentifier' => $resultIdentifier
            ]);
        } else {
            $response = $this->client->post('/playlist/widget/finance/' . $playlistId, [
                'templateId' => $templateId,
                'name' => $name,
                'duration' => $duration,
                'useDuration' => $useDuration,
                'item' => $item,
                'effect' => $effect,
                'speed' => $speed,
                'backgroundColor' => $backgroundColor,
                'noRecordsMessage' => $noRecordsMessage,
                'dateFormat' => $dateFormat,
                'updateInterval' => $updateInterval,
                'overrideTemplate' => 0,
                'durationIsPerItem' => $durationIsPerItem,
                'javaScript' => $javaScript
            ]);
        }

        $widgetOptions = (new XiboFinance($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($name, $widgetOptions->name);
        $this->assertSame($duration, $widgetOptions->duration);
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'templateId') {
                $this->assertSame($templateId, $option['value']);
            }
            if ($option['option'] == 'item') {
                $this->assertSame($item, $option['value']);
            }
            if ($option['option'] == 'updateInterval') {
                $this->assertSame($updateInterval, intval($option['value']));
            }
        }

    }

    /**
     * Each array is a test run
     * Format ($isOverride, $templateId, $name, $duration, $useDuration, $item, $effect, $speed, $backgroundColor, $noRecordsMessage, $dateFormat, $updateInterval, $durationIsPerItem, $javaScript, $template, $styleSheet, $yql, $resultIdentifier)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'No override currency' => [false, 'currency-simple', 'Currency', 6, 1, 'EURUSD,GBPUSD,PLNGBP', NULL, NULL, NULL, 'No messages', NULL, 12, 1, null, null, null, null, null],
            'No override stock' => [false, 'stock-simple', 'Stock', 120, 1, 'GOOGL', NULL, NULL, NULL, 'No messages', NULL, 120, 0, null, null, null, null, null],
            'Override' => [true, 'stock-simple', 'Stock overriden', 40, 1, 'GOOGL,AAPL', 'fade', 5, NULL, 'No messages', 'd M', 20, 1, null, '[Name] -- [symbol] [Change] [DaysRange] [StockExchange]', null, 'select * from yahoo.finance.quote where symbol in ([item])', 'quote']
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

        # Create a finance with wrapper
        $finance = (new XiboFinance($this->getEntityProvider()))->create('currency-simple', 'Currency', 6, 1, 'EURUSD,GBPUSD', NULL, NULL, NULL, 'No messages', NULL, 12, 1, $playlistId);
        $nameNew = 'Edited widget';
        $durationNew = 80;
        $templateNew = 'stock-simple';
        $itemNew = 'GOOGL';
        # Edit finance widget and change name, duration, template and item
        $response = $this->client->put('/playlist/widget/' . $finance->widgetId, [
                'templateId' => $templateNew,
                'name' => $nameNew,
                'duration' => $durationNew,
                'useDuration' => $finance->useDuration,
                'item' => $itemNew,
                'effect' => $finance->effect,
                'speed' => $finance->speed,
                'backgroundColor' => $finance->backgroundColor,
                'noRecordsMessage' => $finance->noRecordsMessage,
                'dateFormat' => $finance->dateFormat,
                'updateInterval' => $finance->updateInterval,
                'overrideTemplate' => 0,
                'durationIsPerItem' => $finance->durationIsPerItem,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboFinance($this->getEntityProvider()))->getById($playlistId);
        # check if changes were correctly saved
        $this->assertSame($nameNew, $widgetOptions->name);
        $this->assertSame($durationNew, $widgetOptions->duration);
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'templateId') {
                $this->assertSame($templateNew, $option['value']);
            }
            if ($option['option'] == 'item') {
                $this->assertSame($itemNew, $option['value']);
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

        # Create a finance with wrapper
        $finance = (new XiboFinance($this->getEntityProvider()))->create('currency-simple', 'Currency', 6, 1, 'EURUSD,GBPUSD', NULL, NULL, NULL, 'No messages', NULL, 12, 1, $playlistId);
        # Delete it
        $this->client->delete('/playlist/widget/' . $finance->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
