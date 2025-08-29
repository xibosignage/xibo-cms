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

namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboText;
use Xibo\Tests\Helper\LayoutHelperTrait;

/**
 * Class AboutTest
 * @package Xibo\Tests\Integration
 */
class InteractiveFeaturesTest extends \Xibo\Tests\LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for ' . get_class($this));

        // Create a Layout
        $this->layout = $this->createLayout();

        // Get Draft
        $layout = $this->getDraft($this->layout);

        $this->addSimpleTextWidget($layout);

        $this->layout = $this->publish($this->layout);

        // Set the Layout status
        $this->setLayoutStatus($this->layout, 1);

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        // Delete the Layout we've been working with
        $this->deleteLayout($this->layout);

        parent::tearDown();
    }
    // </editor-fold>

    /**
     * Test Add Region Drawer
     */
    public function testAddDrawer()
    {
        $layout = $this->checkout($this->layout);
        // add Drawer Region
        $response = $this->sendRequest('POST', '/region/drawer/' . $layout->layoutId);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response);

        $body = json_decode($response->getBody());

        $this->assertSame(201, $body->status);
        $this->assertSame(true, $body->success);
        $this->assertSame(false, $body->grid);
        $this->assertNotEmpty($body->data, 'Empty Data');

        $this->assertSame(1, $body->data->isDrawer);
        $this->assertContains('drawer', $body->data->name);

        // get the layout
        $layout = $this->getEntityProvider()->get('/layout', ['layoutId' => $layout->layoutId, 'embed' => 'regions'])[0];
        // check if regions and drawers arrays are not empty
        $this->assertNotEmpty($layout['drawers']);
        $this->assertNotEmpty($layout['regions']);
    }

    /**
     * Test Add Region Drawer
     */
    public function testDeleteDrawer()
    {
        $layout = $this->checkout($this->layout);
        // add Drawer Region
        $drawer = $this->getEntityProvider()->post('/region/drawer/' . $layout->layoutId);

        // delete Drawer Region
        $response = $this->sendRequest('DELETE', '/region/' . $drawer['regionId']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response);
        $body = json_decode($response->getBody());
        $this->assertSame(204, $body->status);
        $this->assertSame(true, $body->success);
    }

    public function testListAll()
    {
        $response = $this->sendRequest('GET','/action');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
    }

    /**
     * Add Action
     * @dataProvider AddActionSuccessCases
     * @param string $source
     * @param string $triggerType
     * @param string|null $triggerCode
     * @param string $actionType
     * @param string $target
     * @param string|null $layoutCode
     */
    public function testAddActionSuccess(?string $source, ?string $triggerType, ?string $triggerCode, string $actionType, string $target, ?string $layoutCode)
    {
        $layout = $this->checkout($this->layout);
        $sourceId = null;
        $targetId = null;
        $widgetId = null;

        // Add a couple of text widgets to the region
        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist->playlistId);
        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1
        ]);

        $widget = (new XiboText($this->getEntityProvider()))->hydrate($response);

        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist->playlistId);
        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'text' => 'Widget B',
            'duration' => 100,
            'useDuration' => 1
        ]);

        $widget2 = (new XiboText($this->getEntityProvider()))->hydrate($response);

        // depending on the source from AddActionsCases, the sourceId will be different
        if ($source === 'layout') {
            $sourceId = $layout->layoutId;
        } elseif ($source === 'region') {
            $sourceId = $layout->regions[0]->regionId;
        } else {
            $sourceId = $widget->widgetId;
        }

        // depending on the target screen|region we may need targetId
        if ($target === 'region') {
            $targetId = $layout->regions[0]->regionId;
        }

        if ($actionType == 'navWidget') {
            $widgetId = $widget2->widgetId;
        }

        $response = $this->sendRequest('POST', '/action', [
            'triggerType' => $triggerType,
            'triggerCode' => $triggerCode,
            'actionType' => $actionType,
            'target' => $target,
            'targetId' => $targetId,
            'widgetId' => $widgetId,
            'layoutCode' => $layoutCode,
            'source' => $source,
            'sourceId' => $sourceId,
            'layoutId' => $layout->layoutId
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response);

        $body = json_decode($response->getBody());
        $this->assertSame(201, $body->status);
        $this->assertSame(true, $body->success);
        $this->assertSame(false, $body->grid);

        $this->assertNotEmpty($body->data, 'Empty Data');
        $this->assertSame($layout->layoutId, $body->data->layoutId);
        $this->assertSame($sourceId, $body->data->sourceId);
        $this->assertSame($triggerType, $body->data->triggerType);
        $this->assertSame($triggerCode, $body->data->triggerCode);
        $this->assertSame($actionType, $body->data->actionType);
        $this->assertSame($target, $body->data->target);
        $this->assertSame($targetId, $body->data->targetId);
    }

    /**
     * Each array is a test run
     * Format (string $source, string $triggerType, string|null $triggerCode, string $actionType, string $target, string LayoutCode)
     * @return array
     */
    public function AddActionSuccessCases()
    {
        return [
            'Layout' => ['layout', 'touch', 'trigger code', 'next', 'screen', null],
            'Layout with region target' => ['layout', 'touch', null, 'previous', 'region', null],
            'Region' => ['region', 'webhook', 'test', 'previous', 'screen', null],
            'Region with region target' => ['region', 'touch', null, 'previous', 'region', null],
            'Widget' => ['widget', 'touch', null, 'next', 'screen', null],
            'Widget with region target' => ['widget', 'touch', null, 'next', 'region', null],
            'Navigate to Widget' => ['layout', 'touch', null, 'navWidget', 'screen', null],
            'Navigate to Layout with code' => ['layout', 'touch', null, 'navLayout', 'screen', 'CodeIdentifier'],
            'Web UI' => [null, null, null, 'next', 'screen', null]
        ];
    }

    public function testEditAction()
    {
        $layout = $this->checkout($this->layout);
        $action = $this->getEntityProvider()->post('/action', [
            'actionType' => 'previous',
            'target' => 'screen',
            'layoutId' => $layout->layoutId
        ]);

        $response = $this->sendRequest('PUT', '/action/' . $action['actionId'], [
            'source' => 'layout',
            'sourceId' => $layout->layoutId,
            'triggerType' => 'webhook',
            'triggerCode' => 'new code',
            'actionType' => 'next',
            'target' => 'region',
            'targetId' => $layout->regions[0]->regionId
        ], ['Content-Type' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response);

        $body = json_decode($response->getBody());
        $this->assertSame(200, $body->status);
        $this->assertSame(true, $body->success);
        $this->assertSame(false, $body->grid);

        $this->assertNotEmpty($body->data, 'Empty Data');
        $this->assertSame($layout->layoutId, $body->data->sourceId);
        $this->assertSame($layout->layoutId, $body->data->layoutId);
        $this->assertSame('webhook', $body->data->triggerType);
        $this->assertSame('new code', $body->data->triggerCode);
        $this->assertSame('next', $body->data->actionType);
        $this->assertSame('region', $body->data->target);
        $this->assertSame($layout->regions[0]->regionId, $body->data->targetId);
    }

    public function testDeleteAction()
    {
        $layout = $this->checkout($this->layout);

        $action = $this->getEntityProvider()->post('/action', [
            'triggerType' => 'webhook',
            'triggerCode' => 'test',
            'actionType' => 'previous',
            'target' => 'screen',
            'layoutId' => $layout->layoutId
        ]);

        $response = $this->sendRequest('DELETE', '/action/' . $action['actionId']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response);

        $body = json_decode($response->getBody());
        $this->assertSame(204, $body->status);
        $this->assertSame(true, $body->success);
        $this->assertSame(false, $body->grid);

        // check if one action remains with our Layout Id.
        $actions = $this->getEntityProvider()->get('/action', ['sourceId' => $layout->layoutId]);
        $this->assertSame(0, count($actions));
    }

    /**
     * Add Action
     * @dataProvider editActionFailureCases
     * @param string $source
     * @param string $triggerType
     * @param string|null $triggerCode
     * @param string $actionType
     * @param string $target
     */
    public function testEditActionFailure(string $source, string $triggerType, ?string $triggerCode, string $actionType, string $target)
    {
        $layout = $this->checkout($this->layout);
        $action = $this->getEntityProvider()->post('/action', [
            'actionType' => 'previous',
            'target' => 'screen',
            'layoutId' => $layout->layoutId
        ]);

        $targetId = null;
        $widgetId = null;
        $layoutCode = null;

        if ($source === 'layout') {
            $sourceId = $layout->layoutId;
        } elseif ($source === 'region') {
            $sourceId = $layout->regions[0]->regionId;
        } else {
            $sourceId = null;
        }

        $response = $this->sendRequest('PUT', '/action/' . $action['actionId'], [
            'triggerType' => $triggerType,
            'triggerCode' => $triggerCode,
            'actionType' => $actionType,
            'target' => $target,
            'targetId' => $targetId,
            'source' => $source,
            'sourceId' => $sourceId
        ]);

        $body = json_decode($response->getBody());

        // in other failure cases, we expect to get invalidArgument exception.
        $this->assertSame(422, $response->getStatusCode());

        // get the error message for cases and make sure we return correct one.
        if ($source === 'playlist') {
            $this->assertSame('Invalid source', $body->error);
        }

        // wrong trigger type case
        if ($triggerType === 'notExistingType') {
            $this->assertSame('Invalid trigger type', $body->error);
        }

        // wrong trigger type case
        if ($actionType === 'wrongAction') {
            $this->assertSame('Invalid action type', $body->error);
        }

        // wrong target case
        if ($target === 'world') {
            $this->assertSame('Invalid target', $body->error);
        }

        // test case when we have target set to region, but we don't set targetId to any regionId
        if ($target === 'region') {
            $this->assertSame('Please select a Region', $body->error);
        }

        // trigger code in non layout
        if ($triggerType === 'webhook' && $triggerCode === null) {
            $this->assertSame('Please provide trigger code', $body->error);
        }

        // navWidget without widgetId
        if ($actionType === 'navWidget' && $widgetId == null) {
            $this->assertSame('Please select a Widget', $body->error);
        }

        // navLayout without layoutCode
        if ($actionType === 'navLayout' && $layoutCode == null) {
            $this->assertSame('Please enter Layout code', $body->error);
        }
    }

    /**
     * Each array is a test run
     * Format (string $source, string $triggerType, string|null $triggerCode, string $actionType, string $target)
     * @return array
     */
    public function editActionFailureCases()
    {
        return [
            'Wrong source' => ['playlist', 'touch', null, 'next', 'screen'],
            'Wrong trigger type' => ['layout', 'notExistingType', null, 'previous', 'screen'],
            'Wrong action type' => ['layout', 'touch', null, 'wrongAction', 'screen'],
            'Wrong target' => ['layout', 'touch', null, 'next', 'world'],
            'Target region without targetId' => ['layout', 'touch', 'trigger code', 'next', 'region'],
            'Missing trigger code for webhook' => ['region', 'webhook', null, 'next', 'screen'],
            'Navigate to Widget without widgetId' => ['layout', 'touch', null, 'navWidget', 'screen'],
            'Navigate to Layout without layoutCode' => ['layout', 'touch', null, 'navLayout', 'screen']
        ];
    }

    public function testCopyLayoutWithActions()
    {
        $layout = $this->checkout($this->layout);

        $this->getEntityProvider()->post('/action', [
            'triggerType' => 'touch',
            'actionType' => 'previous',
            'target' => 'screen',
            'layout' => $layout->layoutId
        ]);

        $this->layout = $this->publish($this->layout);

        $response = $this->sendRequest('POST', '/layout/copy/' . $this->layout->layoutId, ['copyMediaFiles' => 0, 'name' =>  Random::generateString()]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response);

        $body = json_decode($response->getBody());
        $this->assertSame(201, $body->status);

        $newLayoutId = $body->id;
        $newLayout = $this->getEntityProvider()->get('/layout', ['layoutId' => $newLayoutId, 'embed' => 'regions,actions'])[0];
        $this->assertNotEmpty($newLayout['actions']);
        // delete the copied layout
        (new XiboLayout($this->getEntityProvider()))->getById($newLayoutId)->delete();
    }
}
