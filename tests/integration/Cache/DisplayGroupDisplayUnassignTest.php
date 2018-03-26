<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017-18 Spring Signage Ltd
 * (DisplayGroupDisplayUnassignTest.php)
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
 * Class DisplayGroupDisplayUnassignTest
 * @package Xibo\Tests\integration\Cache
 */
class DisplayGroupDisplayUnassignTest extends LocalWebTestCase
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

        // Build the layout
        $this->buildLayout($this->layout);

        // Create a Display Group
        $this->displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create(Random::generateString(), 'Cache Test', 0, null);

        // Schedule the Layout "always" onto our display group
        //  deleting the layout will remove this at the end
        $this->event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
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

        // Create a Display
        $this->display = $this->createDisplay();

        // Assign my Display to the Group
        $this->getEntityProvider()->post('/displaygroup/ . ' . $this->displayGroup->displayGroupId . '/display/assign', [
            'displayId' => [$this->display->displayId]
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

        // Delete the Display Group
        $this->displayGroup->delete();
    }
    // </editor-fold>

    /**
     * @group cacheInvalidateTests
     */
    public function testInvalidateCache()
    {
        // Make sure our Display is already DONE
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Unassign
        $this->client->post('/displaygroup/ . ' . $this->displayGroup->displayGroupId . '/display/unassign', [
            'displayId' => [$this->display->displayId]
        ]);

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_PENDING), 'Display Status isnt as expected');

        // Validate that XMR has been called.
        $this->assertTrue(in_array($this->display->displayId, $this->getPlayerActionQueue()), 'Player action not present');
    }
}