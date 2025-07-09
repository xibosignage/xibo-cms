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


namespace Xibo\Tests\integration\Cache;

use Carbon\Carbon;
use Xibo\Entity\DataSetColumn;
use Xibo\Entity\Display;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDataSet;
use Xibo\OAuth2\Client\Entity\XiboDataSetColumn;
use Xibo\OAuth2\Client\Entity\XiboDataSetView;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class DataSetDataEditTest
 * @package Xibo\Tests\integration\Cache
 */
class DataSetDataEditTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var XiboDataSet */
    protected $dataSet;

    /** @var DataSetColumn */
    protected $dataSetColumn;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboWidget */
    protected $widget;

    /** @var XiboDisplay */
    protected $display;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for Cache ' . get_class($this) .' Test');

        // Add a DataSet
        $this->dataSet = (new XiboDataSet($this->getEntityProvider()))->create(Random::generateString(), 'Test');

        // Add a Column
        $this->dataSetColumn = (new XiboDataSetColumn($this->getEntityProvider()))->create($this->dataSet->dataSetId,
            Random::generateString(),
            '',
            1,
            1,
            1,
            '');

        // Create a Layout
        $this->layout = $this->createLayout();

        // Checkout
        $layout = $this->getDraft($this->layout);

        // Add a couple of text widgets to the region
        $response = $this->getEntityProvider()->post('/playlist/widget/datasetview/' . $layout->regions[0]->regionPlaylist->playlistId);
        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'step' => 1,
            'dataSetId' => $this->dataSet->dataSetId
        ]);

        $this->widget = (new XiboDataSetView($this->getEntityProvider()))->hydrate($response);

        // Check in
        $this->layout = $this->publish($this->layout);

        // Set the Layout status
        $this->setLayoutStatus($this->layout, 1);

        // Create a Display
        $this->display = $this->createDisplay();

        // Schedule the Layout "always" onto our display
        //  deleting the layout will remove this at the end
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            Carbon::now()->addSeconds(7200)->format(DateFormatHelper::getSystemFormat()),
            $this->layout->campaignId,
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

    public function tearDown()
    {
        parent::tearDown();

        // Delete the Layout we've been working with
        $this->deleteLayout($this->layout);

        // Delete the DataSet
        $this->dataSet->deleteWData();

        // Delete the Display
        $this->deleteDisplay($this->display);
    }
    // </editor-fold>

    /**
     * @group cacheInvalidateTests
     */
    public function testInvalidateCache()
    {
        // Add Data to the DataSet
        $this->sendRequest('POST','/dataset/data/'. $this->dataSet->dataSetId, [
            'dataSetColumnId_' . $this->dataSetColumn->dataSetColumnId => '1'
        ]);

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_PENDING), 'Display Status isnt as expected');

        // Somehow test that we have issued an XMR request
        $this->assertTrue(in_array($this->display->displayId, $this->getPlayerActionQueue()), 'Player action not present');
    }
}