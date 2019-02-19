<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015-2018 Spring Signage Ltd
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

namespace Xibo\Tests\Integration;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class TemplateTest
 * @package Xibo\Tests
 */
class TemplateTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

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
        # Create random name and new layout
        $layout = $this->createLayout();

        # Generate second random name
        $name2 = Random::generateString(8, 'phpunit');

        # Create template using our layout and new name
        $this->client->post('/template/' . $layout->layoutId, [
            'name' => $name2,
            'includeWidgets' =>1,
            'tags' => $layout->tags,
            'description' => $layout->description 
        ]);

        # Check if successful
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        # Check if it has edited name
        $this->assertSame($name2, $object->data->layout);

        $templateId = $object->id;

        # delete template as we no longer need it
        $template = (new XiboLayout($this->getEntityProvider()))->getByTemplateId($templateId);
        $template->delete();

        # delete layout as we no longer need it
        $layout->delete();
    }
}
