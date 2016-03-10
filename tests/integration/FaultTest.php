<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (AuditLogTest.php)
 */

namespace Xibo\Tests\Integration;

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
        $this->getContainer()->configService->ChangeSetting('audit', 'emergency');

        $this->client->put('/fault/debug/on');
        $this->assertSame(200, $this->client->response->status());

        $this->assertSame('DEBUG', $this->getContainer()->configService->GetSetting('audit'));
    }

    public function testDebugOff()
    {
        // Ensure we are
        $this->getContainer()->configService->ChangeSetting('audit', 'debug');

        $this->client->put('/fault/debug/off');
        $this->assertSame(200, $this->client->response->status());

        $this->assertSame('EMERGENCY', $this->getContainer()->configService->GetSetting('audit'));
    }
}
