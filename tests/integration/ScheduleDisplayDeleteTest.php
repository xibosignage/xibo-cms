<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2018 Spring Signage Ltd
 * (ScheduleDisplayDeleteTest.php)
 */
namespace Xibo\Tests\Integration;

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
            date('Y-m-d H:i:s', time()),
            date('Y-m-d H:i:s', time()+7200),
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
        $this->client->delete('/display/' . $this->display->displayId);

        // Test to ensure the schedule remains
        $schedule = $this->getXmdsWrapper()->Schedule($this->display2->license);

        $this->assertContains('file="' . $this->layout->layoutId . '"', $schedule, 'Layout not scheduled');
    }
}
