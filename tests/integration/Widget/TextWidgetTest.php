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

use Xibo\OAuth2\Client\Entity\XiboText;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class TextWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class TextWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var int */
    protected $widgetId;

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
        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist->playlistId);

        $this->widgetId = $response['widgetId'];
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Delete the Layout we've been working with
        $this->deleteLayout($this->publishedLayout);

        parent::tearDown();

        $this->getLogger()->debug('Tear down for ' . get_class($this) .' Test');
    }

    /**
     * Each array is a test run
     * Format ($name, $duration, $useDuration, $effect, $speed, $backgroundColor, $marqueeInlineSelector, $text, $javaScript)
     * @return array
     */
    public function provideSuccessCases()
    {
        // Sets of data used in testAdd
        return [
            'text 1' => ['Text item', 10, 1, 'marqueeRight', 5, null, null, 'TEST API TEXT', null],
            'text with formatting' => ['Text item 2', 20, 1, 'marqueeLeft', 3, null, null, '<p><span style=color:#FFFFFF;><span style=font-size:48px;>TEST</span></span></p>', null],
            'text with background Colour' => ['text item 3', 5, 1, null, 0, '#d900000', null, 'red background', null]
        ];
    }

    /**
     * Test Edit
     * @dataProvider provideSuccessCases
     */
    public function testEdit($name, $duration, $useDuration, $effect, $speed, $backgroundColor, $marqueeInlineSelector, $text, $javaScript)
    {
        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
            'name' => $name,
            'duration' => $duration,
            'useDuration' => $useDuration,
            'effect' => $effect,
            'speed' => $speed,
            'backgroundColor' => $backgroundColor,
            'marqueeInlineSelector' => $marqueeInlineSelector,
            'text' => $text,
            'javaScript' => $javaScript
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboText $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboText($this->getEntityProvider()))->hydrate($response[0]);

        $this->assertSame($name, $checkWidget->name);
        $this->assertSame($duration, $checkWidget->duration);

        foreach ($checkWidget->widgetOptions as $option) {
            if ($option['option'] == 'text') {
                $this->assertSame($text, $option['value']);
            }
        }
    }
}
