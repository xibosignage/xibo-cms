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
use League\OAuth2\Client\Token\AccessToken;

/**
 * Class AboutTest
 * @package Xibo\Tests\Integration
 */
class AboutTest extends \Xibo\Tests\LocalWebTestCase
{
    /**
     * Shows CMS version
     * @throws \Exception
     */
    public function testVersion()
    {
        $response = $this->sendRequest('GET', '/about');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response);

        $body = json_decode($response->getBody());

        $this->assertSame(200, $body->status);
        $this->assertSame(true, $body->success);
        $this->assertSame(false, $body->grid);
        $this->assertNotEmpty($body->data, 'Empty Data');
        $this->assertNotEmpty($body->data->version, 'Empty Version');
    }

    /**
     * Test that the API is initialised and making authenticated requests.
     */
    public function testApiInitialisedTest()
    {
        $this->assertNotNull($this->getEntityProvider(), 'Entity Provider not set');
        $this->assertNotNull($this->getEntityProvider()->getProvider(), 'Provider not set');
    }

    /**
     * @depends testApiInitialisedTest
     */
    public function testApiAccessTest()
    {
        $provider = $this->getEntityProvider()->getProvider();
        $token = $provider->getAccessToken('client_credentials');

        $this->assertNotNull($token);
        $this->assertNotTrue($token->hasExpired(), 'Expired Token');
        $this->assertInstanceOf('League\OAuth2\Client\Token\AccessToken', $token);

        return $token;
    }

    /**
     * @param AccessToken $token
     * @depends testApiAccessTest
     */
    public function testApiUserTest(AccessToken $token)
    {
        $provider = $this->getEntityProvider()->getProvider();

        try {
            $me = $provider->getResourceOwner($token);
        } catch (\Exception $exception) {
            $this->fail('API connect not successful: ' . $exception->getMessage());
        }

        $this->assertNotNull($me);
        $this->assertArrayHasKey('userId', $me->toArray());
        $this->assertNotEmpty($me->getId());
        $this->assertNotEquals(0, $me->getId());
    }
}
