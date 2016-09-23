<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (AuditLogTest.php)
 */

namespace Xibo\Tests\Integration;

/**
 * Class FaultTest
 * @package Xibo\Tests\Integration
 */
class FaultTest extends \Xibo\Tests\LocalWebTestCase
{
    /**
     * Collect data
     * This test modifies headers and we therefore need to run in a separate process
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCollect()
    {
        $this->client->get('/fault/collect', ['outputLog' => 'on']);

        $this->assertSame(200, $this->client->response->status());
    }

    /**
     * test turning debug on
     */
    public function testDebugOn()
    {
        $this->client->put('/fault/debug/on');
        $this->assertSame(200, $this->client->response->status());
    }

    /**
     * test turning debug off
     */
    public function testDebugOff()
    {
        $this->client->put('/fault/debug/off');
        $this->assertSame(200, $this->client->response->status());
    }
}
