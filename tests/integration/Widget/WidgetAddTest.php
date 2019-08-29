<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Tests\integration\Widget;

use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class WidgetAddTest
 * @package Xibo\Tests\integration\Widget
 */
class WidgetAddTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $layout;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var int */
    protected $widgetId;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup for ' . get_class() .' Test');

        // Create a Layout
        $this->publishedLayout = $this->createLayout();

        // Checkout
        $this->layout = $this->getDraft($this->publishedLayout);
    }

    public function tearDown()
    {
        parent::tearDown();

        // Delete the Layout we've been working with
        $this->deleteLayout($this->publishedLayout);
    }
    // </editor-fold>

    /**
     * Test add a widget
     */
    public function testAdd()
    {
        $playlistId = $this->layout->regions[0]->regionPlaylist->playlistId;

        $this->getLogger()->debug('testAdd - ' . $playlistId);

        $this->client->post('/playlist/widget/text/' . $playlistId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());


        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        $this->getLogger()->debug('testAdd - finished.');
    }

    /**
     * Test adding a non-region specific widget using the region specific widget add call
     */
    public function testAddNonRegionSpecific()
    {
        $playlistId = $this->layout->regions[0]->regionPlaylist->playlistId;

        $this->getLogger()->debug('testAddNonRegionSpecific - ' . $playlistId);

        $this->client->post('/playlist/widget/audio/' . $playlistId);

        $this->assertSame(500, $this->client->response->status(), 'Status Code isnt correct: ' . $this->client->response->status());

        $this->getLogger()->debug('testAddNonRegionSpecific - finished.');
    }
}