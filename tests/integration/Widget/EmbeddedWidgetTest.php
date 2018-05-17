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

use Xibo\OAuth2\Client\Entity\XiboEmbedded;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class EmbeddedWidgetTest extends LocalWebTestCase
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

    public function testAdd()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        $response = $this->client->post('/playlist/widget/embedded/' . $playlistId, [
            'name' => 'API Embedded widget',
            'duration' => 60,
            'transparency' => 0,
            'scaleContent' => 0,
            'embedHtml' => null,
            'embedScript' => null,
            'embedStyle' => null
            ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $embeddedOptions = (new XiboEmbedded($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame('API Embedded widget', $embeddedOptions->name);       
        $this->assertSame(60, $embeddedOptions->duration);
    }

    public function testEdit()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        $durationNew = 80;
        # Create embedded widget
        $embedded = (new XiboEmbedded($this->getEntityProvider()))->create('API embedded', 60, 1, 0, 0, null, null, null, $playlistId);
        $response = $this->client->put('/playlist/widget/' . $embedded->widgetId, [
            'name' => 'EDITED Name',
            'duration' => $durationNew,
            'transparency' => 1,
            'scaleContent' => 1,
            'embedHtml' => null,
            'embedScript' => null,
            'embedStyle' => '<style type="text/css"> </style>'
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $embeddedOptions = (new XiboEmbedded($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame('EDITED Name', $embeddedOptions->name);       
        $this->assertSame($durationNew, $embeddedOptions->duration);
    }

    public function testDelete()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create embedded widget
        $embedded = (new XiboEmbedded($this->getEntityProvider()))->create('API embedded', 60, 1, 0, 0, null, null, null, $playlistId);
        # Delete it
        $this->client->delete('/playlist/widget/' . $embedded->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
