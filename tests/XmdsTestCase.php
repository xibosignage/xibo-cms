<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class xmdsTestCase extends TestCase
{
    /** @var  ContainerInterface */
    public static $container;

    /** @var LoggerInterface */
    public static $logger;

    /** @var Client */
    public $client;

    /**
     * @inheritDoc
     */
    public function getGuzzleClient(array $requestOptions = []): Client
    {
        if ($this->client === null) {
            $this->client = new Client($requestOptions);
        }

        return $this->client;
    }

    /**
     * @param string $method
     * @param string $body
     * @param string $path
     * @param array $headers
     * @return ResponseInterface
     * @throws GuzzleException
     */
    protected function sendRequest(
        string $method = 'POST',
        string $body = '',
        string $version = '7',
        bool $httpErrors = true,
        string $path = 'http://localhost/xmds.php?v=',
        array $headers = ['HTTP_ACCEPT'=>'text/xml']
    ): ResponseInterface {
        // Create a request for tests
        return $this->client->request($method, $path . $version, [
            'headers' => $headers,
            'body' => $body,
            'http_errors' => $httpErrors
        ]);
    }

    protected function getFile(
        string $fileQuery = '',
        string $method = 'GET',
        string $basePath = 'http://localhost/xmds.php',
    ): ResponseInterface {
        // Create a request for tests
        return $this->client->request($method, $basePath . $fileQuery);
    }

    /**
     * Create a global container for all tests to share.
     * @throws \Exception
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    /**
     * Convenience function to skip a test with a reason and close output buffers nicely.
     * @param string $reason
     */
    public function skipTest(string $reason): void
    {
        $this->markTestSkipped($reason);
    }

    /**
     * @return Logger|NullLogger|LoggerInterface
     */
    public static function getLogger(): Logger|NullLogger|LoggerInterface
    {
        // Create if necessary
        if (self::$logger === null) {
            if (isset($_SERVER['PHPUNIT_LOG_TO_CONSOLE']) && $_SERVER['PHPUNIT_LOG_TO_CONSOLE']) {
                self::$logger = new Logger('TESTS', [new StreamHandler(STDERR, Logger::DEBUG)]);
            } else {
                self::$logger = new NullLogger();
            }
        }

        return self::$logger;
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function setUp(): void
    {
        self::getLogger()->debug('xmdsTestCase: setUp');
        parent::setUp();

        // Establish a local reference to the Slim app object
        $this->client = $this->getGuzzleClient();
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function tearDown(): void
    {
        self::getLogger()->debug('xmdsTestCase: tearDown');

        // Close and tidy up the app
        $this->client = null;

        parent::tearDown();
    }

    /**
     * Set the _SERVER vars for the suite
     * @param array $userSettings
     */
    public static function setEnvironment(array $userSettings = []): void
    {
        $defaults = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/',
            'QUERY_STRING' => '',
            'SERVER_NAME' => 'local.dev',
            'SERVER_PORT' => 80,
            'HTTP_ACCEPT' => 'text/xml,text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $environmentSettings = array_merge($userSettings, $defaults);

        foreach ($environmentSettings as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }
}
