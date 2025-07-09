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

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDataSet;
use Xibo\OAuth2\Client\Entity\XiboDataSetColumn;
use Xibo\OAuth2\Client\Entity\XiboDataSetView;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class DataSetViewWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var int */
    protected $widgetId;

    /** @var XiboDataSet */
    protected $dataSet;

    /** @var XiboDataSetColumn */
    protected $dataSetColumn;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup for ' . get_class($this) .' Test');

        // Add a DataSet
        $this->dataSet = (new XiboDataSet($this->getEntityProvider()))->create(Random::generateString(), 'Test');

        // Create a Column for our DataSet
        $this->dataSetColumn = (new XiboDataSetColumn($this->getEntityProvider()))->create($this->dataSet->dataSetId, Random::generateString(8, 'phpunit'),'', 2, 1, 1, '');

        // Create a Layout
        $this->publishedLayout = $this->createLayout();

        // Checkout
        $layout = $this->getDraft($this->publishedLayout);

        // Create a Widget for us to edit.
        $response = $this->getEntityProvider()->post('/playlist/widget/datasetview/' . $layout->regions[0]->regionPlaylist->playlistId);
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

    public function testEdit()
    {
        $nameNew = 'Edited Name';
        $durationNew = 80;

        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
            'dataSetColumnId' => [$this->dataSetColumn->dataSetColumnId],
            'name' => $nameNew,
            'duration' => $durationNew,
            'updateInterval' => 100,
            'rowsPerPage' => 2,
            'showHeadings' => 0,
            'upperLimit' => 0,
            'lowerLimit' => 0,
            'filter' => null,
            'ordering' => null,
            'templateId' => 'light-green',
            'overrideTemplate' => 0,
            'useOrderingClause' => 0,
            'useFilteringClause' => 0,
            'noDataMessage' => 'No Data returned',
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboDataSetView $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboDataSetView($this->getEntityProvider()))->hydrate($response[0]);

        $this->assertSame($nameNew, $checkWidget->name);
        $this->assertSame($durationNew, $checkWidget->duration);

        foreach ($checkWidget->widgetOptions as $option) {
            if ($option['option'] == 'templateId') {
                $this->assertSame('light-green', $option['value']);
            } else if ($option['option'] == 'updateInterval') {
                $this->assertSame(100, intval($option['value']));
            } else if ($option['option'] == 'name') {
                $this->assertSame($nameNew, $option['value']);
            }
        }
    }
}
