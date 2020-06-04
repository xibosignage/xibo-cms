<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
        parent::tearDown();
        // This should always be the original, regardless of whether we checkout/discard/etc
        $this->layout->delete();
    }

    /**
     * Test adding a region to a Layout that has been checked out, but use the parent
     */
    public function testAddRegionCheckoutParent()
    {
        // Add region to our layout with data from regionSuccessCases
        $response = $this->sendRequest('POST','/region/' . $this->layout->layoutId, [
            'width' => 100,
            'height' => 100,
            'top' => 10,
            'left' => 10
        ]);
        $this->assertSame(422, $response->getStatusCode(), 'Status Incorrect');
        $object = json_decode($response->getBody());
        $this->assertSame(false, $object->success);
        $this->assertSame(422, $object->httpStatus);
    }

    /**
     * Test adding a region to a Layout that has been checked out, using the draft
     */
    public function testAddRegionCheckout()
    {
        // Checkout the Parent, but add a Region to the Original
        $layout = $this->getDraft($this->layout);

        // Add region to our layout with data from regionSuccessCases
        $response = $this->sendRequest('POST','/region/' . $layout->layoutId, [
            'width' => 100,
            'height' => 100,
            'top' => 10,
            'left' => 10
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
    }
}