<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LibraryTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\Tests\LocalWebTestCase;

class LibraryTest extends LocalWebTestCase
{

    /**
     * List all file in library
     */
    public function testListAll()
    {
        # Get all library items
        $this->client->get('/library');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     * Add new file to library
     */
    public function testAdd()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video', PROJECT_ROOT . '/tests/resources/HLH264.mp4');
    }


    /**
     * Edit media file
     * @group broken
     */
    public function testEdit()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video', PROJECT_ROOT . '/tests/resources/HLH264.mp4');
        # Generate new random name
        $name = Random::generateString(8, 'phpunit');
        # Edit media file, change the name
        $this->client->put('/library/' . $media->mediaId, [
            'name' => $name,
            'duration' => 50,
            'retired' => $media->retired,
            'tags' => $media->tags,
            'updateInLayouts' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());

      //  $this->assertObjectHasAttribute('data', $object);
    }

    /**
     * Test delete added media
     * @depends testEdit
     * @group broken
     */
    public function testDelete()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video', PROJECT_ROOT . '/tests/resources/HLH264.mp4');
        # Delete added media file
        $this->client->delete('/library/' . $media->mediaId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }
}
