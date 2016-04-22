<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (StatisticsTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\Tests\LocalWebTestCase;

class StatisticsTest extends LocalWebTestCase
{

/**
*  Test the method call with default values
*/ 

    public function testListAll()
    {
        $this->client->get('/stats');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
    //    fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }


/**
*  Test the method call with custom values
*/ 

        public function testListAll2()
    {
        $this->client->get('/stats' , [
        	'fromDt' => '2016-04-14 09:00:00',
        	'toDt' => '2016-04-15 10:00:00',
        	'displayId' => 2,
        	'mediaId' => [16]
        	]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
    //    fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }
}