<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (LibraryReviseTest.php)
 */


namespace Xibo\Tests\integration\Cache;


use Xibo\Entity\Display;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class LibraryReviseTest
 *
 * Tests whether a Layout Edit updates the Cache Appropriately
 *
 * @package integration\Cache
 */
class LibraryReviseTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var XiboLibrary */
    protected $media;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboDisplay */
    protected $display;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for Cache Layout Edit Test');

        // Upload some media
        $this->media = (new XiboLibrary($this->getEntityProvider()))
            ->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/xts-flowers-001.jpg');

        // Create a Layout
        $this->layout = $this->createLayout(1);

        // Checkout
        $layout = $this->getDraft($this->layout);

        // Add it to the Layout
        (new XiboPlaylist($this->getEntityProvider()))->assign([$this->media->mediaId], 10, $layout->regions[0]->regionPlaylist->playlistId);

        // Publish
        $this->layout = $this->publish($this->layout);

        // Set the Layout status (force it)
        $this->setLayoutStatus($this->layout, 1);

        // Create a Display
        $this->display = $this->createDisplay();

        // Schedule the Layout "always" onto our display
        //  deleting the layout will remove this at the end
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            date('Y-m-d H:i:s', time()),
            date('Y-m-d H:i:s', time()+7200),
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

        $this->getLogger()->debug('Finished setup');
    }

    public function tearDown()
    {
        parent::tearDown();

        // Delete the Layout we've been working with
        $this->deleteLayout($this->layout);

        // Delete the media record
        $this->media->deleteAssigned();

        // Delete the Display
        $this->deleteDisplay($this->display);
    }
    // </editor-fold>

    /**
     * @group cacheInvalidateTests
     */
    public function testInvalidateCache()
    {
        // Make sure we're in good condition to start
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        $this->assertTrue($this->layoutStatusEquals($this->layout, 1), 'Layout status is not as expected');

        // Replace the Media
        $this->media = (new XiboLibrary($this->getEntityProvider()))
            ->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/xts-flowers-002.jpg',  $this->media->mediaId, 1, 1);

        // Validate the layout status afterwards
        $this->assertTrue($this->layoutStatusEquals($this->layout, 3), 'Layout Status isnt as expected');

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');
    }
}