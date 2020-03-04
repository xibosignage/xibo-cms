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
namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
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
        $this->playlist = (new XiboPlaylist($this->getEntityProvider()))->hydrate($this->getEntityProvider()->post('/playlist', [
            'name' => Random::generateString(5, 'playlist')
        ]));

        // Assign some Widgets
        $this->getEntityProvider()->post('/playlist/widget/clock/' . $this->playlist->playlistId, [
            'duration' => 100,
            'useDuration' => 1
        ]);

        $text = $this->getEntityProvider()->post('/playlist/widget/text/' .  $this->playlist->playlistId);
        $this->getEntityProvider()->put('/playlist/widget/' . $text['widgetId'], [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1
        ]);
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
     */
    public function testGetWidget()
    {
        // Search widgets on our playlist
        $response = $this->sendRequest('GET','/playlist/widget', [
        	'playlistId' => $this->playlist->playlistId
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(2, $object->data->recordsTotal);
    }
}
