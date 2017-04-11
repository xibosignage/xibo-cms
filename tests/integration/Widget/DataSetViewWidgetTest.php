<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetViewWidgetTest.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboDataSet;
use Xibo\OAuth2\Client\Entity\XiboDataSetColumn;
use Xibo\OAuth2\Client\Entity\XiboDataSetView;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\Tests\LocalWebTestCase;

class DataSetViewWidgetTest extends LocalWebTestCase
{

	protected $startLayouts;
    protected $startDataSets;

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

    public function testAdd()
    {
        # Create layout
        $layout = (new XiboLayout($this->getEntityProvider()))->create('DataSetView add', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create a new dataset
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create('phpunit dataset', 'phpunit description');

        $response = $this->client->post('/playlist/widget/dataSetView/' . $region->playlists[0]['playlistId'], [
            'name' => 'API dataSetView',
            'dataSetId' => $dataSet->dataSetId
            ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $widgetOptions = (new XiboDataSetView($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame('API dataSetView', $widgetOptions->name);
        $this->assertSame(60, $widgetOptions->duration);
    }

    public function testEdit()
    {
        # Create layout
        $layout = (new XiboLayout($this->getEntityProvider()))->create('DataSetView edit', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create a new dataset
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create('phpunit dataset', 'phpunit description');
        # Create dataSetView widget
        $dataSetView = (new XiboDataSetView($this->getEntityProvider()))->create('API dataSetView', $dataSet->dataSetId, $region->playlists[0]['playlistId']);
        $nameCol = Random::generateString(8, 'phpunit');
        $nameCol2 = Random::generateString(8, 'phpunit');
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        $column2 = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol2,'', 2, 1, 1, '');
        $nameNew = 'Edited Name';
        $durationNew = 80;
        $response = $this->client->put('/playlist/widget/' . $dataSetView->widgetId, [
            'dataSetColumnId' => [$column->dataSetColumnId, $column2->dataSetColumnId],
            'name' => $nameNew,
            'duration' => $durationNew,
            'updateInterval' => 100,
            'rowsPerPage' => 2,
            'showHeadings' =>0,
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
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboDataSetView($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame($nameNew, $widgetOptions->name);
        $this->assertSame($durationNew, $widgetOptions->duration);
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'templateId') {
                $this->assertSame('light-green', $option['value']);
            }
            if ($option['option'] == 'updateInterval') {
                $this->assertSame(100, intval($option['value']));
            }
        }
    }

        public function testDelete()
    {
        # Create layout
        $layout = (new XiboLayout($this->getEntityProvider()))->create('DataSetView delete Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create a new dataset
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create('phpunit dataset', 'phpunit description');
        # Create dataSetView widget
        $dataSetView = (new XiboDataSetView($this->getEntityProvider()))->create('API dataSetView', $dataSet->dataSetId, $region->playlists[0]['playlistId']);
        # Delete it
        $this->client->delete('/playlist/widget/' . $dataSetView->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
