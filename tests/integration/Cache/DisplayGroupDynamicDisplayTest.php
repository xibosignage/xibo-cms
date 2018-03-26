<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017-18 Spring Signage Ltd
 * (DisplayGroupDynamicDisplayTest.php)
 */


namespace Xibo\Tests\integration\Cache;

use Xibo\Entity\Display;
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

        $this->getLogger()->debug('Setup test for Cache ' . get_class() .' Test');

        // Create a Layout
        $this->layout = $this->createLayout();

        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $this->layout->regions[0]->playlists[0]['playlistId'], [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1
        ]);

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
            date('Y-m-d H:i:s', time()+3600),
            date('Y-m-d H:i:s', time()+7200),
            $this->layout->campaignId,
            [$this->displayGroup->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0
        );

        $this->getLogger()->debug('Schedule created with ID ' . $event->eventId);

        // Create a Display
        $this->display = $this->createDisplay();

        // Our display should already be in our group via its name
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

        // Rename the display
        $this->client->put('/display/' . $this->display->displayId, [
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

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_PENDING), 'Display Status isnt as expected');

        // Validate that XMR has been called.
        $this->assertTrue(in_array($this->display->displayId, $this->getPlayerActionQueue()), 'Player action not present');
    }
}