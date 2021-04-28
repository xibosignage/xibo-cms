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
use Xibo\Tests\LocalWebTestCase;

class MenuBoardTest extends LocalWebTestCase
{
    private $menuBoardId;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
    }

    public function tearDown()
    {
        parent::tearDown();

        if ($this->menuBoardId !== null) {
            $this->getEntityProvider()->delete('/menuboard/' . $this->menuBoardId);
        }
    }

    public function testListAll()
    {
        $response = $this->sendRequest('GET', '/menuboards');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
    }

    public function testAdd()
    {
        $name = Random::generateString(10, 'MenuBoard Add');
        $response = $this->sendRequest('POST', '/menuboard', [
            'name' => $name,
            'description' => 'Description for test Menu Board Add'
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame('Description for test Menu Board Add', $object->data->description);

        $this->menuBoardId = $object->id;
    }

    public function testEdit()
    {
        $menuBoard = $this->getEntityProvider()->post('/menuboard', [
            'name' => 'Test Menu Board Edit',
            'description' => 'Description for test Menu Board Edit'
        ]);
        $name = Random::generateString(10, 'MenuBoard Edit');
        $response = $this->sendRequest('PUT', '/menuboard/' . $menuBoard['menuId'], [
            'name' => $name,
            'description' => 'Test Menu Board Edited description'
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame('Test Menu Board Edited description', $object->data->description);

        $this->menuBoardId = $object->id;
    }

    public function testDelete()
    {
        $menuBoard = $this->getEntityProvider()->post('/menuboard', [
            'name' => 'Test Menu Board Delete',
            'description' => 'Description for test Menu Board Delete'
        ]);

        $response = $this->sendRequest('DELETE', '/menuboard/' . $menuBoard['menuId']);

        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status);
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
    }
}
