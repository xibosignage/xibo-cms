<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015-2018 Spring Signage Ltd
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
namespace Xibo\Tests\Integration;

use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class PlaylistTest
 * @package Xibo\Tests\Integration
 */
class PlaylistWidgetListTest extends LocalWebTestCase
{
    /** @var XiboPlaylist */
    private $playlist;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();

        // Create a Playlist


        // Assign some Widgets
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Delete the Playlist

        parent::tearDown();
    }

	/**
     * List all items in playlist
     * @group broken
     */
    public function testGetWidget()
    {
        // Search widgets on our playlist
        $this->client->get('/playlist/widget', [
        	'playlistId' => $this->playlist->playlistId
        ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }
}
