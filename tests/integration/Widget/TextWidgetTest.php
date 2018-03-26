<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015-2018 Spring Signage Ltd
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

use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboText;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class TextWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

	protected $startLayouts;
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all layouts that weren't there initially
        $finalLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining layouts and nuke them
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
                    fwrite(STDERR, 'Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }
        /**
     * @group add
     * @dataProvider provideSuccessCases
     */
    public function testAdd($name, $duration, $useDuration, $effect, $speed, $backgroundColor, $marqueeInlineSelector, $text, $javaScript)
    {
        //parent::setupEnv();
        // Create layout
        $layout = $this->createLayout();
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        $response = $this->client->post('/playlist/widget/text/' . $playlistId, [
            'name' => $name,
            'duration' => $duration,
            'useDuration' => $useDuration,
            'effect' => $effect,
            'speed' => $speed,
            'backgroundColor' => $backgroundColor,
            'marqueeInlineSelector' => $marqueeInlineSelector,
            'text' => $text,
            'javaScript' => $javaScript
            ]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $textOptions = (new XiboText($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($name, $textOptions->name);
        $this->assertSame($duration, $textOptions->duration);
        foreach ($textOptions->widgetOptions as $option) {
            if ($option['option'] == 'effect') {
                $this->assertSame($effect, $option['value']);
            }
            if ($option['option'] == 'speed') {
                $this->assertSame($speed, intval($option['value']));
            }
            if ($option['option'] == 'text') {
                $this->assertSame($text, $option['value']);
            }
        }
    }

    /**
     * Each array is a test run
     * Format ($name, $duration, $useDuration, $effect, $speed, $backgroundColor, $marqueeInlineSelector, $text, $javaScript)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'text 1' => ['Text item', 10, 1, 'marqueeRight', 5, null, null, 'TEST API TEXT', null],
            'text with formatting' => ['Text item 2', 20, 1, 'marqueeLeft', 3, null, null, '<p><span style=color:#FFFFFF;><span style=font-size:48px;>TEST</span></span></p>', null],
            'text with background Colour' => ['text item 3', 5, 1, null, 0, '#d900000', null, 'red background', null]
        ];
    }

    public function testEdit()
    {
        // Create layout
        $layout = $this->createLayout();
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create a text widget with wrapper
        $text = (new XiboText($this->getEntityProvider()))->create('Text item', 10, 1, 'marqueeRight', 5, null, null, 'TEST API TEXT', null, $playlistId);
        $nameNew = 'Edited Name';
        $durationNew = 80;
        $textNew = 'Edited Text';
        $response = $this->client->put('/playlist/widget/' . $text->widgetId, [
            'name' => $nameNew,
            'duration' => $durationNew,
            'useDuration' => 1,
            'effect' => $text->effect,
            'speed' => $text->speed,
            'backgroundColor' => null,
            'marqueeInlineSelector' => null,
            'text' => $textNew,
            'javaScript' => null
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $textOptions = (new XiboText($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($nameNew, $textOptions->name);
        $this->assertSame($durationNew, $textOptions->duration);
                foreach ($textOptions->widgetOptions as $option) {
            if ($option['option'] == 'text') {
                $this->assertSame($textNew, $option['value']);
            }
        }
    }

    public function testDelete()
    {
        // Create layout
        $layout = $this->createLayout();
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create a text widget with wrapper
        $text = (new XiboText($this->getEntityProvider()))->create('Text item', 10, 1, 'marqueeRight', 5, null, null, 'TEST API TEXT', null, $playlistId);
        # Delete it
        $this->client->delete('/playlist/widget/' . $text->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
