<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TemplateTest.php)
 */

namespace Xibo\Tests\Integration;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class TemplateTest
 * @package Xibo\Tests
 */
class TemplateTest extends LocalWebTestCase
{
    /**
     * Show Templates
     */
    public function testListAll()
    {
        $this->client->get('/template');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
    }

    /**
     * Add Template
     */
    public function testAdd()
    {
        $layout = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 1]);

        $name = \Xibo\Helper\Random::generateString(8, 'phpunit');

        $this->client->post('/template/' . $layout->layoutId, [
            'name' => $name,
            'includeWidgets' =>1,
            'tags' => $layout->tags,
            'description' => $layout->description 
        ]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());
//      fwrite(STDERR, $this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->layout);
    }
}