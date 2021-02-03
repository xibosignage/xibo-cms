<?php
/*
 * Copyright (C) 2021 Xibo Signage Ltd
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
use Xibo\OAuth2\Client\Exception\XiboApiException;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class UserGroupTest
 * @package Xibo\Tests\integration
 */
class UserGroupTest extends LocalWebTestCase
{
    /**
     * Add a new group and then check it was added correctly.
     */
    public function testAdd()
    {
        $params = [
            'group' => Random::generateString(),
            'description' => Random::generateString()
        ];
        $response = $this->sendRequest('POST', '/group', $params);

        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertObjectHasAttribute('id', $object, $response->getBody());

        // Use the API to get it out again
        try {
            //$group = (new XiboUserGroup($this->getEntityProvider()))->getById($object->id);
            $group = $this->getEntityProvider()->get('/group', ['userGroupId' => $object->id])[0];

            // Check our key parts match.
            $this->assertSame($params['group'], $group['group'], 'Name does not match');
            $this->assertSame($params['description'], $group['description'], 'Description does not match');
        } catch (XiboApiException $e) {
            $this->fail('Group not found. e = ' . $e->getMessage());
        }
    }
}
