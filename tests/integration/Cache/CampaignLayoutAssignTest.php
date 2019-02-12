<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017-18 Spring Signage Ltd
 * (CampaignLayoutAssignTest.php)
 */


namespace Xibo\Tests\integration\Cache;

use Jenssegers\Date\Date;
use Xibo\Entity\Display;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboCampaign;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class CampaignLayoutAssignTest
 * @package Xibo\Tests\integration\Cache
 */
class CampaignLayoutAssignTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var XiboCampaign */
    protected $campaign;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboDisplay */
    protected $display;

    /** @var XiboSchedule */
    protected $event;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for Cache Campaign Layout Unassign Test');

        // Create a Layout
        $this->layout = $this->createLayout();

        // Checkout
        $layout = $this->checkout($this->layout);

        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist->playlistId, [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1
        ]);

        // Check us in again
        $this->layout = $this->publish($this->layout);

        // Build the layout
        $this->buildLayout($this->layout);

        // Create a Campaign
        $this->campaign = (new XiboCampaign($this->getEntityProvider()))->create(Random::generateString());

        // Create a Display
        $this->display = $this->createDisplay();

        // Date
        $date = Date::now();

        // Schedule the Campaign "always" onto our display
        //  deleting the layout will remove this at the end
        $this->event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            $date->format('Y-m-d H:i:s'),
            $date->addHours(3)->format('Y-m-d H:i:s'),
            $this->campaign->campaignId,
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

        // Delete the Campaign
        $this->campaign->delete();
    }
    // </editor-fold>

    /**
     * @group cacheInvalidateTests
     */
    public function testInvalidateCache()
    {
        // Make sure our Display is already DONE
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Add the Layout we have prepared to the existing Campaign
        $this->client->post('/campaign/layout/assign/' . $this->campaign->campaignId, [
            'layoutId' => [
                [
                    'layoutId' => $this->layout->layoutId,
                    'displayOrder' => 1
                ]
            ]
        ]);

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_PENDING), 'Display Status isnt as expected');

        // Validate that XMR has been called.
        $this->assertTrue(in_array($this->display->displayId, $this->getPlayerActionQueue()), 'Player action not present');
    }
}