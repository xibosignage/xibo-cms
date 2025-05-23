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
use Xibo\Entity\Display;
use Xibo\Helper\DateFormatHelper;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\OAuth2\Client\Entity\XiboTicker;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class GetResourceTest
 * @package Xibo\Tests\integration\Cache
 */
class GetResourceTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboRegion */
    protected $region;

    /** @var XiboDisplay */
    protected $display;

    /** @var XiboTicker */
    protected $widget;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for Cache ' . get_class($this) .' Test');

        // Create a Layout
        $this->layout = $this->createLayout();

        // Checkout
        $layout = $this->getDraft($this->layout);

        // Add a resource heavy module to the Layout (one that will download images)
        $response = $this->getEntityProvider()->post('/playlist/widget/ticker/' . $layout->regions[0]->regionPlaylist->playlistId);

        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'uri' => 'http://ceu.xibo.co.uk/mediarss/feed.xml',
            'duration' => 100,
            'useDuration' => 1,
            'sourceId' => 1,
            'templateId' => 'media-rss-with-title'
        ]);

        // Edit the Ticker to add the template
        $this->widget = (new XiboTicker($this->getEntityProvider()))->hydrate($response);

        // Checkin
        $this->layout = $this->publish($this->layout);

        // Set the Layout status
        $this->setLayoutStatus($this->layout, 3);

        // Build the Layout
        $this->buildLayout($this->layout);

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
        $this->displaySetLicensed($this->display);

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        parent::tearDown();

        // Delete the Layout we've been working with
        $this->deleteLayout($this->layout);

        // Delete the Display
        $this->deleteDisplay($this->display);
    }
    // </editor-fold>

    /**
     * @group cacheInvalidateTests
     */
    public function testInvalidateCache()
    {
        // Make sure our Layout is already status 1
        $this->assertTrue($this->layoutStatusEquals($this->layout, 1), 'Layout Status isnt as expected');

        // Make sure our Display is already DONE
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Confirm our Layout is in the Schedule
        $schedule = $this->getXmdsWrapper()->Schedule($this->display->license);

        $this->assertContains('file="' . $this->layout->layoutId . '"', $schedule, 'Layout not scheduled');

        // Call Required Files
        $rf = $this->getXmdsWrapper()->RequiredFiles($this->display->license);

        $this->assertContains('layoutid="' . $this->layout->layoutId . '"', $rf, 'Layout not in Required Files');

        // Call Get Resource
        $this->getLogger()->debug('Calling GetResource - for ' . $this->layout->layoutId . ' - ' . $this->layout->regions[0]->regionId . ' - ' . $this->widget->widgetId);

        $this->getXmdsWrapper()->GetResource($this->display->license, $this->layout->layoutId, $this->layout->regions[0]->regionId, $this->widget->widgetId);

        // Check the Layout Status
        // Validate the layout status afterwards
        $this->assertTrue($this->layoutStatusEquals($this->layout, 1), 'Layout Status isnt as expected');

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_PENDING), 'Display Status isnt as expected');
    }
}