<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class WidgetOnDraftsTest
 * @package Xibo\Tests\integration\Widget
 */
class WidgetOnDraftsTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var XiboLayout */
    private $layout;

    public function setup()
    {
        parent::setup();

        $this->layout = $this->createLayout();
    }

    public function tearDown()
    {
        // This should always be the original, regardless of whether we checkout/discard/etc
        $this->layout->delete();

        parent::tearDown();
    }

    /**
     * Test to try and add a widget to a Published Layout
     */
    public function testEditPublished()
    {
        // Get my Playlist
        $playlistId = $this->layout->regions[0]->regionPlaylist->playlistId;

        // Add a widget (any widget will do, it doesn't matter)
        $response = $this->sendRequest('POST','/playlist/widget/localVideo/' . $playlistId);

        $this->getLogger()->debug('Response from Widget Add is ' . $response->getBody()->getContents());

        $this->assertSame(422, $response->getStatusCode(), $response->getBody());
    }

    /**
     * Test to try and add a widget to a Published Layout
     */
    public function testEditDraft()
    {
        // Checkout the Layout
        $layout = $this->getDraft($this->layout);

        // Get my Playlist
        $playlistId = $layout->regions[0]->regionPlaylist->playlistId;

        // Add a widget (any widget will do, it doesn't matter)
        $response = $this->sendRequest('POST','/playlist/widget/localVideo/' . $playlistId);

        $this->getLogger()->debug('Response from Widget Add is ' . $response->getBody()->getContents());

        $this->assertSame(200, $response->getStatusCode(), $response);
    }
}