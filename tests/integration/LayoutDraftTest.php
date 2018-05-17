<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Spring Signage Ltd
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
 * (LayoutDraftTest.php)
 */
namespace Xibo\Tests\integration;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class LayoutDraftTest
 * @package Xibo\Tests\integration
 */
class LayoutDraftTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var XiboLayout */
    private $layout;

    public function setup()
    {
        parent::setup();

        $this->layout = $this->createLayout();
    }

    public function tearDown()
    {
        // This should always be the original, regardless of whether we checkout/discard/etc
        $this->layout->delete();

        parent::tearDown();
    }

    /**
     * Test adding a region to a Layout that has not been checked out.
     */
    public function testAddRegionNoCheckout()
    {
        // Add region to our layout with data from regionSuccessCases
        $this->client->post('/region/' . $this->layout->layoutId, [
            'width' => 100,
            'height' => 100,
            'top' => 10,
            'left' => 10
        ]);

        $this->assertSame(500, $this->client->response->status(), $this->client->response->getBody());
    }

    /**
     * Test adding a region to a Layout that has been checked out, but use the parent
     */
    public function testAddRegionCheckoutParent()
    {
        // Checkout the Parent, but add a Region to the Original
        $this->checkout($this->layout);

        // Add region to our layout with data from regionSuccessCases
        $response = $this->client->post('/region/' . $this->layout->layoutId, [
            'width' => 100,
            'height' => 100,
            'top' => 10,
            'left' => 10
        ]);

        $this->assertSame(500, $this->client->response->status(), 'Status Incorrect');
    }

    /**
     * Test adding a region to a Layout that has been checked out, using the draft
     */
    public function testAddRegionCheckout()
    {
        // Checkout the Parent, but add a Region to the Original
        $layout = $this->checkout($this->layout);

        // Add region to our layout with data from regionSuccessCases
        $this->client->post('/region/' . $layout->layoutId, [
            'width' => 100,
            'height' => 100,
            'top' => 10,
            'left' => 10
        ]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
    }
}