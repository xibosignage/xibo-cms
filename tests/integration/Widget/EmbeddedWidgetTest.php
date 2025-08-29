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

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboEmbedded;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class EmbeddedWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class EmbeddedWidgetTest extends LocalWebTestCase
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
        $response = $this->getEntityProvider()->post('/playlist/widget/embedded/' . $layout->regions[0]->regionPlaylist->playlistId);

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
     * @throws \Xibo\OAuth2\Client\Exception\XiboApiException
     */
    public function testEdit()
    {
        $name = Random::generateString();
        $durationNew = 80;

        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
            'name' => $name,
            'duration' => $durationNew,
            'transparency' => 1,
            'scaleContent' => 1,
            'embedHtml' => null,
            'embedScript' => null,
            'embedStyle' => '<style type="text/css"> </style>'
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboEmbedded $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboEmbedded($this->getEntityProvider()))->hydrate($response[0]);

        $this->assertSame($name, $checkWidget->name);
        $this->assertSame($durationNew, $checkWidget->duration);
    }
}
