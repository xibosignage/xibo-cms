<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserTest.php)
 */

namespace Xibo\Tests\Integration;
use Xibo\OAuth2\Client\Entity\XiboUser;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class UserTest
 * @package Xibo\Tests
 */
class UserTest extends LocalWebTestCase
{
    /**
     * Show me
        * @group broken
     */
    public function testGetMe()
    {
        $this->client->get('/user/me');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
    }

	/**
	* Show all users
        * @group broken
	*/
    public function testGetUsers()
    {
        $this->client->get('/user');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
    }

    /**
	* Add new user
	* @group broken
	*/

    public function testAdd()
    {
        // Wrapper, get Users Group
        

        $this->client->post('/user', [
            'userName' => 'Alex',
            'userTypeId' => 3,
            'homePageId' => 29,
            'password' => 'AlexCabbage',
            'groupId' => 3,
            'libraryQuota' => 0
        ]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertObjectHasAttribute('id', $object, $this->client->response->body());

        $this->assertSame('Alex', $object->data->userName);
        $this->assertSame(3, $object->data->userTypeId);
        $this->assertSame(29, $object->data->homePageId);
    }
}