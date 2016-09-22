<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (PlaylistTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\Tests\LocalWebTestCase;

class PlaylistTest extends LocalWebTestCase
{
	/**
     * List all items in playlist
     * @group broken
     */
    public function testGetPlaylist()
    {
        # Create layout with random name
        $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,300,75,125);
        # Search widgets on our playlist
        if (count($region->playlists) <= 0) throw new \Xibo\Exception\NotFoundException('No playlists assigned to the region');
        $this->client->get('/playlist/widget' ,[
        	'playlistId' => $region->playlists[0]->playlistId
        	]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        # Clean up
        $layout->delete();
    }

    /**
     * Assign file to playlist
     * @group broken
     */
    public function testAssign()
    {
    	# Create layout with random name
        $name = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name, 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 200,300,75,125);
        if (count($region->playlists) <= 0) throw new \Xibo\Exception\NotFoundException('No playlists assigned to the region');
        # Create media
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API image 45', PROJECT_ROOT . '/tests/resources/xts-night-001.jpg');
        # Assign media to a playlist
        $this->client->post('/playlist/library/assign/' . $region->playlists[0]->playlistId, [
        	'media' => [$media->mediaId],
        	]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        # Clean up
        $layout->delete();
        $media->delete();
    }
}
