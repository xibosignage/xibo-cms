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

namespace Xibo\Tests\integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDataSet;
use Xibo\OAuth2\Client\Entity\XiboTicker;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class DataSetTickerWidgetTest
 * @package Xibo\Tests\integration\Widget
 */
class DataSetTickerWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var int */
    protected $widgetId;

    /** @var XiboDataSet */
    protected $dataSet;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup for ' . get_class($this) .' Test');

        // Add a DataSet
        $this->dataSet = (new XiboDataSet($this->getEntityProvider()))->create(Random::generateString(), 'Test');

        // Create a Layout
        $this->publishedLayout = $this->createLayout();

        // Checkout
        $layout = $this->getDraft($this->publishedLayout);

        // Create a Widget for us to edit.
        $response = $this->getEntityProvider()->post('/playlist/widget/datasetticker/' . $layout->regions[0]->regionPlaylist->playlistId);
        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'step' => 1,
            'dataSetId' => $this->dataSet->dataSetId
        ]);

        $this->widgetId = $response['widgetId'];
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Delete the Layout we've been working with
        $this->deleteLayout($this->publishedLayout);

        // Delete the DataSet
        $this->dataSet->deleteWData();

        parent::tearDown();

        $this->getLogger()->debug('Tear down for ' . get_class($this) .' Test');
    }

    /**
     * Edit dataSet ticker
     */
    public function testEditDataset()
    {
        $this->getLogger()->debug('testEdit ' . get_class($this) .' Test');

        // Edit ticker
        $noDataMessage = 'no records found';

        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
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

        $this->assertSame(200, $response->getStatusCode(), 'Incorrect status: ' . $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        $this->getLogger()->debug('Request successful, double check contents.');

        /** @var XiboTicker $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboTicker($this->getEntityProvider()))->hydrate($response[0]);

        // check if changes were correctly saved
        $this->assertSame('Edited widget', $checkWidget->name);
        $this->assertSame(90, $checkWidget->duration);

        foreach ($checkWidget->widgetOptions as $option) {
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

        $this->getLogger()->debug('testEdit finished');
    }
}