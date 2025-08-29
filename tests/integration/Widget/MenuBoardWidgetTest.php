<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Tests\Integration\Widget;

use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class LocalVideoWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class MenuBoardWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var int */
    protected $widgetId;

    private $menuBoard;
    private $menuBoardCategory;
    private $menuBoardProduct;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup for ' . get_class($this) .' Test');

        // Create a Layout
        $this->publishedLayout = $this->createLayout();

        // Checkout
        $layout = $this->getDraft($this->publishedLayout);

        // Create a Widget for us to edit.
        $response = $this->getEntityProvider()->post('/playlist/widget/menuboard/' . $layout->regions[0]->regionPlaylist->playlistId);

        $this->menuBoard = $this->getEntityProvider()->post('/menuboard', [
            'name' => 'phpunit Menu board',
            'description' => 'Description for test Menu Board'
        ]);

        $this->menuBoardCategory = $this->getEntityProvider()->post('/menuboard/' . $this->menuBoard['menuId'] . '/category', [
            'name' => 'phpunit Menu Board Category'
        ]);

        $this->menuBoardProduct = $this->getEntityProvider()->post('/menuboard/' . $this->menuBoardCategory['menuCategoryId'] . '/product', [
            'name' => 'phpunit Menu Board Product',
            'price' => '$11.11'
        ]);

        $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'step' => 1,
            'menuId' => $this->menuBoard['menuId'],
            'templateId' => 'menuboard1'
        ]);

        $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'step' => 2,
            'menuBoardCategories_1' => [$this->menuBoardCategory['menuCategoryId']]
        ]);

        $this->widgetId = $response['widgetId'];
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Delete the Layout we've been working with
        $this->deleteLayout($this->publishedLayout);

        if ($this->menuBoard['menuId'] !== null) {
            $this->getEntityProvider()->delete('/menuboard/' . $this->menuBoard['menuId']);
        }

        parent::tearDown();

        $this->getLogger()->debug('Tear down for ' . get_class($this) .' Test');
    }

    public function testEdit()
    {
        $response = $this->sendRequest('PUT', '/playlist/widget/' . $this->widgetId, [
            'name' => 'Test Menu Board Widget',
            'duration' => 60,
            'useDuration' => 1,
            'showUnavailable' => 0,
            'productsHighlight' => [$this->menuBoardProduct['menuProductId']]
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        $widget = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId])[0];

        $this->assertSame(60, $widget['duration']);
        foreach ($widget['widgetOptions'] as $option) {
            if ($option['option'] == 'showUnavailable') {
                $this->assertSame(0, intval($option['value']));
            } elseif ($option['option'] == 'name') {
                $this->assertSame('Test Menu Board Widget', $option['value']);
            } elseif ($option['option'] == 'productsHighlight') {
                $this->assertSame([$this->menuBoardProduct['menuBoardProductId']], $option['value']);
            }
        }
    }
}
