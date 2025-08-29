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

namespace Xibo\Tests\integration\Widget;

use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class WidgetDeleteTest
 * @package Xibo\Tests\integration\Widget
 */
class WidgetDeleteTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var int */
    protected $widgetId;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup for ' . get_class($this) .' Test');

        // Create a Layout
        $this->publishedLayout = $this->createLayout();

        // Checkout
        $layout = $this->getDraft($this->publishedLayout);

        // Create a widget
        $response = $this->getEntityProvider()->post('/playlist/widget/datasetview/' . $layout->regions[0]->regionPlaylist->playlistId);

        // Store the widgetId
        $this->widgetId = $response['widgetId'];
    }

    public function tearDown()
    {
        parent::tearDown();

        // Delete the Layout we've been working with
        $this->deleteLayout($this->publishedLayout);
    }
    // </editor-fold>

    /**
     * Test edit a widget
     */
    public function testDelete()
    {
        // Delete the widget we've added
        $response = $this->sendRequest('DELETE','/playlist/widget/' . $this->widgetId);

        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
    }
}