<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\Tests\LocalWebTestCase;

class MenuBoardCategoryTest extends LocalWebTestCase
{
    private $menuBoard;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        $this->menuBoard = $this->getEntityProvider()->post('/menuboard', [
            'name' => Random::generateString(10, 'phpunit'),
            'description' => 'Description for test Menu Board'
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();

        if ($this->menuBoard['menuId'] !== null) {
            $this->getEntityProvider()->delete('/menuboard/' . $this->menuBoard['menuId']);
        }
    }

    public function testListEmpty()
    {
        $response = $this->sendRequest('GET', '/menuboard/' . $this->menuBoard['menuId'] . '/categories');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertEquals(0, $object->data->recordsTotal);
    }

    public function testAdd()
    {
        $media = (new XiboLibrary($this->getEntityProvider()))->create(Random::generateString(10, 'API Image'), PROJECT_ROOT . '/tests/resources/xts-night-001.jpg');
        $name = Random::generateString(10, 'Category Add');

        $response = $this->sendRequest('POST', '/menuboard/' . $this->menuBoard['menuId'] . '/category', [
            'name' => $name,
            'mediaId' => $media->mediaId
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame($media->mediaId, $object->data->mediaId);

        $media->delete();
    }

    public function testEdit()
    {
        $menuBoardCategory = $this->getEntityProvider()->post('/menuboard/' . $this->menuBoard['menuId'] . '/category', [
            'name' => 'Test Menu Board Category Edit'
        ]);
        $name = Random::generateString(10, 'Category Edit');

        $response = $this->sendRequest('PUT', '/menuboard/' . $menuBoardCategory['menuCategoryId'] . '/category', [
            'name' => $name,
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
    }

    public function testDelete()
    {
        $menuBoardCategory = $this->getEntityProvider()->post('/menuboard/' . $this->menuBoard['menuId'] . '/category', [
            'name' => 'Test Menu Board Category Delete'
        ]);

        $response = $this->sendRequest('DELETE', '/menuboard/' . $menuBoardCategory['menuCategoryId'] . '/category');

        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status);
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
    }
}
