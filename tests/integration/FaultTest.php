<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (AuditLogTest.php)
 */


class FaultTest extends \Xibo\Tests\LocalWebTestCase
{
    public function testCollect()
    {
        $this->client->get('/fault/collect');
        $this->assertSame(200, $this->client->response->status());
    }

    public function testDebugOn()
    {
        // Ensure we are
        \Xibo\Helper\Config::ChangeSetting('audit', Slim\Log::EMERGENCY);

        $this->client->put('/fault/debug/on');
        $this->assertSame(200, $this->client->response->status());

        $this->assertSame(Slim\Log::DEBUG, intval(\Xibo\Helper\Config::GetSetting('audit')));
    }

    public function testDebugOff()
    {
        // Ensure we are
        \Xibo\Helper\Config::ChangeSetting('audit', Slim\Log::DEBUG);

        $this->client->put('/fault/debug/off');
        $this->assertSame(200, $this->client->response->status());

        $this->assertSame(Slim\Log::EMERGENCY, intval(\Xibo\Helper\Config::GetSetting('audit')));
    }
}
