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
use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class DisplayGroupDynamicDisplayTest
 * @package Xibo\Tests\integration\Cache
 */
class DisplayGroupDynamicDisplayTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboDisplay */
    protected $display;

    /** @var XiboDisplayGroup */
    protected $displayGroup;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for Cache ' . get_class($this) .' Test');

        // Create a Layout
        $this->layout = $this->createLayout();

        // Checkout
        $layout = $this->getDraft($this->layout);

        // Add a simple widget
        $this->addSimpleWidget($layout);

        // Check us in again
        $this->layout = $this->publish($this->layout);

        // Create a Display Group
        // this matches all displays created by the test suite
        $this->displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create(
            Random::generateString(),
            'Cache Test',
            1,
            'phpunit');

        $this->getLogger()->debug('DisplayGroup created with ID ' . $this->displayGroup->displayGroupId);

        // Schedule the Layout "always" onto our display group
        //  deleting the layout will remove this at the end
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            Carbon::now()->addSeconds(7200)->format(DateFormatHelper::getSystemFormat()),
            $this->layout->campaignId,
            [$this->displayGroup->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0,
            0
        );

        $this->getLogger()->debug('Schedule created with ID ' . $event->eventId);

        // Create a Display
        $this->display = $this->createDisplay();

        // Run regular maintenance to add the new display to our group.
        $this->runRegularMaintenance();

        $this->getLogger()->debug('Display created with ID ' . $this->display->displayId);

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

        // Delete the Display Group
        $this->displayGroup->delete();

        // Delete the Display
        $this->deleteDisplay($this->display);
    }
    // </editor-fold>

    /**
     * @group cacheInvalidateTests
     */
    public function testInvalidateCache()
    {
        // Make sure our Display is already DONE
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        $this->getLogger()->debug('Renaming display');

        // Rename the display
        $response = $this->sendRequest('PUT','/display/' . $this->display->displayId, [
            'display' => Random::generateString(10, 'testedited'),
            'defaultLayoutId' => $this->display->defaultLayoutId,
            'auditingUntil' => null,
            'licensed' => $this->display->licensed,
            'license' => $this->display->license,
            'incSchedule' => $this->display->incSchedule,
            'emailAlert' => $this->display->emailAlert,
            'wakeOnLanEnabled' => $this->display->wakeOnLanEnabled,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        // There isn't anything directly on the display - so that will NOT trigger anything. The schedule is on the Display Group.
        $this->getLogger()->debug('Finished renaming display');

        $this->assertLessThan(300, $response->getStatusCode(), 'Non-success status code, body =' . $response->getBody()->getContents());

        // Initially we're expecting no change.
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Run regular maintenance
        $this->runRegularMaintenance();

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_PENDING), 'Display Status isnt as expected');

        // Our player action would have been sent by regular maintenance, not by the edit.
        // Make sure we don't have one here.
        $this->assertFalse(in_array($this->display->displayId, $this->getPlayerActionQueue()), 'Player action not present');
    }

    private function runRegularMaintenance()
    {
        $this->getLogger()->debug('Running Regular Maintenance');
        exec('cd /var/www/cms; php bin/run.php 2');
        $this->getLogger()->debug('Finished Regular Maintenance');
    }
}