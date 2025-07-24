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

use Xibo\OAuth2\Client\Entity\XiboCurrencies;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class CurrenciesWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class CurrenciesWidgetTest extends LocalWebTestCase
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
        $response = $this->getEntityProvider()->post('/playlist/widget/currencies/' . $layout->regions[0]->regionPlaylist->playlistId);

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
     * Format ($overrideTemplate, $templateId, $name, $duration, $useDuration, $base, $items, $reverseConversion, $effect, $speed, $backgroundColor, $noRecordsMessage, $dateFormat, $updateInterval, $durationIsPerItem, $widgetOriginalWidth, $widgetOriginalHeight, $itemsPerPage, $mainTemplate, $itemTemplate, $styleSheet, $javaScript)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'No override template 1' => [false, 'currencies1', 'template 1', 6, 1, 'GBP', 'PLN', 0, NULL, NULL, NULL, 'No messages', NULL, 12, 1, null, null, 5, null, null, null, null],
            'No override template 2 reverse' => [false, 'currencies2', 'template 2', 120, 1, 'GBP', 'EUR', 1, NULL, NULL, NULL, 'No messages', NULL, 120, 0, null, null, 2, null, null, null, null],
            'Override' => [true, 'currencies1', 'override template', 12, 1, 'GBP', 'EUR', 0, NULL, NULL, NULL, 'No messages', NULL, 60, 1, 1000, 800, 5, '<div class="container-main"><div class="container "><div class="row row-header"><div class="col-2 offset-xs-7 text-center value">BUY</div><div class="col-2 offset-xs-1 value text-center">SELL</div></div><div id="cycle-container">[itemsTemplate]</div></div></div>', '<div class="row row-finance"><div class="col-1 flags"><img class="img-circle center-block " src="[CurrencyFlag]"></div><div class="col-1 value ">[NameShort]</div><div class="col-2 offset-xs-5 text-center value">[Bid]</div><div class="col-2 offset-xs-1 value text-center">[Ask]</div> </div>','body {     font-family: "Helvetica", "Arial", sans-serif;     line-height: 1; }  .container-main {height: 420px !important;width: 820px !important; }  .container { height: 420px !important; width: 820px !important; float: left;  margin-top: 20px; }  .row-finance { height: 60px;  background: rgba(0, 0, 0, 0.87);  margin-bottom: 20px; }  .row {margin-right: 0; margin-left: 0; }  .row-header { margin-right: -15px; margin-left: -15px; margin-bottom: 20px; }  #cycle-container { margin-left: -15px; margin-right: -15px; }  .value { font-size: 20px; padding-top: 20px; font-weight: bold; color: #fff; }  .down-arrow { font-size: 20px; color: red; padding-top: 17px; }  .up-arrow { font-size: 20px;color: green; padding-top: 17px; } .variant { font-size: 20px; padding-top: 17px; }  .flags { padding-top: 4px; }  .center-block { width: 50px; height: 50px; }', NULL]
        ];
    }

    /**
     * This test works correctly, it's marked as broken because we don't have this widget installed by default
     * @group broken
     * @dataProvider provideSuccessCases
     */
    public function testEdit($isOverride, $templateId, $name, $duration, $useDuration, $base, $items, $reverseConversion, $effect, $speed, $backgroundColor, $noRecordsMessage, $dateFormat, $updateInterval, $durationIsPerItem, $widgetOriginalWidth, $widgetOriginalHeight, $itemsPerPage, $mainTemplate, $itemTemplate, $styleSheet, $javaScript)
    {
        # Edit currency widget and change name, duration, template, reverseConversion and items
        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
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
                'durationIsPerItem' => $durationIsPerItem,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboCurrencies $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboCurrencies($this->getEntityProvider()))->hydrate($response[0]);

        # check if changes were correctly saved
        $this->assertSame($name, $checkWidget->name);
        $this->assertSame($duration, $checkWidget->duration);

        foreach ($checkWidget->widgetOptions as $option) {
            if ($option['option'] == 'templateId') {
                $this->assertSame($templateId, $option['value']);
            }
            if ($option['option'] == 'items') {
                $this->assertSame($items, $option['value']);
            }
            if ($option['option'] == 'reverseConversion') {
                $this->assertSame($reverseConversion, intval($option['value']));
            }
        }
    }
}
