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
use Xibo\OAuth2\Client\Entity\XiboImage;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ProofOfPlayOnOff
 * @package Xibo\Tests\Integration
 */
class ProofOfPlayOnOff extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboLayout */
    protected $layout2;

    /** @var XiboLibrary */
    protected $media;

    /** @var int */
    protected $widgetId;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for ' . get_class($this) .' Test');

        // Create a Layout
        $this->layout = $this->createLayout();

        // get draft Layout
        $layout = $this->getDraft($this->layout);

        // Create another Layout with stat enabled
        $this->layout2 = (new XiboLayout($this->getEntityProvider()))->create(
            Random::generateString(8, 'phpunit'),
            'phpunit layout',
            '',
            $this->getResolutionId('landscape'),
            1
        );

        // Upload some media
        $this->media = (new XiboLibrary($this->getEntityProvider()))->create('API video '.rand(1,400), PROJECT_ROOT . '/tests/resources/HLH264.mp4');

        // Assign the media we've created to our regions playlist.
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$this->media->mediaId], 10, $layout->regions[0]->regionPlaylist->playlistId);

        // Store the widgetId
        $this->widgetId = $playlist->widgets[0]->widgetId;

        $this->getLogger()->debug('Finished Setup');
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        $this->getLogger()->debug('Tear down for ' . get_class($this) . ' Test');

        parent::tearDown();

        // Delete the Layout we've been working with
        $this->deleteLayout($this->layout);

        // Delete the second Layout we've been working with
        $this->deleteLayout($this->layout2);

        // Delete the media record
        $this->media->deleteAssigned();
    }

    /**
     * Each array is a test run
     * Format (enableStat)
     * @return array
     */
    public function enableStatLayoutCases()
    {
        return [
            // various correct enableStat flag
            'Layout enableStat Off' => [0],
            'Layout enableStat On' => [1]
        ];
    }

    /**
     * Each array is a test run
     * Format (enableStat)
     * @return array
     */
    public function enableStatMediaAndWidgetCases()
    {
        return [
            // various correct enableStat options - for both media and widget are same
            'enableStat Off' => ['Off'],
            'enableStat On' => ['On'],
            'enableStat Inherit' => ['Inherit']
        ];
    }

    /**
     * Add enableStat flag was set to 0 when creating the layout
     */
    public function testAddLayoutEnableStatOff()
    {
        // Check that the layout enable stat sets to off
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($this->layout->layoutId);
        $this->assertSame(0, $layout->enableStat);
    }

    /**
     * Add enableStat flag was set to 1 when creating the layout
     */
    public function testAddLayoutEnableStatOn()
    {
        // Check that the layout enable stat sets to on
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($this->layout2->layoutId);
        $this->assertSame(1, $layout->enableStat);
    }

    /**
     * Edit enableStat flag of an existing layout
     * @dataProvider enableStatLayoutCases
     */
    public function testEditLayoutEnableStat($enableStat)
    {
        $name = Random::generateString(8, 'phpunit');
        $description = Random::generateString(8, 'description');

        $response = $this->sendRequest('PUT','/layout/' . $this->layout->layoutId, [
            'name' => $name,
            'description' => $description,
            'enableStat' => $enableStat
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame($enableStat, $object->data->enableStat);

        // Check that the layout enable stat sets to on/off
        $layout = (new XiboLayout($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($enableStat, $layout->enableStat);
    }

    /**
     * Edit enableStat flag of an existing media file
     * @dataProvider enableStatMediaAndWidgetCases
     */
    public function testEditMediaEnableStat($enableStat)
    {
        $name = Random::generateString(8, 'phpunit');

        // Edit media file
        $response = $this->sendRequest('PUT','/library/' . $this->media->mediaId, [
            'name' => $name,
            'duration' => 50,
            'updateInLayouts' => 1,
            'enableStat' => $enableStat
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertSame($enableStat, $object->data->enableStat);

        $media = (new XiboLibrary($this->getEntityProvider()))->getById($this->media->mediaId);
        $this->assertSame($enableStat, $media->enableStat);
    }

    /**
     * @throws \Xibo\OAuth2\Client\Exception\XiboApiException
     * @dataProvider enableStatMediaAndWidgetCases
     */
    public function testEditWidgetEnableStat($enableStat)
    {
        // Now try to edit our assigned Media Item.
        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
            'enableStat' => $enableStat,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboImage $widgetOptions */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $widgetOptions = (new XiboImage($this->getEntityProvider()))->hydrate($response[0]);

        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'enableStat') {
                $this->assertSame($enableStat, $option['value']);
            }
        }
    }

    /**
     * Copy Layout - enableStat flag copied from an existing layout
     */
    public function testCopyLayoutCheckEnableStat()
    {
        // Generate new random name
        $nameCopy = Random::generateString(8, 'phpunit');

        // Call copy
        $response = $this->sendRequest('POST','/layout/copy/' . $this->layout2->layoutId, [
            'name' => $nameCopy,
            'description' => 'Copy',
            'copyMediaFiles' => 1
        ], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
        ]);

        $this->assertSame(200, $response->getStatusCode());

        // Check if copied layout has enableStat flag of copying layout
        $object = json_decode($response->getBody());
        $this->assertSame($this->layout2->enableStat, $object->data->enableStat);
    }

    /**
     * Bulk On/Off Layout enableStat
     * @dataProvider enableStatLayoutCases     
     */
    public function testLayoutBulkEnableStat($enableStat)
    {
        // Call Set enable stat
        $response = $this->sendRequest('PUT','/layout/setenablestat/' . $this->layout->layoutId, [
            'enableStat' => $enableStat
        ], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $layout = (new XiboLayout($this->getEntityProvider()))->getById($this->layout->layoutId);
        $this->assertSame($enableStat, $layout->enableStat);
    }

    /**
     * Bulk On/Off/Inherit Media enableStat
     * @dataProvider enableStatMediaAndWidgetCases
     */
    public function testMediaBulkEnableStat($enableStat)
    {
        // Call Set enable stat
        $response = $this->sendRequest('PUT','/library/setenablestat/' . $this->media->mediaId, [
            'enableStat' => $enableStat
        ], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $media = (new XiboLibrary($this->getEntityProvider()))->getById($this->media->mediaId);
        $this->assertSame($enableStat, $media->enableStat);
    }
}