<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserGroupTest.php)
 */

namespace Xibo\Tests\Integration;
use Xibo\OAuth2\Client\Entity\XiboUserGroup;
use Xibo\Tests\LocalWebTestCase;
use Xibo\Helper\Random;

/**
 * Class UserGroupTest
 * @package Xibo\Tests
 */
class UserGroupTest extends LocalWebTestCase
{
	/**
	* Show all user groups
    * @group broken
	*/
    public function testGetGroups()
    {
        $this->client->get('/usergroup');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
        fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
    }
}