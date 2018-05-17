<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (PlaylistReorderTest.php)
 */


namespace Xibo\Tests\integration\Cache;


use Xibo\Entity\Display;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\OAuth2\Client\Entity\XiboText;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class PlaylistReorderTest
 * @package Xibo\Tests\integration\Cache
 */
class PlaylistReorderTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    protected $widget1;
    protected $widget2;

    /** @var XiboDisplay */
    protected $display;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for Cache Region Edit Test');

        // Create a Layout
        $this->layout = $this->createLayout();

        // Checkout
        $layout = $this->checkout($this->layout);

        // Add a couple of text widgets to the region
        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist['playlistId'], [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1,
            'sourceId' => 1,
        ]);

        $this->widget1 = (new XiboText($this->getEntityProvider()))->hydrate($response);

        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist['playlistId'], [
            'text' => 'Widget B',
            'duration' => 100,
            'useDuration' => 1,
            'sourceId' => 1,
        ]);

        $this->widget2 = (new XiboText($this->getEntityProvider()))->hydrate($response);

        // Publish
        $this->layout = $this->publish($this->layout);

        // Set the Layout status
        $this->setLayoutStatus($this->layout, 1);

        // Create a Display
        $this->display = $this->createDisplay();

        // Schedule the Layout "always" onto our display
        //  deleting the layout will remove this at the end
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            date('Y-m-d H:i:s', time()+3600),
            date('Y-m-d H:i:s', time()+7200),
            $this->layout->campaignId,
            [$this->display->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
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

        // Delete the Display
        $this->deleteDisplay($this->display);
    }
    // </editor-fold>

    /**
     * @group cacheInvalidateTests
     */
    public function testInvalidateCache()
    {
        // Checkout
        $layout = $this->checkout($this->layout);

        // Edit region
        $this->client->post('/playlist/order/' . $layout->regions[0]->regionPlaylist['playlistId'], [
            'widgets' => [
                $this->widget1->widgetId => 2,
                $this->widget2->widgetId => 1
            ]
        ]);

        // Publish
        $this->layout = $this->publish($this->layout);

        // Check the Layout Status
        // Validate the layout status afterwards
        $this->assertTrue($this->layoutStatusEquals($this->layout, 3), 'Layout Status isnt as expected');

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Somehow test that we have issued an XMR request
        $this->assertFalse(in_array($this->display->displayId, $this->getPlayerActionQueue()), 'Player action not present');
    }
}