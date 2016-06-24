<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TemplateTest.php)
 */

namespace Xibo\Tests\Integration;
use Xibo\Helper\Random;
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
        $name1 = Random::generateString(8, 'phpunit');
        $layout = (new XiboLayout($this->getEntityProvider()))->create($name1, 'phpunit description', '', 9);
        $name2 = Random::generateString(8, 'phpunit');

        $this->client->post('/template/' . $layout->layoutId, [
            'name' => $name2,
            'includeWidgets' =>1,
            'tags' => $layout->tags,
            'description' => $layout->description 
        ]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name2, $object->data->layout);
        $templateId = $object->id;
        // delete template as we no longer need it
        $template = (new XiboLayout($this->getEntityProvider()))->getByTemplateId($object->id);
        $template->delete();
        // delete layout as we no longer need it
        $layout->delete();
    }
}
