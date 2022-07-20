<?php
/*
 * Copyright (C) 2021 Xibo Signage Ltd
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

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboCampaign;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class CampaignLayoutManagementTest
 * @package Xibo\Tests\integration
 */
class CampaignLayoutManagementTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var XiboCampaign */
    protected $campaign;

    /** @var XiboLayout */
    protected $layout;

    public function setup()
    {
        parent::setup();

        // Create a Campaign and Layout
        $this->campaign = (new XiboCampaign($this->getEntityProvider()))->create(Random::generateString());
        $this->layout = $this->createLayout();
        $this->layout = $this->publish($this->layout);
    }

    public function tearDown()
    {
        // Delete the Layout we've been working with
        $this->deleteLayout($this->layout);

        // Delete the Campaign
        $this->campaign->delete();

        parent::tearDown();
    }

    /**
     * Assign Layout
     */
    public function testAssignOneLayout()
    {
        // Assign one layout
        $response = $this->sendRequest('POST', '/campaign/layout/assign/' . $this->campaign->campaignId, [
            'layoutId' => $this->layout->layoutId
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Request failed: ' . $response->getBody()->getContents());

        // Get this campaign and check it has 1 layout assigned
        $campaignCheck = (new XiboCampaign($this->getEntityProvider()))->getById($this->campaign->campaignId);
        $this->assertSame($this->campaign->campaignId, $campaignCheck->campaignId, $response->getBody());
        $this->assertSame(1, $campaignCheck->numberLayouts, $response->getBody());
    }

    /**
     * Assign Layout
     */
    public function testAssignTwoLayouts()
    {
        $response = $this->sendRequest('PUT', '/campaign/' . $this->campaign->campaignId, [
            'name' => $this->campaign->campaign,
            'manageLayouts' => 1,
            'layoutIds' => [$this->layout->layoutId, $this->layout->layoutId]
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Request failed');

        // Get this campaign and check it has 2 layouts assigned
        $campaignCheck = (new XiboCampaign($this->getEntityProvider()))->getById($this->campaign->campaignId);
        $this->assertSame($this->campaign->campaignId, $campaignCheck->campaignId, $response->getBody());
        $this->assertSame(2, $campaignCheck->numberLayouts, $response->getBody());
    }

    /**
     * Unassign Layout
     */
    public function testUnassignLayout()
    {
        $this->getEntityProvider()->post('/campaign/layout/assign/' . $this->campaign->campaignId, [
            'layoutId' => $this->layout->layoutId
        ]);

        $response = $this->sendRequest('PUT', '/campaign/' . $this->campaign->campaignId, [
            'name' => $this->campaign->campaign,
            'manageLayouts' => 1,
            'layoutIds' => []
        ]);

        $campaignCheck = (new XiboCampaign($this->getEntityProvider()))->getById($this->campaign->campaignId);
        $this->assertSame($this->campaign->campaignId, $campaignCheck->campaignId, $response->getBody());
        $this->assertSame(0, $campaignCheck->numberLayouts, $response->getBody());
    }

    /**
     * Assign Layout to layout specific campaignId - expect failure
     * @throws \Exception
     */
    public function testAssignLayoutFailure()
    {
        // Call assign on the layout specific campaignId
        $request = $this->createRequest('POST', '/campaign/layout/assign/' . $this->layout->campaignId);
        $request = $request->withParsedBody([
            'layoutId' => $this->layout->layoutId
        ]);

        try {
            $this->app->handle($request);
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(422, $exception->getCode(), 'Expecting failure, received ' . $exception->getMessage());
        }
    }
}
