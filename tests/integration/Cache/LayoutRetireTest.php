<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (LayoutRetireTest.php)
 */


namespace Xibo\Tests\integration\Cache;


use Xibo\Entity\Display;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class LayoutRetireTest extends LocalWebTestCase
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

        $this->getLogger()->debug('Setup test for Cache Layout Retire Test');

        // Create a Layout
        $this->layout = $this->createLayout(1);

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
        // Retire the Layout we've got created for us.
        $this->client->put('/layout/retire/' . $this->layout->layoutId, [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        // Validate the layout status
        $this->assertTrue($this->layoutStatusEquals($this->layout, 1), 'Layout Status isnt as expected');

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_PENDING), 'Display Status isnt as expected');

        // Somehow test that we have issued an XMR request
        $this->assertTrue(in_array($this->display->displayId, $this->getPlayerActionQueue()), 'Player action not present');
    }
}