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
    /*
     * @group broken
     */

    public function Collect()
    {
        $this->client->get('/fault/collect');
        $this->assertSame(200, $this->client->response->status());
    }

    /*
     * @group broken
     */
    public function DebugOn()
    {
        $this->client->put('/fault/debug/on');
        $this->assertSame(200, $this->client->response->status());
    }

    /*
     * @group broken
     */
    public function DebugOff()
    {
        $this->client->put('/fault/debug/off');
        $this->assertSame(200, $this->client->response->status());
    }
}
