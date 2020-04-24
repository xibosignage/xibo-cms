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
use Xibo\OAuth2\Client\Exception\XiboApiException;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class LayoutLockTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var XiboLayout */
    private $layout;

    public function setup()
    {
        parent::setup();

        $this->layout = $this->createLayout();

        // Get Draft
        $layout = $this->getDraft($this->layout);

        $this->addSimpleWidget($layout);

        $this->layout = $this->publish($this->layout);

        // Set the Layout status
        $this->setLayoutStatus($this->layout, 1);
    }

    public function tearDown()
    {
        // Delete the Layout we've been working with
        $this->deleteLayout($this->layout);

        parent::tearDown();
    }

    public function testIsLockedObject()
    {
        // draft
        $layout = $this->checkout($this->layout);

        // add simple Widget via API
        $this->addSimpleWidget($layout);

        // Get the Layout object via web request
        $response = $this->sendRequest('GET', '/layout', ['layoutId' => $layout->layoutId], [], 'layoutLock');
        $body = json_decode($response->getBody());
        $layoutObject = $body[0];

        // check if the isLocked dynamic object is there and is not empty, then check the values inside of it.
        // we expect it to be locked with our LayoutId and API entryPoint
        $this->assertNotEmpty($layoutObject->isLocked);
        $this->assertSame($layout->layoutId, $layoutObject->isLocked->layoutId);
        $this->assertSame('API', $layoutObject->isLocked->entryPoint);
    }

    public function testApiToWeb()
    {
        // draft
        $layout = $this->checkout($this->layout);

        // add simple Widget via API
        $this->addSimpleWidget($layout);

        // layout should be locked for our User with API entry point for 5 min.
        // attempt to add another Widget via web request
        $response = $this->sendRequest('POST', '/playlist/widget/clock/' . $layout->regions[0]->regionPlaylist->playlistId, [
            'duration' => 100,
            'useDuration' => 1
        ], [], 'layoutLock');

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getBody());
        $this->assertSame(403, $body->httpStatus);
        $this->assertContains('Layout ID ' . $layout->layoutId . ' is locked by another User! Lock expires on:', $body->error);
    }

    public function testWebToApi()
    {
        // draft
        $layout = $this->checkout($this->layout);

        // call Layout status via web request, this will trigger the Layout Lock Middleware as well
        $this->sendRequest('GET', '/layout/status/' . $layout->layoutId, [], [], 'layoutLock');

        // attempt to add Widget via API
        try {
            $this->addSimpleWidget($layout);
        } catch (XiboApiException $exception) {
            $this->assertContains('Layout ID ' . $layout->layoutId . ' is locked by another User! Lock expires on:', $exception->getMessage());
        }
    }

}