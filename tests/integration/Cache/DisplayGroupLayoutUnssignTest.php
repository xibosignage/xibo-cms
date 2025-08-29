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

use Xibo\Entity\Display;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class DisplayGroupLayoutUnssignTest
 * @package Xibo\Tests\integration\Cache
 */
class DisplayGroupLayoutUnssignTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboDisplay */
    protected $display;

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

        // Build the layout
        $this->buildLayout($this->layout);

        // Create a Display
        $this->display = $this->createDisplay();

        // Assign the Layout to the Display
        $this->getEntityProvider()->post('/displaygroup/' . $this->display->displayGroupId . '/layout/assign', [
            'layoutId' => [$this->layout->layoutId]
        ]);

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
        // Make sure our Display is already DONE
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Add the Layout we have prepared to the Display Group
        $response = $this->sendRequest('POST','/displaygroup/' . $this->display->displayGroupId . '/layout/unassign', [
            'layoutId' => [$this->layout->layoutId]
        ]);

        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status);

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_PENDING), 'Display Status isnt as expected');

        // Validate that XMR has been called.
        $this->assertFalse(in_array($this->display->displayId, $this->getPlayerActionQueue()), 'Player action not present');
    }
}