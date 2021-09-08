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
namespace Xibo\Tests\integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class PlaylistTest
 * @package Xibo\Tests\integration
 */
class PlaylistTest extends LocalWebTestCase
{
    /** @var XiboPlaylist[] */
    private $playlists;

    /** @var XiboPlaylist */
    private $duplicateName;

    public function setup()
    {
        parent::setup();

        $this->duplicateName = (new XiboPlaylist($this->getEntityProvider()))->hydrate($this->getEntityProvider()->post('/playlist', [
            'name' => Random::generateString(5, 'playlist')
        ]));

        // Add a Playlist to use for the duplicate name test
        $this->playlists[] = $this->duplicateName;
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tearing down, removing ' . count($this->playlists));

        // Delete any Playlists we've added
        foreach ($this->playlists as $playlist) {
            $this->getEntityProvider()->delete('/playlist/' . $playlist->playlistId);
        }

        parent::tearDown();
    }

    /**
     * @return array
     */
    public function addPlaylistCases()
    {
        return [
            'Normal add' => [200, Random::generateString(5, 'playlist'), null, 0, null, null],
            'Tags add' => [200, Random::generateString(5, 'playlist'), 'test', 0, null, null],
            'Dynamic add' => [200, Random::generateString(5, 'playlist'), null, 1, 'test', null]
        ];
    }

    /**
     * @return array
     */
    public function addPlaylistFailureCases()
    {
        return [
            'Dynamic without filter' => [422, Random::generateString(5, 'playlist'), null, 1, null, null],
            'Without a name' => [422, null, null, 1, null, null]
        ];
    }

    /**
     * @dataProvider addPlaylistCases
     */
    public function testAddPlaylist($statusCode, $name, $tags, $isDynamic, $nameFilter, $tagFilter)
    {
        // Add this Playlist
        $response = $this->sendRequest('POST', '/playlist', [
            'name' => $name,
            'tags' => $tags,
            'isDynamic' => $isDynamic,
            'filterMediaName' => $nameFilter,
            'filterMediaTag' => $tagFilter
        ]);

        // Check the response headers
        $this->assertSame($statusCode, $response->getStatusCode(), 'Not successful: ' . $response->getStatusCode() . $response->getBody());

        // Make sure we have a useful body
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, 'Missing data');
        $this->assertObjectHasAttribute('id', $object, 'Missing id');

        // Add to the list of playlists to clean up
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->playlists[] = (new XiboPlaylist($this->getEntityProvider()))->hydrate((array)$object->data);
        }

        // Get the Playlists back out from the API, to double check it has been created as we expected
        /** @var XiboPlaylist $playlistCheck */
        $playlistCheck = (new XiboPlaylist($this->getEntityProvider()))->hydrate($this->getEntityProvider()->get('/playlist', ['playlistId' => $object->id])[0]);

        $this->assertEquals($name, $playlistCheck->name, 'Names are not identical');
    }

    /**
     * @dataProvider addPlaylistFailureCases
     */
    public function testAddPlaylistFailure($statusCode, $name, $tags, $isDynamic, $nameFilter, $tagFilter)
    {
        $response = $this->sendRequest('POST', '/playlist', [
            'name' => $name,
            'tags' => $tags,
            'isDynamic' => $isDynamic,
            'filterMediaName' => $nameFilter,
            'filterMediaTag' => $tagFilter
        ]);

        // Check the response headers
        $this->assertSame($statusCode, $response->getStatusCode(), 'Not successful: ' . $response->getStatusCode() . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, 'Missing data');
        $this->assertSame([], $object->data);
        $this->assertObjectHasAttribute('error', $object, 'Missing error');
        $this->assertObjectNotHasAttribute('id', $object);
    }

    /**
     * Edit test
     */
    public function testEditPlaylist()
    {
        // New name
        $newName = Random::generateString(5, 'playlist');

        // Take the duplicate name playlist, and edit it
        $response = $this->sendRequest('PUT','/playlist/' . $this->duplicateName->playlistId, [
            'name' => $newName,
            'tags' => null,
            'isDynamic' => 0,
            'nameFilter' => null,
            'tagFilter' => null
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        // Check the response headers
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getStatusCode() . $response->getBody());

        /** @var XiboPlaylist $playlistCheck */
        $playlistCheck = (new XiboPlaylist($this->getEntityProvider()))->hydrate($this->getEntityProvider()->get('/playlist', ['playlistId' =>  $this->duplicateName->playlistId])[0]);

        $this->assertEquals($newName, $playlistCheck->name, 'Names are not identical');
    }
}