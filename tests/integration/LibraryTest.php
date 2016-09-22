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
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video test', PROJECT_ROOT . '/tests/resources/HLH264.mp4');

        $media->delete();
    }

    /**
     * try to add not allowed filetype
     */
    public function testAddEmpty()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $this->setExpectedException('\Xibo\OAuth2\Client\Exception\XiboApiException');

        $media = (new XiboLibrary($this->getEntityProvider()))->create('API incorrect file 2', PROJECT_ROOT . '/tests/resources/empty.txt');
    }

    /**
     * Add tags to media
     * @group broken 
     */
    public function testAddTag()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('flowers', PROJECT_ROOT . '/tests/resources/xts-flowers-001.jpg');

        $this->client->post('/media/' . $media->mediaId . '/tag', [
            'tag' => ['API']
            ]);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame('', $object->tags->tag);
    }

    /**
     * Delete tags to media
     * @group broken 
     */
    public function testDeleteTag()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('flowers', PROJECT_ROOT . '/tests/resources/xts-flowers-001.jpg');

        $this->client->delete('/media/' . $media->mediaId . '/tag', [
            'tag' => ['API']
            ]);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
    }

    /**
     * Edit media file
     */
    public function testEdit()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video 4', PROJECT_ROOT . '/tests/resources/HLH264.mp4');
        # Generate new random name
        $name = Random::generateString(8, 'phpunit');
        # Edit media file, change the name
        $this->client->put('/library/' . $media->mediaId, [
            'name' => $name,
            'duration' => 50,
            //'retired' => $media->retired,
            //'tags' => $media->tags,
            'updateInLayouts' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame($name, $object->data->name);

        $media->delete();
    }

    /**
     * Test delete added media
     */
    public function testDelete()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video 4', PROJECT_ROOT . '/tests/resources/HLH264.mp4');
        # Delete added media file
        $this->client->delete('/library/' . $media->mediaId);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /**
    * Library tidy
    * @group broken
    */
    public function testTidy()
    {
        $this->client->post('/library/tidy');
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }
}
