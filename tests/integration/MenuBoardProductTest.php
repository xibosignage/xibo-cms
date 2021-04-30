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

class MenuBoardProductTest extends LocalWebTestCase
{
    private $menuBoard;
    private $menuBoardCategory;

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

        $this->menuBoardCategory = $this->getEntityProvider()->post('/menuboard/' . $this->menuBoard['menuId'] . '/category', [
            'name' => 'Test Menu Board Category Edit'
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
        $response = $this->sendRequest('GET', '/menuboard/' . $this->menuBoardCategory['menuCategoryId'] . '/products');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertEquals(0, $object->data->recordsTotal);
    }

    public function testAdd()
    {
        $media = (new XiboLibrary($this->getEntityProvider()))->create(Random::generateString(10, 'API Image'), PROJECT_ROOT . '/tests/resources/xts-night-001.jpg');
        $name = Random::generateString(10, 'Product Add');

        $response = $this->sendRequest('POST', '/menuboard/' . $this->menuBoardCategory['menuCategoryId'] . '/product', [
            'name' => $name,
            'mediaId' => $media->mediaId,
            'price' => '$12.40',
            'description' => 'Product Description',
            'allergyInfo' => 'N/A',
            'availability' => 1,
            'productOptions' => ['small', 'medium', 'large'],
            'productValues' => ['$10.40', '$15.40', '$20.20']

        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame($media->mediaId, $object->data->mediaId);
        $this->assertSame('$12.40', $object->data->price);
        $this->assertSame('Product Description', $object->data->description);
        $this->assertSame('N/A', $object->data->allergyInfo);
        $this->assertSame(1, $object->data->availability);

        // product options are ordered by option name
        $this->assertSame($object->id, $object->data->productOptions[2]->menuProductId);
        $this->assertSame('small', $object->data->productOptions[2]->option);
        $this->assertSame('$10.40', $object->data->productOptions[2]->value);
        $this->assertSame($object->id, $object->data->productOptions[1]->menuProductId);
        $this->assertSame('medium', $object->data->productOptions[1]->option);
        $this->assertSame('$15.40', $object->data->productOptions[1]->value);
        $this->assertSame($object->id, $object->data->productOptions[0]->menuProductId);
        $this->assertSame('large', $object->data->productOptions[0]->option);
        $this->assertSame('$20.20', $object->data->productOptions[0]->value);

        $media->delete();
    }

    /**
     * @dataProvider provideFailureCases
     */
    public function testAddFailure($name, $price)
    {
        $response = $this->sendRequest('POST', '/menuboard/' . $this->menuBoardCategory['menuCategoryId'] . '/product', [
            'name' => $name,
            'price' => $price
        ]);

        # check if they fail as expected
        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode());
    }

    /**
     * Each array is a test run
     * Format (name, price, productOptions, productValues)
     * @return array
     */
    public function provideFailureCases()
    {
        return [
            'empty name' => ['', '$11', [], []],
            'empty price' => ['Test Product', null, [], []]
        ];
    }

    public function testEdit()
    {
        $menuBoardProduct = $this->getEntityProvider()->post('/menuboard/' . $this->menuBoardCategory['menuCategoryId'] . '/product', [
            'name' => 'Test Menu Board Product Edit',
            'price' => '$11.11',
            'productOptions' => ['small', 'medium', 'large'],
            'productValues' => ['$10.40', '$15.40', '$20.20']
        ]);
        $name = Random::generateString(10, 'Product Edit');

        $response = $this->sendRequest('PUT', '/menuboard/' . $menuBoardProduct['menuProductId'] . '/product', [
            'name' => $name,
            'price' => '$9.99',
            'description' => 'Product Description Edited',
            'allergyInfo' => '',
            'availability' => 1,
            'productOptions' => ['small', 'medium', 'large'],
            'productValues' => ['$8.40', '$12.40', '$15.20']
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame('$9.99', $object->data->price);
        $this->assertSame('Product Description Edited', $object->data->description);
        $this->assertSame('', $object->data->allergyInfo);
        $this->assertSame(1, $object->data->availability);

        // product options are ordered by option name
        $this->assertSame($object->id, $object->data->productOptions[2]->menuProductId);
        $this->assertSame('small', $object->data->productOptions[2]->option);
        $this->assertSame('$8.40', $object->data->productOptions[2]->value);
        $this->assertSame($object->id, $object->data->productOptions[1]->menuProductId);
        $this->assertSame('medium', $object->data->productOptions[1]->option);
        $this->assertSame('$12.40', $object->data->productOptions[1]->value);
        $this->assertSame($object->id, $object->data->productOptions[0]->menuProductId);
        $this->assertSame('large', $object->data->productOptions[0]->option);
        $this->assertSame('$15.20', $object->data->productOptions[0]->value);
    }

    public function testDelete()
    {
        $menuBoardProduct = $this->getEntityProvider()->post('/menuboard/' . $this->menuBoardCategory['menuCategoryId'] . '/product', [
            'name' => 'Test Menu Board Category Delete',
            'price' => '$11.11'
        ]);

        $response = $this->sendRequest('DELETE', '/menuboard/' . $menuBoardProduct['menuProductId'] . '/product');

        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status);
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
    }
}
