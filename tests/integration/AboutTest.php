<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (AboutTest.php)
 */

namespace Xibo\Tests\Integration;


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
   //   fwrite(STDERR, $this->client->response->body());

        $this->assertNotEmpty($response->data);
        
    }
}
