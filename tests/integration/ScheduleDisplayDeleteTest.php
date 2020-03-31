<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
namespace Xibo\Tests\Integration;

use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ScheduleDisplayDeleteTest
 * @package Xibo\Tests\Integration
 */
class ScheduleDisplayDeleteTest extends LocalWebTestCase
{
    use DisplayHelperTrait;
    use LayoutHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboDisplay */
    protected $display;

    /** @var XiboDisplay */
    protected $display2;

    /** @var XiboSchedule */
    protected $event;

    // <editor-fold desc="Init">
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        // We need 2 displays
        $this->display = $this->createDisplay();
        $this->display2 = $this->createDisplay();

        // This is the remaining display we will test for the schedule
        $this->displaySetLicensed($this->display2);

        // 1 Layout
        $this->layout = $this->createLayout();

        // 1 Schedule
        $this->event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            Carbon::now()->addSeconds(7200)->format(DateFormatHelper::getSystemFormat()),
            $this->layout->campaignId,
            [$this->display->displayGroupId, $this->display2->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0,
            0
        );
    }
    
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Delete the Layout and Remaining Display
        $this->deleteDisplay($this->display2);
        $this->deleteLayout($this->layout);

        parent::tearDown();
    }
    //</editor-fold>

    /**
     * Do the test
     */
    public function test()
    {
        // Delete 1 display
        $this->sendRequest('DELETE','/display/' . $this->display->displayId);

        // Test to ensure the schedule remains
        $schedule = $this->getXmdsWrapper()->Schedule($this->display2->license);

        $this->assertContains('file="' . $this->layout->layoutId . '"', $schedule, 'Layout not scheduled');
    }
}
