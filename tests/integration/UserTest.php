<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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
     */
    public function testGetMe()
    {
        $response = $this->sendRequest('GET','/user/me');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());

        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame('phpunit', $object->data->userName);
    }

	/**
	* Show all users
	*/
    public function testGetUsers()
    {
        $response = $this->sendRequest('GET','/user');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());

        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object);
    }

    /**
	* Add new user
	*/
    public function testAdd()
    {
        $group = $this->getEntityProvider()->get('/group', ['userGroup' => 'Users'])[0];
        $userName = Random::generateString();

        $response = $this->sendRequest('POST','/user', [
            'userName' => $userName,
            'userTypeId' => 3,
            'homePageId' => 'icondashboard.view',
            'homeFolderId' => 1,
            'password' => 'newUserPassword',
            'groupId' => $group['groupId'],
            'libraryQuota' => 0
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getBody());

        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertObjectHasAttribute('id', $object, $response->getBody());

        $this->assertSame($userName, $object->data->userName);
        $this->assertSame(3, $object->data->userTypeId);
        $this->assertSame('icondashboard.view', $object->data->homePageId);

        $userCheck = (new XiboUser($this->getEntityProvider()))->getById($object->id);
        $userCheck->delete();
    }

    public function testAddEmptyPassword()
    {
        $group = $this->getEntityProvider()->get('/group', ['userGroup' => 'Users'])[0];

        $response = $this->sendRequest('POST', '/user', [
            'userName' => Random::generateString(),
            'userTypeId' => 3,
            'homePageId' => 'icondashboard.view',
            'homeFolderId' => 1,
            'password' => null,
            'groupId' => $group['groupId'],
            'libraryQuota' => 0
        ]);

        $this->assertSame(422, $response->getStatusCode(), $response->getBody());
    }
}