<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ClockTest.php)
 */

namespace Xibo\Tests\Integration;


class ClockTest extends \Xibo\Tests\LocalWebTestCase
{
    public function testView()
    {
        $response = $this->client->get('/clock');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($response);
        $response = json_decode($this->client->response->body());
        $this->assertNotEmpty($response->data);
        
    }
}
