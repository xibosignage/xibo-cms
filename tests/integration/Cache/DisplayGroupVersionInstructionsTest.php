<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017-18 Spring Signage Ltd
 * (DisplayGroupVersionInstructionsTest.php)
 */


namespace Xibo\Tests\integration\Cache;

use Xibo\Entity\Display;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class DisplayGroupVersionInstructionsTest
 * @package Xibo\Tests\integration\Cache
 */
class DisplayGroupVersionInstructionsTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var XiboLibrary */
    protected $media;

    /** @var XiboDisplay */
    protected $display;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for Cache ' . get_class() .' Test');

        // Add a media item
        $this->media = (new XiboLibrary($this->getEntityProvider()))
            ->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/Xibo_for_Android_v1.7_R61.apk');

        // Create a Display
        $this->display = $this->createDisplay();

        $this->displaySetStatus($this->display, Display::$STATUS_DONE);
        $this->displaySetLicensed($this->display);

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        parent::tearDown();

        // Delete the Layout we've been working with
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
        // Make sure our Display is already DONE
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Add the Layout we have prepared to the Display Group
        $this->client->post('/displaygroup/' . $this->display->displayGroupId . '/version', [
            'mediaId' => $this->media->mediaId
        ]);

        // Validate the display status afterwards
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_PENDING), 'Display Status isnt as expected');

        // Validate that XMR has been called.
        $this->assertTrue(in_array($this->display->displayId, $this->getPlayerActionQueue()), 'Player action not present');

        // Ensure the Version Instructions are present on the Register Display call
        // Register our display
        $register = $this->getXmdsWrapper()->RegisterDisplay($this->display->license,
            $this->display->license,
            'windows',
            null,
            null,
            null,
            '00:16:D9:C9:AL:69',
            $this->display->xmrChannel,
            $this->display->xmrPubKey
        );

        $this->assertContains($this->media->storedAs, $register, 'Version information not in Register');
    }
}