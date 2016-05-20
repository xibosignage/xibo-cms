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
     * List all file sin library
     */
    public function testListAll()
    {
        $this->client->get('/library');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
      //  fwrite(STDOUT, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        return $object->id;
    }

    /**
     * Add new file to library
     * @group broken
     */
    public function testAdd()
    {

    }


    /**
     * Edit specific media file
     * @group broken
     */
    public function testEdit($mediaId)
    {
        $media = $$displayProfile = (new XiboLibrary($this->getEntityProvider()))->getById($mediaId);

        $name = Random::generateString(8, 'phpunit');

        $this->client->put('/library/' . $mediaId, [
            'name' => $name,
            'duration' => 50,
            'retired' => $media->retired,
            'tags' => $media->tags,
            'updateInLayouts' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
        fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);

        return $mediaId;
    }

    /**
     * Test delete added media
     * @depends testEdit
     * @group broken
     */
    public function testDelete($mediaId)
    {
        $this->client->delete('/library/' . $mediaId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /**
     * Edit soecific media file
     * @group broken
     */
    public function testEdit2()
    {
        $name = Random::generateString(8, 'phpunit');

        $this->client->put('/library/' . 17, [
            'name' => $name,
            'duration' => 50,
            'retired' => 0,
            'tags' => '',
            'updateInLayouts' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
    }

    /**
     * Test delete specific media file
          * @group broken
     */
    public function testDelete2()
    {
        $this->client->delete('/library/' . 19);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }
}
