<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboStats;
use Xibo\OAuth2\Client\Entity\XiboText;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class StatisticsWidgetTest
 * @package Xibo\Tests\Integration
 */
class StatisticsWidgetTest extends LocalWebTestCase
{

    use LayoutHelperTrait, DisplayHelperTrait;

    protected $startMedias;
    protected $startLayouts;
    protected $startDisplays;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startMedias = (new XiboLibrary($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startDisplays = (new XiboDisplay($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all media files that weren't there initially
        $finalMedias = (new XiboLibrary($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        // Loop over any remaining media files and nuke them
        foreach ($finalMedias as $media) {
            /** @var XiboLibrary $media */
            $flag = true;
            foreach ($this->startMedias as $startMedia) {
                if ($startMedia->mediaId == $media->mediaId) {
                    $flag = false;
                }
            }
            if ($flag) {
                try {
                    $media->deleteAssigned();
                } catch (\Exception $e) {
                    $this->getLogger()->error('Media: Unable to delete ' . $media->mediaId . '. E:' . $e->getMessage());
                }
            }
        }

        // tearDown all layouts that weren't there initially
        $finalLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        // Loop over any remaining layouts and nuke them
        foreach ($finalLayouts as $layout) {
            /** @var XiboLayout $layout */
            $flag = true;
            foreach ($this->startLayouts as $startLayout) {
                if ($startLayout->layoutId == $layout->layoutId) {
                    $flag = false;
                }
            }
            if ($flag) {
                try {
                    $layout->delete();
                } catch (\Exception $e) {
                    $this->getLogger()->error('Layout: Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
                }
            }
        }

        // Tear down any displays that weren't there before
        $finalDisplays = (new XiboDisplay($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);

        // Loop over any remaining displays and nuke them
        foreach ($finalDisplays as $display) {
            /** @var XiboDisplay $display */
            $flag = true;
            foreach ($this->startDisplays as $startDisplay) {
                if ($startDisplay->displayId == $display->displayId) {
                    $flag = false;
                }
            }
            if ($flag) {
                try {
                    $display->delete();
                } catch (\Exception $e) {
                    $this->getLogger()->error('Display: Unable to delete ' . $display->displayId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    /**
     * Test the method call with default values
     * @group broken
     */
    public function testListAll()
    {
        $this->client->get('/stats');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     * Check if proof of play statistics are correct
     */
    public function testProof()
    {
        // Create a Display
        $display = $this->createDisplay();
        $this->displaySetLicensed($display);
        $hardwareId = $display->license;

        // Create layout with random name
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);

        // Add a region
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 100,100,475,425);

        // Create and assign new text widget
        $text = (new XiboText($this->getEntityProvider()))->create('Text item', 10, 1, 'marqueeRight', 5, null, null, 'TEST API TEXT', null, $region->regionPlaylist->playlistId);

        // First insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
                '<stats>
                        <stat fromdt="2018-02-12 00:00:00" 
                        todt="2018-02-15 00:00:00" 
                        type="widget" 
                        scheduleid="0" 
                        layoutid="'.$layout->layoutId.'" 
                        mediaid="'.$text->widgetId.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Second insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-15 00:00:00" 
                        todt="2018-02-16 00:00:00" 
                        type="widget" 
                        scheduleid="0" 
                        layoutid="'.$layout->layoutId.'" 
                        mediaid="'.$text->widgetId.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Third insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-16 00:00:00" 
                        todt="2018-02-17 00:00:00" 
                        type="widget" 
                        scheduleid="0" 
                        layoutid="'.$layout->layoutId.'" 
                        mediaid="'.$text->widgetId.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Get stats and see if they match with what we expect
        $this->client->get('/stats' , [
            'fromDt' => '2018-02-12 00:00:00',
            'toDt' => '2018-02-17 00:00:00',
            'displayId' => $display->displayId
        ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        //$this->getLogger()->debug($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $stats = (new XiboStats($this->getEntityProvider()))->get([$layout->layoutId]);
        //print_r($stats);

    }
}
