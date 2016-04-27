<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (AboutTest.php)
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
     */
    public function testVersion()
    {
        $response = $this->client->get('/about');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($response);

        $response = json_decode($this->client->response->body());

        $this->assertSame(200, $response->status);
        $this->assertSame(false, $response->grid);
        $this->assertNotEmpty($response->data, 'Empty Data');
        $this->assertNotEmpty($response->data->version, 'Empty Version');
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

        $me = $provider->getResourceOwner($token);

        $this->assertNotNull($me);
        $this->assertArrayHasKey('userId', $me->toArray());
        $this->assertNotEmpty($me->getId());
        $this->assertNotEquals(0, $me->getId());
    }
}
