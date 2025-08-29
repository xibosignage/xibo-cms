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
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\OAuth2\Client\Entity\XiboText;
use Xibo\OAuth2\Client\Entity\XiboTicker;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class LayoutProofOfPlayXMLWithoutMediaTest
 * @package Xibo\Tests\integration\Cache
 */
class LayoutProofOfPlayXMLWithoutMediaTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var XiboLayout */
    protected $layoutOff;

    /** @var XiboLayout */
    protected $layoutOn;

    /** @var XiboRegion */
    protected $region;

    /** @var XiboDisplay */
    protected $display;

    /** @var XiboDisplay */
    protected $display2;

    /** @var XiboTicker */
    protected $widget;

    protected $media;

    protected $widgetId2;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for ' . get_class($this) .' Test');

        // Create a Layout with enableStat Off (by default)
        $this->layoutOff = $this->createLayout();

        // Create a Display
        $this->display = $this->createDisplay();

        // Schedule the Layout "always" onto our display
        //  deleting the layoutOff will remove this at the end
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            Carbon::now()->addSeconds(3600)->format(DateFormatHelper::getSystemFormat()),
            Carbon::now()->addSeconds(7200)->format(DateFormatHelper::getSystemFormat()),
            $this->layoutOff->campaignId,
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

        // Create a layout with enableStat On
        $this->layoutOn = (new XiboLayout($this->getEntityProvider()))->create(
            Random::generateString(8, 'phpunit'),
            'phpunit description',
            '',
            $this->getResolutionId('landscape'),
            1
        );

        // Create a Display2
        $this->display2 = $this->createDisplay();

        // Schedule the LayoutOn "always" onto our display
        //  deleting the layoutOn will remove this at the end
        $event2 = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            Carbon::now()->addSeconds(3600)->format(DateFormatHelper::getSystemFormat()),
            Carbon::now()->addSeconds(7200)->format(DateFormatHelper::getSystemFormat()),
            $this->layoutOn->campaignId,
            [$this->display2->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0,
            0
        );

        $this->displaySetStatus($this->display2, Display::$STATUS_DONE);
        $this->displaySetLicensed($this->display2);

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        // Delete the LayoutOff
        $this->deleteLayout($this->layoutOff);

        // Delete the Display
        $this->deleteDisplay($this->display);

        // Delete the LayoutOn
        $this->deleteLayout($this->layoutOn);

        // Delete the Display2
        $this->deleteDisplay($this->display2);

        parent::tearDown();
    }
    // </editor-fold>

//                Logic Table
//
//                Widget Without Media
//                LAYOUT	WIDGET		Widget stats collected?
//                    ON	ON		    YES	    Widget takes precedence     // Match - 1
//                    ON	OFF		    NO	    Widget takes precedence     // Match - 2
//                    ON	INHERIT		YES	    Inherited from Layout       // Match - 7
//                    OFF	ON		    YES	    Widget takes precedence     // Match - 1
//                    OFF	OFF		    NO	    Widget takes precedence     // Match - 2
//                    OFF	INHERIT		NO	    Inherited from Layout       // Match - 8


    /**
     * Each array is a test run
     * Format (enableStat)
     * @return array
     */
    public function layoutEnableStatOffCases()
    {
        return [
            // Layout enableStat Off options - for layout and widget and their expected result (Widget stats collected?) in enableStat (media node attribute)
            'Layout Off Media On' => [0, 'On', 1],
            'Layout Off Media Off' => [0, 'Off', 0],
            'Layout Off Media Inherit' => [0, 'Inherit', 0]
        ];
    }

    /**
     * Each array is a test run
     * Format (enableStat)
     * @return array
     */
    public function layoutEnableStatOnCases()
    {
        return [
            // Layout enableStat On options - for layout and widget and their expected result (Widget stats collected?) in enableStat (media node attribute)
            'Layout On Media On' => [1, 'On', 1],
            'Layout On Media Off' => [1, 'Off', 0],
            'Layout On Media Inherit' => [1, 'Inherit', 1]
        ];
    }

    /**
     * Edit
     * @dataProvider layoutEnableStatOffCases
     */
    public function testLayoutOff($layoutEnableStat, $widgetEnableStat, $outputEnableStat)
    {
        // Checkout
        $layoutOff = $this->getDraft($this->layoutOff);

        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layoutOff->regions[0]->regionPlaylist->playlistId);
        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'] , [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1,
            'enableStat' => $widgetEnableStat
        ]);

        $this->widget = (new XiboText($this->getEntityProvider()))->hydrate($response);

        // Publish layout
        $response = $this->sendRequest('PUT','/layout/publish/' . $this->layoutOff->layoutId, [
            'publishNow' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $response = json_decode($response->getBody(), true);

        $this->layoutOff = $this->constructLayoutFromResponse($response['data']);
        $this->getLogger()->debug($this->layoutOff->enableStat);

        // Confirm our Layout is in the Schedule
        $schedule = $this->getXmdsWrapper()->Schedule($this->display->license);
        $this->assertContains('file="' . $this->layoutOff->layoutId . '"', $schedule, 'Layout not scheduled');

        // Call Required Files
        $rf = $this->getXmdsWrapper()->RequiredFiles($this->display->license);

        // Get XML string for player
        $xmlString = $this->getXmdsWrapper()->GetFile($this->display->license, $this->layoutOff->layoutId, 'layout', 0, 0);
        $this->assertContains('<layout width="1920" height="1080" bgcolor="#000" schemaVersion="3" enableStat="'.$layoutEnableStat.'">', $xmlString );
        $this->assertContains('<media id="'.$this->widget->widgetId.'" type="text" render="native" duration="100" useDuration="1" fromDt="1970-01-01 01:00:00" toDt="2038-01-19 03:14:07" enableStat="'.$outputEnableStat.'">', $xmlString );
    }

    /**
     * Edit
     * @dataProvider layoutEnableStatOnCases
     */
    public function testLayoutOn($layoutEnableStat, $widgetEnableStat, $outputEnableStat)
    {
        // Checkout
        $layoutOn = $this->getDraft($this->layoutOn);

        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layoutOn->regions[0]->regionPlaylist->playlistId);
        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'] , [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1,
            'enableStat' => $widgetEnableStat
        ]);

        $this->widget = (new XiboText($this->getEntityProvider()))->hydrate($response);

        // Publish layout
        $response = $this->sendRequest('PUT','/layout/publish/' . $this->layoutOn->layoutId, [
            'publishNow' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $response = json_decode($response->getBody(), true);

        $this->layoutOn = $this->constructLayoutFromResponse($response['data']);
        $this->getLogger()->debug($this->layoutOn->enableStat);

        // Confirm our Layout is in the Schedule
        $schedule = $this->getXmdsWrapper()->Schedule($this->display2->license);
        $this->assertContains('file="' . $this->layoutOn->layoutId . '"', $schedule, 'Layout not scheduled');

        // Call Required Files
        $rf = $this->getXmdsWrapper()->RequiredFiles($this->display2->license);

        // Get XML string for player
        $xmlString = $this->getXmdsWrapper()->GetFile($this->display2->license, $this->layoutOn->layoutId, 'layout', 0, 0);
        $this->assertContains('<layout width="1920" height="1080" bgcolor="#000" schemaVersion="3" enableStat="'.$layoutEnableStat.'">', $xmlString );
        $this->assertContains('<media id="'.$this->widget->widgetId.'" type="text" render="native" duration="100" useDuration="1" fromDt="1970-01-01 01:00:00" toDt="2038-01-19 03:14:07" enableStat="'.$outputEnableStat.'">', $xmlString );
    }

}