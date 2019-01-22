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

namespace Xibo\Tests\Integration;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboCampaign;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboResolution;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class CampaignTest
 * @package Xibo\Tests
 */
class CampaignTest extends LocalWebTestCase
{

    protected $startCampaigns;
    protected $startLayouts;

    /**
     * setUp - called before every test automatically
     */

    public function setup()
    {  
        parent::setup();
        $this->startCampaigns = (new XiboCampaign($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all campaigns that weren't there initially
        $finalCamapigns = (new XiboCampaign($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining campaigns and nuke them
        foreach ($finalCamapigns as $campaign) {
            /** @var XiboCampaign $campaign */
            $flag = true;
            foreach ($this->startCampaigns as $startCampaign) {
               if ($startCampaign->campaignId == $campaign->campaignId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $campaign->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $campaign->campaignId . '. E:' . $e->getMessage());
                }
            }
        }
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
     * @param $type
     * @return int
     */
    private function getResolutionId($type)
    {
        if ($type === 'landscape') {
            $width = 1920;
            $height = 1080;
        } else if ($type === 'portrait') {
            $width = 1080;
            $height = 1920;
        } else {
            return -10;
        }

        //$this->getLogger()->debug('Querying for ' . $width . ', ' . $height);

        $resolutions = (new XiboResolution($this->getEntityProvider()))->get(['width' => $width, 'height' => $height]);

        if (count($resolutions) <= 0)
            return -10;

        return $resolutions[0]->resolutionId;
    }

    /**
     * Show Campaigns
     */
    public function testListAll()
    {
        # Get list of all campaigns
        $this->client->get('/campaign');
        # Check if call was successful
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
    }

    /**
     * Add Campaign
     * @return mixed
     */
    public function testAdd()
    {
        # Generate random name
        $name = Random::generateString(8, 'phpunit');
        # Add campaign
        $this->client->post('/campaign', [
            'name' => $name
        ]);
        # Check if call was successful
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Check if campaign has he name we want it to have
        $this->assertSame($name, $object->data->campaign);
    }

    /**
     * Test edit
     */
    public function testEdit()
    {
        # Generate name and add campaign
        $name = Random::generateString(8, 'phpunit');
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create($name);
        # Generate new random name
        $newName = Random::generateString(8, 'phpunit');
        # Edit the campaign we added and change the name
        $this->client->put('/campaign/' . $campaign->campaignId, [
            'name' => $newName
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # check if cal was successful
        $this->assertSame(200, $this->client->response->status());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        # Check if campaign has the new name now
        $this->assertSame($newName, $object->data->campaign);
    }

    /**
     * Test Delete
     */
    public function testDelete()
    {
        # generate two random names
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known campaigns
        $camp1 = (new XiboCampaign($this->getEntityProvider()))->create($name1);
        $camp2 = (new XiboCampaign($this->getEntityProvider()))->create($name2);
        # Delete the one we created last
        $this->client->delete('/campaign/' . $camp2->campaignId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        # Check only one remains
        $campaigns = (new XiboCampaign($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startCampaigns) + 1, count($campaigns));
        $flag = false;
        foreach ($campaigns as $campaign) {
            if ($campaign->campaignId == $camp1->campaignId) {
                $flag = true;
            }
        }
        # Check if everything is in order
        $this->assertTrue($flag, 'Campaign ID ' . $camp1->campaignId . ' was not found after deleting a different campaign');
        # Cleanup
        $camp1->delete();
    }

    /**
     * Assign Layout
     * @throws \Xibo\Exception\NotFoundException
     */
    public function testAssignLayout()
    {
        // Make a campaign with a known name
        $name = Random::generateString(8, 'phpunit');

        /* @var XiboCampaign $campaign */
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create($name);

        // Get a layout for the test
        $layout = (new XiboLayout($this->getEntityProvider()))->create(
            Random::generateString(8, 'phpunit'),
            'phpunit description',
            '',
            $this->getResolutionId('landscape')
        );

        // Call assign on the default layout
        $this->client->post('/campaign/layout/assign/' . $campaign->campaignId, [
            'layoutId' => [
                [
                    'layoutId' => $layout->layoutId,
                    'displayOrder' => 1
                ]
            ]
        ]);

        $this->assertSame(200, $this->client->response->status(), '/campaign/layout/assign/' . $campaign->campaignId . '. Body: ' . $this->client->response->body());
        # Get this campaign and check it has 1 layout assigned
        $campaignCheck = (new XiboCampaign($this->getEntityProvider()))->getById($campaign->campaignId);
        $this->assertSame($campaign->campaignId, $campaignCheck->campaignId, $this->client->response->body());
        $this->assertSame(1, $campaignCheck->numberLayouts, $this->client->response->body());
        # Delete layout as we no longer need it
        $campaign->delete();
        $layout->delete();
    }
    /**
     * Unassign Layout
     * @throws \Xibo\Exception\NotFoundException
     */
    public function testUnassignLayout()
    {
        // Make a campaign with a known name
        $name = Random::generateString(8, 'phpunit');
        /* @var XiboCampaign $campaign */
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create($name);

        // Get a layout for the test
        $layout = (new XiboLayout($this->getEntityProvider()))->create(
            Random::generateString(8, 'phpunit'),
            'phpunit description',
            '',
            $this->getResolutionId('landscape')
        );

        // Assign layout to campaign
        $campaign->assignLayout($layout->layoutId);
        # Call unassign on the created layout
        $this->client->post('/campaign/layout/unassign/' . $campaign->campaignId, ['layoutId' => [['layoutId' => $layout->layoutId, 'displayOrder' => 1]]]);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        # Get this campaign and check it has 0 layouts assigned
        $campaignCheck2 = (new XiboCampaign($this->getEntityProvider()))->getById($campaign->campaignId);
        $this->assertSame($campaign->campaignId, $campaignCheck2->campaignId, $this->client->response->body());
        $this->assertSame(0, $campaignCheck2->numberLayouts, $this->client->response->body());
        # Delete layout as we no longer need it
        $campaign->delete();
        $layout->delete();
    }

    /**
     * Assign Layout to layout specific campaignId - expect failure
     * @throws \Xibo\Exception\NotFoundException
     */
    public function testAssignLayoutFailure()
    {
        // Get a layout for the test
        $layout = (new XiboLayout($this->getEntityProvider()))->create(
            Random::generateString(8, 'phpunit'),
            'phpunit description',
            '',
            $this->getResolutionId('landscape')
        );

        // Call assign on the layout specific campaignId
        $this->client->post('/campaign/layout/assign/' . $layout->campaignId, [
            'layoutId' => [
                [
                    'layoutId' => $layout->layoutId,
                    'displayOrder' => 1
                ]
            ]
        ]);

        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
        $layout->delete();
    }
}
